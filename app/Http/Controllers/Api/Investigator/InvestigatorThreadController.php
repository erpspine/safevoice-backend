<?php

namespace App\Http\Controllers\Api\Investigator;

use App\Http\Controllers\Controller;
use App\Models\Thread;
use App\Models\CaseMessage;
use App\Models\CaseAssignment;
use App\Models\CaseModel;
use App\Models\CaseFile;
use App\Models\MessageReadReceipt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
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
            $assignment = CaseAssignment::where('investigator_id', $user->id)
                ->where('case_id', $caseId)
                ->first();

            if (!$assignment) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Case not found or not assigned to you'
                ], 404);
            }

            // Get case info
            $case = CaseModel::with(['company', 'branch'])
                ->where('id', $caseId)
                ->first();

            // Get threads where user is a participant
            $threads = Thread::where('case_id', $caseId)
                ->whereHas('participants', function ($query) use ($user) {
                    $query->where('user_id', $user->id);
                })
                ->with([
                    'participants.user:id,name,email,role',
                    'messages' => function ($query) {
                        $query->latest()->limit(1);
                    }
                ])
                ->get()
                ->map(function ($thread) use ($user) {
                    // Get unread message count for this user
                    $unreadCount = $this->getUnreadMessageCount($thread->id, $user->id);

                    return [
                        'id' => $thread->id,
                        'case_id' => $thread->case_id,
                        'title' => $thread->title,
                        'description' => $thread->description,
                        'status' => $thread->status,
                        'created_by' => $thread->created_by,
                        'created_at' => $thread->created_at,
                        'updated_at' => $thread->updated_at,
                        'participants_count' => $thread->participants->count(),
                        'messages_count' => $thread->messages()->count(),
                        'unread_count' => $unreadCount,
                        'latest_message' => $thread->messages->first(),
                        'participants' => $thread->participants
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'case' => $case ? $case->only(['id', 'case_token', 'title', 'status', 'type']) : null,
                    'threads' => $threads
                ]
            ]);
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
            $assignment = CaseAssignment::where('investigator_id', $user->id)
                ->where('case_id', $caseId)
                ->first();

            if (!$assignment) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Case not found or not assigned to you'
                ], 404);
            }

            // Get case info
            $case = CaseModel::where('id', $caseId)->first();

            $thread = Thread::where('id', $threadId)
                ->where('case_id', $caseId)
                ->with([
                    'participants.user:id,name,email,role,profile_picture'
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
            $assignment = CaseAssignment::where('investigator_id', $user->id)
                ->where('case_id', $caseId)
                ->first();

            if (!$assignment) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Case not found or not assigned to you'
                ], 404);
            }

            $thread = Thread::where('id', $threadId)
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
                ->with(['files'])
                ->orderBy('created_at', 'asc')
                ->paginate($perPage, ['*'], 'page', $page);

            // Mark messages as read for this user
            $this->markThreadAsRead($threadId, $user->id);

            $messagesData = $messages->getCollection()->map(function ($message) use ($user, $caseId, $threadId) {
                // Get read status
                $readBy = DB::table('message_reads')
                    ->where('message_id', $message->id)
                    ->join('users', 'message_reads.user_id', '=', 'users.id')
                    ->select('users.id as user_id', 'users.name as user_name', 'message_reads.read_at')
                    ->get();

                $isReadByMe = DB::table('message_reads')
                    ->where('message_id', $message->id)
                    ->where('user_id', $user->id)
                    ->exists();

                return [
                    'id' => $message->id,
                    'sender_id' => $message->sender_id,
                    'sender_type' => $message->sender_type,
                    'sender_name' => $message->sender_name,
                    'message' => $message->message,
                    'has_attachments' => $message->has_attachments,
                    'files' => $message->files ? $message->files->map(function ($file) use ($caseId, $threadId, $message) {
                        return [
                            'id' => $file->id,
                            'filename' => $file->original_name ?? $file->filename,
                            'stored_name' => $file->stored_name ?? $file->filename,
                            'file_size' => $file->file_size,
                            'file_type' => $file->file_type,
                            'download_url' => route('investigator.threads.messages.download', [
                                'caseId' => $caseId,
                                'threadId' => $threadId,
                                'messageId' => $message->id,
                                'filename' => $file->stored_name ?? $file->filename
                            ])
                        ];
                    }) : [],
                    'created_at' => $message->created_at,
                    'is_read_by_me' => $isReadByMe,
                    'read_by' => $readBy->map(function ($read) {
                        return [
                            'user_id' => $read->user_id,
                            'user_name' => $read->user_name,
                            'read_at' => $read->read_at,
                        ];
                    })
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
            $assignment = CaseAssignment::where('investigator_id', $user->id)
                ->where('case_id', $caseId)
                ->first();

            if (!$assignment) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Case not found or not assigned to you'
                ], 404);
            }

            $thread = Thread::where('id', $threadId)
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
            $assignment = CaseAssignment::where('investigator_id', $user->id)
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
            $assignment = CaseAssignment::where('investigator_id', $user->id)
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
    private function getUnreadMessageCount($threadId, $userId)
    {
        return CaseMessage::where('thread_id', $threadId)
            ->where('sender_id', '!=', $userId) // Don't count own messages
            ->whereNotExists(function ($query) use ($userId) {
                $query->select(DB::raw(1))
                    ->from('message_reads')
                    ->whereColumn('message_reads.message_id', 'case_messages.id')
                    ->where('message_reads.user_id', $userId);
            })
            ->count();
    }

    /**
     * Helper method to mark all unread messages in a thread as read for a user.
     */
    private function markThreadAsRead($threadId, $userId)
    {
        // Get unread messages for this user in this thread
        $unreadMessages = CaseMessage::where('thread_id', $threadId)
            ->where('sender_id', '!=', $userId) // Don't mark own messages
            ->whereNotExists(function ($query) use ($userId) {
                $query->select(DB::raw(1))
                    ->from('message_reads')
                    ->whereColumn('message_reads.message_id', 'case_messages.id')
                    ->where('message_reads.user_id', $userId);
            })
            ->pluck('id');

        $markedCount = 0;
        foreach ($unreadMessages as $messageId) {
            DB::table('message_reads')->updateOrInsert([
                'message_id' => $messageId,
                'user_id' => $userId
            ], [
                'read_at' => now(),
                'created_at' => now(),
                'updated_at' => now()
            ]);
            $markedCount++;
        }

        return $markedCount;
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
