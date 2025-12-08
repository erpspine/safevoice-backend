<?php

namespace App\Http\Controllers\Api\Investigator;

use App\Http\Controllers\Controller;
use App\Models\CaseThread;
use App\Models\CaseMessage;
use App\Models\InvestigatorCaseAssignment;
use App\Models\CaseFile;
use App\Models\MessageReadReceipt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class InvestigatorThreadController extends Controller
{
    /**
     * Get threads for a specific case
     */
    public function index(Request $request, $caseId)
    {
        try {
            $user = $request->user();

            // Verify investigator has access to this case
            $assignment = InvestigatorCaseAssignment::where('investigator_id', $user->id)
                ->where('case_id', $caseId)
                ->first();

            if (!$assignment) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Case not found or not assigned to you'
                ], 404);
            }

            $threads = CaseThread::where('case_id', $caseId)
                ->with([
                    'participants.user:id,name,email,role',
                    'messages' => function ($q) {
                        $q->with(['sender:id,name,email,role', 'files'])
                            ->orderBy('created_at', 'desc')
                            ->limit(1);
                    }
                ])
                ->withCount(['messages', 'participants'])
                ->orderBy('updated_at', 'desc')
                ->get()
                ->map(function ($thread) use ($user) {
                    $lastMessage = $thread->messages->first();
                    $unreadCount = $this->getUnreadCount($thread->id, $user->id);

                    return [
                        'id' => $thread->id,
                        'subject' => $thread->subject,
                        'description' => $thread->description,
                        'status' => $thread->status,
                        'created_at' => $thread->created_at,
                        'updated_at' => $thread->updated_at,
                        'participants_count' => $thread->participants_count,
                        'messages_count' => $thread->messages_count,
                        'unread_count' => $unreadCount,
                        'last_message' => $lastMessage ? [
                            'id' => $lastMessage->id,
                            'message_preview' => substr($lastMessage->message, 0, 100) . '...',
                            'sender' => [
                                'id' => $lastMessage->sender->id,
                                'name' => $lastMessage->sender->name,
                                'role' => $lastMessage->sender->role,
                            ],
                            'created_at' => $lastMessage->created_at,
                            'has_files' => $lastMessage->files->count() > 0,
                        ] : null,
                        'participants' => $thread->participants->map(function ($participant) {
                            return [
                                'id' => $participant->user->id,
                                'name' => $participant->user->name,
                                'email' => $participant->user->email,
                                'role' => $participant->role,
                            ];
                        }),
                    ];
                });

            return response()->json([
                'status' => 'success',
                'data' => [
                    'threads' => $threads,
                    'total_count' => $threads->count(),
                ]
            ], 200);
        } catch (\Exception $e) {
            Log::error('Investigator threads index error', [
                'user_id' => $request->user()->id,
                'case_id' => $caseId,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve threads'
            ], 500);
        }
    }

    /**
     * Get specific thread details with messages
     */
    public function show(Request $request, $caseId, $threadId)
    {
        try {
            $user = $request->user();

            // Verify investigator has access to this case
            $assignment = InvestigatorCaseAssignment::where('investigator_id', $user->id)
                ->where('case_id', $caseId)
                ->first();

            if (!$assignment) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Case not found or not assigned to you'
                ], 404);
            }

            $thread = CaseThread::where('id', $threadId)
                ->where('case_id', $caseId)
                ->with([
                    'participants.user:id,name,email,role,profile_picture',
                    'case:id,case_token,title,company_id',
                    'case.company:id,name'
                ])
                ->first();

            if (!$thread) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Thread not found'
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'data' => [
                    'thread' => [
                        'id' => $thread->id,
                        'subject' => $thread->subject,
                        'description' => $thread->description,
                        'status' => $thread->status,
                        'case' => [
                            'id' => $thread->case->id,
                            'case_token' => $thread->case->case_token,
                            'title' => $thread->case->title,
                            'company_name' => $thread->case->company->name,
                        ],
                        'participants' => $thread->participants->map(function ($participant) {
                            return [
                                'id' => $participant->user->id,
                                'name' => $participant->user->name,
                                'email' => $participant->user->email,
                                'role' => $participant->role,
                                'profile_picture' => $participant->user->profile_picture,
                            ];
                        }),
                        'created_at' => $thread->created_at,
                        'updated_at' => $thread->updated_at,
                    ]
                ]
            ], 200);
        } catch (\Exception $e) {
            Log::error('Investigator thread show error', [
                'user_id' => $request->user()->id,
                'case_id' => $caseId,
                'thread_id' => $threadId,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve thread'
            ], 500);
        }
    }

    /**
     * Get messages for a specific thread
     */
    public function getMessages(Request $request, $caseId, $threadId)
    {
        try {
            $user = $request->user();

            // Verify access
            $assignment = InvestigatorCaseAssignment::where('investigator_id', $user->id)
                ->where('case_id', $caseId)
                ->first();

            if (!$assignment) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Case not found or not assigned to you'
                ], 404);
            }

            $thread = CaseThread::where('id', $threadId)
                ->where('case_id', $caseId)
                ->first();

            if (!$thread) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Thread not found'
                ], 404);
            }

            $page = $request->get('page', 1);
            $perPage = min($request->get('per_page', 20), 100);

            $messages = CaseMessage::where('thread_id', $threadId)
                ->with([
                    'sender:id,name,email,role,profile_picture',
                    'files',
                    'readReceipts.user:id,name'
                ])
                ->orderBy('created_at', 'asc')
                ->paginate($perPage, ['*'], 'page', $page);

            $messagesData = $messages->getCollection()->map(function ($message) use ($user) {
                return [
                    'id' => $message->id,
                    'message' => $message->message,
                    'sender' => [
                        'id' => $message->sender->id,
                        'name' => $message->sender->name,
                        'email' => $message->sender->email,
                        'role' => $message->sender->role,
                        'profile_picture' => $message->sender->profile_picture,
                    ],
                    'sender_type' => $message->sender_type,
                    'files' => $message->files->map(function ($file) {
                        return [
                            'id' => $file->id,
                            'filename' => $file->filename,
                            'original_filename' => $file->original_filename,
                            'file_type' => $file->file_type,
                            'file_size' => $file->file_size,
                            'category' => $file->category,
                        ];
                    }),
                    'read_by' => $message->readReceipts->map(function ($receipt) {
                        return [
                            'user_id' => $receipt->user->id,
                            'user_name' => $receipt->user->name,
                            'read_at' => $receipt->read_at,
                        ];
                    }),
                    'is_read_by_me' => $message->readReceipts->where('user_id', $user->id)->isNotEmpty(),
                    'created_at' => $message->created_at,
                ];
            });

            return response()->json([
                'status' => 'success',
                'data' => [
                    'messages' => $messagesData,
                    'pagination' => [
                        'current_page' => $messages->currentPage(),
                        'last_page' => $messages->lastPage(),
                        'per_page' => $messages->perPage(),
                        'total' => $messages->total(),
                        'from' => $messages->firstItem(),
                        'to' => $messages->lastItem(),
                    ]
                ]
            ], 200);
        } catch (\Exception $e) {
            Log::error('Investigator thread messages error', [
                'user_id' => $request->user()->id,
                'case_id' => $caseId,
                'thread_id' => $threadId,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve messages'
            ], 500);
        }
    }

    /**
     * Add a new message to thread
     */
    public function addMessage(Request $request, $caseId, $threadId)
    {
        try {
            $user = $request->user();

            // Verify access
            $assignment = InvestigatorCaseAssignment::where('investigator_id', $user->id)
                ->where('case_id', $caseId)
                ->first();

            if (!$assignment) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Case not found or not assigned to you'
                ], 404);
            }

            $thread = CaseThread::where('id', $threadId)
                ->where('case_id', $caseId)
                ->first();

            if (!$thread) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Thread not found'
                ], 404);
            }

            $request->validate([
                'message' => 'required|string|max:5000',
                'files' => 'sometimes|array|max:10',
                'files.*' => 'file|max:10240|mimes:jpg,jpeg,png,gif,pdf,doc,docx,xls,xlsx,txt'
            ]);

            DB::beginTransaction();

            // Create message
            $message = CaseMessage::create([
                'thread_id' => $thread->id,
                'sender_id' => $user->id,
                'sender_type' => 'investigator',
                'message' => $request->message,
            ]);

            // Handle file uploads
            $uploadedFiles = [];
            if ($request->hasFile('files')) {
                foreach ($request->file('files') as $file) {
                    $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                    $path = $file->storeAs('private/case-files', $filename);

                    $caseFile = CaseFile::create([
                        'case_id' => $caseId,
                        'filename' => $filename,
                        'original_filename' => $file->getClientOriginalName(),
                        'file_type' => $file->getMimeType(),
                        'file_size' => $file->getSize(),
                        'category' => 'thread_message',
                        'uploaded_by' => $user->id,
                        'message_id' => $message->id,
                    ]);

                    $uploadedFiles[] = [
                        'id' => $caseFile->id,
                        'filename' => $caseFile->filename,
                        'original_filename' => $caseFile->original_filename,
                        'file_type' => $caseFile->file_type,
                        'file_size' => $caseFile->file_size,
                    ];
                }
            }

            // Update thread timestamp
            $thread->touch();

            DB::commit();

            // Send email notifications to participants (except sender)
            $this->sendMessageNotifications($thread, $message, $user);

            Log::info('Investigator sent thread message', [
                'user_id' => $user->id,
                'case_id' => $caseId,
                'thread_id' => $threadId,
                'message_id' => $message->id,
                'files_count' => count($uploadedFiles),
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Message sent successfully',
                'data' => [
                    'message' => [
                        'id' => $message->id,
                        'message' => $message->message,
                        'sender' => [
                            'id' => $user->id,
                            'name' => $user->name,
                            'role' => $user->role,
                        ],
                        'sender_type' => $message->sender_type,
                        'files' => $uploadedFiles,
                        'created_at' => $message->created_at,
                    ]
                ]
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Investigator thread message error', [
                'user_id' => $request->user()->id,
                'case_id' => $caseId,
                'thread_id' => $threadId,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to send message'
            ], 500);
        }
    }

    /**
     * Mark thread messages as read
     */
    public function markAsRead(Request $request, $caseId, $threadId)
    {
        try {
            $user = $request->user();

            // Verify access
            $assignment = InvestigatorCaseAssignment::where('investigator_id', $user->id)
                ->where('case_id', $caseId)
                ->first();

            if (!$assignment) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Case not found or not assigned to you'
                ], 404);
            }

            $messageIds = CaseMessage::where('thread_id', $threadId)
                ->where('sender_id', '!=', $user->id)
                ->pluck('id');

            foreach ($messageIds as $messageId) {
                MessageReadReceipt::updateOrCreate([
                    'message_id' => $messageId,
                    'user_id' => $user->id,
                ], [
                    'read_at' => now(),
                ]);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Messages marked as read'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Investigator mark messages read error', [
                'user_id' => $request->user()->id,
                'case_id' => $caseId,
                'thread_id' => $threadId,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to mark messages as read'
            ], 500);
        }
    }

    /**
     * Download thread message attachment
     */
    public function downloadAttachment(Request $request, $caseId, $threadId, $messageId, $filename)
    {
        try {
            $user = $request->user();

            // Verify access
            $assignment = InvestigatorCaseAssignment::where('investigator_id', $user->id)
                ->where('case_id', $caseId)
                ->first();

            if (!$assignment) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Case not found or not assigned to you'
                ], 404);
            }

            $file = CaseFile::where('message_id', $messageId)
                ->where('case_id', $caseId)
                ->where('filename', $filename)
                ->first();

            if (!$file) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'File not found'
                ], 404);
            }

            $filePath = storage_path('app/private/case-files/' . $file->filename);

            if (!file_exists($filePath)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'File not found on storage'
                ], 404);
            }

            Log::info('Investigator downloaded thread attachment', [
                'user_id' => $user->id,
                'case_id' => $caseId,
                'thread_id' => $threadId,
                'message_id' => $messageId,
                'filename' => $filename
            ]);

            return response()->download($filePath, $file->original_filename);
        } catch (\Exception $e) {
            Log::error('Investigator attachment download error', [
                'user_id' => $request->user()->id,
                'case_id' => $caseId,
                'thread_id' => $threadId,
                'message_id' => $messageId,
                'filename' => $filename,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to download attachment'
            ], 500);
        }
    }

    /**
     * Get unread count for user in thread
     */
    private function getUnreadCount($threadId, $userId)
    {
        return CaseMessage::where('thread_id', $threadId)
            ->where('sender_id', '!=', $userId)
            ->whereDoesntHave('readReceipts', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            })
            ->count();
    }

    /**
     * Send email notifications to thread participants
     */
    private function sendMessageNotifications($thread, $message, $sender)
    {
        try {
            $participants = $thread->participants()
                ->where('user_id', '!=', $sender->id)
                ->with(['user', 'thread.case'])
                ->get();

            foreach ($participants as $participant) {
                // Queue email notification
                \Mail::to($participant->user->email)->queue(
                    new \App\Mail\ThreadMessageNotification(
                        $thread->case,
                        $message,
                        $participant->user,
                        $participant->role
                    )
                );

                Log::info('Message notification queued', [
                    'message_id' => $message->id,
                    'thread_id' => $thread->id,
                    'case_id' => $thread->case->id,
                    'recipient_email' => $participant->user->email,
                    'sender_id' => $sender->id
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to send message notifications', [
                'thread_id' => $thread->id,
                'message_id' => $message->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}
