<?php

namespace App\Http\Controllers\Api\Branch;

use App\Http\Controllers\Controller;
use App\Models\Thread;
use App\Models\ThreadParticipant;
use App\Models\CaseMessage;
use App\Models\MessageRead;
use App\Models\CaseModel;
use App\Models\CaseFile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Mail\ThreadCreatedNotification;
use App\Mail\ThreadMessageNotification;

class BranchThreadController extends Controller
{
    /**
     * Get all threads for a case (Branch view).
     */
    public function index(Request $request, string $caseId): JsonResponse
    {
        try {
            $user = $request->user();

            // Check authentication and authorization
            if (!$user || $user->role !== 'branch_admin' || !$user->branch_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Only branch admins can manage threads.'
                ], 403);
            }

            // Verify case exists and belongs to branch's company
            $case = CaseModel::with(['company', 'branch'])
                ->where('id', $caseId)
                ->where('company_id', $user->company_id)
                ->firstOrFail();

            // Get threads where user is a participant with participants and unread counts
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
                    'case' => $case->only(['id', 'case_token', 'title', 'status']),
                    'threads' => $threads
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve threads',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Create a new thread for a case.
     */
    public function store(Request $request, string $caseId): JsonResponse
    {
        try {
            $user = $request->user();

            // Check authentication and authorization
            if (!$user || $user->role !== 'branch_admin' || !$user->branch_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Only branch admins can create threads.'
                ], 403);
            }

            // Verify case exists and belongs to branch's company
            $case = CaseModel::where('id', $caseId)
                ->where('company_id', $user->company_id)
                ->firstOrFail();

            $validator = Validator::make($request->all(), [
                'title' => 'required|string|max:255',
                'description' => 'nullable|string|max:1000',
                'participants' => 'required|array|min:1',
                'participants.*' => 'required|exists:users,id',
                'initial_message' => 'required|string|max:5000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Verify all participants belong to the same company
            $participantUsers = User::whereIn('id', $request->participants)
                ->where('company_id', $user->company_id)
                ->get();

            if ($participantUsers->count() !== count($request->participants)) {
                return response()->json([
                    'success' => false,
                    'message' => 'All participants must belong to the same company.'
                ], 422);
            }

            DB::beginTransaction();

            // Create thread
            $thread = Thread::create([
                'case_id' => $caseId,
                'title' => $request->title,
                'description' => $request->description,
                'status' => 'active',
                'created_by' => $user->id,
                'created_by_type' => class_basename($user)
            ]);

            // Add creator as participant if not in participants list
            $participantIds = collect($request->participants);
            if (!$participantIds->contains($user->id)) {
                $participantIds->push($user->id);
            }

            // Add participants
            foreach ($participantIds as $participantId) {
                // Determine role based on user type - use branch_admin for creator, company_admin for other participants
                $participantRole = ($participantId == $user->id) ? 'branch_admin' : 'company_admin';

                ThreadParticipant::create([
                    'thread_id' => $thread->id,
                    'user_id' => $participantId,
                    'role' => $participantRole,
                    'joined_at' => now()
                ]);
            }

            // Add initial message
            $message = CaseMessage::create([
                'case_id' => $caseId,
                'thread_id' => $thread->id,
                'sender_id' => $user->id,
                'sender_type' => class_basename($user),
                'message' => $request->initial_message,
                'has_attachments' => false
            ]);

            // Mark as read for creator
            MessageRead::create([
                'message_id' => $message->id,
                'user_id' => $user->id,
                'read_at' => now()
            ]);

            DB::commit();

            // Load relationships for response
            $thread->load(['participants.user:id,name,email,role']);

            // Send email notifications to all participants (except creator)
            try {
                $case->load(['company', 'branch']);
                $participants = $thread->participants()->with('user')->get();

                foreach ($participants as $participant) {
                    if ($participant->user && $participant->user_id !== $user->id) {
                        Mail::to($participant->user->email)
                            ->queue(new ThreadCreatedNotification($case, $thread, $participant->user, $user));

                        Log::info('Thread creation notification sent', [
                            'thread_id' => $thread->id,
                            'case_id' => $case->id,
                            'recipient_email' => $participant->user->email,
                            'creator_id' => $user->id
                        ]);
                    }
                }
            } catch (\Exception $e) {
                Log::error('Failed to send thread creation notifications', [
                    'thread_id' => $thread->id,
                    'case_id' => $case->id,
                    'error' => $e->getMessage()
                ]);
                // Don't fail the response for notification issues
            }

            return response()->json([
                'success' => true,
                'message' => 'Thread created successfully',
                'data' => $thread
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create thread',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get thread details with messages and participants.
     */
    public function show(Request $request, string $caseId, string $threadId): JsonResponse
    {
        try {
            $user = $request->user();

            // Check authentication and authorization
            if (!$user || $user->role !== 'branch_admin' || !$user->branch_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Only branch admins can view threads.'
                ], 403);
            }

            // Verify case and thread exist and belong to branch's company
            $case = CaseModel::where('id', $caseId)
                ->where('company_id', $user->company_id)
                ->firstOrFail();

            $thread = Thread::with([
                'participants.user:id,name,email,role',
                'messages.readRecords',
                'messages.files'
            ])
                ->where('id', $threadId)
                ->where('case_id', $caseId)
                ->firstOrFail();

            // Check if user is participant
            $isParticipant = $thread->participants->contains('user_id', $user->id);

            if (!$isParticipant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. You are not a participant in this thread.'
                ], 403);
            }

            // Mark messages as read for this user
            $this->markThreadAsRead($threadId, $user->id);

            // Prepare messages with read status
            $messages = $thread->messages->map(function ($message) use ($user, $caseId, $threadId) {
                return [
                    'id' => $message->id,
                    'sender_id' => $message->sender_id,
                    'sender_type' => $message->sender_type,
                    'sender_name' => $message->sender_name, // Use the safe attribute
                    'message' => $message->message,
                    'has_attachments' => $message->has_attachments,
                    'files' => $message->files ? $message->files->map(function ($file) use ($message, $caseId, $threadId) {
                        return [
                            'id' => $file->id,
                            'filename' => $file->original_name,
                            'stored_name' => $file->stored_name,
                            'file_size' => $file->file_size,
                            'file_type' => $file->file_type,
                            'download_url' => route('branch.threads.messages.download', [
                                'caseId' => $caseId,
                                'threadId' => $threadId,
                                'messageId' => $message->id,
                                'filename' => $file->stored_name
                            ])
                        ];
                    }) : [],
                    'created_at' => $message->created_at,
                    'is_read_by_me' => $message->readRecords->contains('user_id', $user->id),
                    'read_by' => $message->readRecords->map(function ($read) {
                        return [
                            'user_id' => $read->user_id,
                            'user_name' => $read->user->name ?? 'Unknown',
                            'read_at' => $read->read_at
                        ];
                    })
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'thread' => [
                        'id' => $thread->id,
                        'case_id' => $thread->case_id,
                        'title' => $thread->title,
                        'description' => $thread->description,
                        'status' => $thread->status,
                        'created_by' => $thread->created_by,
                        'created_at' => $thread->created_at,
                        'participants' => $thread->participants
                    ],
                    'messages' => $messages,
                    'case' => $case->only(['id', 'case_token', 'title', 'status'])
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve thread',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get messages for a specific thread.
     */
    public function getMessages(Request $request, string $caseId, string $threadId): JsonResponse
    {
        try {
            $user = $request->user();

            // Check authentication and authorization
            if (!$user || $user->role !== 'branch_admin' || !$user->branch_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Only branch admins can view messages.'
                ], 403);
            }

            // Verify case belongs to user's branch
            $case = CaseModel::where('id', $caseId)
                ->where('branch_id', $user->branch_id)
                ->first();

            if (!$case) {
                return response()->json([
                    'success' => false,
                    'message' => 'Case not found or access denied'
                ], 404);
            }

            // Get thread
            $thread = Thread::where('id', $threadId)
                ->where('case_id', $caseId)
                ->first();

            if (!$thread) {
                return response()->json([
                    'success' => false,
                    'message' => 'Thread not found'
                ], 404);
            }

            // Get messages with pagination
            $perPage = $request->get('per_page', 20);
            $messages = CaseMessage::where('thread_id', $threadId)
                ->with(['readRecords.user', 'files'])
                ->orderBy('created_at', 'asc')
                ->paginate($perPage);

            // Format messages
            $formattedMessages = $messages->getCollection()->map(function ($message) use ($user, $caseId, $threadId) {
                return [
                    'id' => $message->id,
                    'sender_type' => $message->sender_type,
                    'sender_id' => $message->sender_id,
                    'sender_name' => $message->sender_name, // Use the safe attribute
                    'message' => $message->message,
                    'has_attachments' => $message->has_attachments,
                    'files' => $message->files ? $message->files->map(function ($file) use ($message, $caseId, $threadId) {
                        return [
                            'id' => $file->id,
                            'filename' => $file->original_name,
                            'stored_name' => $file->stored_name,
                            'file_size' => $file->file_size,
                            'file_type' => $file->file_type,
                            'download_url' => route('branch.threads.messages.download', [
                                'caseId' => $caseId,
                                'threadId' => $threadId,
                                'messageId' => $message->id,
                                'filename' => $file->stored_name
                            ])
                        ];
                    }) : [],
                    'created_at' => $message->created_at,
                    'is_read_by_me' => $message->readRecords->contains('user_id', $user->id),
                    'read_by' => $message->readRecords->map(function ($read) {
                        return [
                            'user_id' => $read->user_id,
                            'user_name' => $read->user->name ?? 'Unknown',
                            'read_at' => $read->read_at
                        ];
                    })
                ];
            });

            $messages->setCollection($formattedMessages);

            return response()->json([
                'success' => true,
                'data' => [
                    'thread' => [
                        'id' => $thread->id,
                        'case_id' => $thread->case_id,
                        'title' => $thread->title,
                        'description' => $thread->description,
                        'status' => $thread->status,
                        'created_by' => $thread->created_by,
                        'created_at' => $thread->created_at
                    ],
                    'messages' => $messages,
                    'case' => $case->only(['id', 'case_token', 'title', 'status'])
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve messages',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Add a message to a thread.
     */
    public function addMessage(Request $request, string $caseId, string $threadId): JsonResponse
    {
        try {
            $user = $request->user();

            // Check authentication and authorization
            if (!$user || $user->role !== 'branch_admin' || !$user->branch_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Only branch admins can add messages.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'message' => 'required_without:attachments|string|max:5000',
                'attachments' => 'nullable|array|max:5',
                'attachments.*' => 'file|mimes:pdf,doc,docx,jpg,jpeg,png,gif,txt,xlsx,xls|max:10240'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // If no message and no attachments, fail
            if (empty($request->message) && !$request->hasFile('attachments')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Either message or attachments are required.'
                ], 422);
            }

            // Verify case belongs to branch's company
            $case = CaseModel::where('id', $caseId)
                ->where('company_id', $user->company_id)
                ->firstOrFail();

            // Verify thread exists and user is participant
            $thread = Thread::with('participants')
                ->where('id', $threadId)
                ->where('case_id', $caseId)
                ->firstOrFail();

            $isParticipant = $thread->participants->contains('user_id', $user->id);

            if (!$isParticipant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. You are not a participant in this thread.'
                ], 403);
            }

            DB::beginTransaction();

            // Prepare message content
            $messageText = $request->message ?? '';
            if (empty($messageText) && $request->hasFile('attachments')) {
                $attachmentCount = count($request->file('attachments'));
                $messageText = 'Attachment' . ($attachmentCount > 1 ? 's' : '') . ' sent';
            }

            // Create message
            $message = CaseMessage::create([
                'case_id' => $caseId,
                'thread_id' => $threadId,
                'sender_id' => $user->id,
                'sender_type' => class_basename($user),
                'message' => $messageText,
                'has_attachments' => $request->hasFile('attachments')
            ]);

            // Handle file attachments
            $uploadedFiles = [];
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $filename = time() . '_' . Str::random(8) . '_' . $file->getClientOriginalName();
                    $path = $file->storeAs('case_attachments', $filename, 'public');

                    $caseFile = CaseFile::create([
                        'case_id' => $caseId,
                        'case_message_id' => $message->id,
                        'original_name' => $file->getClientOriginalName(),
                        'stored_name' => $filename,
                        'file_path' => $path,
                        'mime_type' => $file->getMimeType(),
                        'file_size' => $file->getSize(),
                        'file_type' => $this->categorizeFileType($file->getMimeType()),
                        'uploaded_by_type' => 'user',
                        'uploaded_by_id' => $user->id,
                        'is_confidential' => false,
                        'processing_status' => 'completed'
                    ]);

                    $uploadedFiles[] = [
                        'id' => $caseFile->id,
                        'original_name' => $caseFile->original_name,
                        'file_size' => $caseFile->file_size,
                        'file_type' => $caseFile->file_type
                    ];
                }

                // Reload files relationship
                $message->load('files');
            }

            // Mark as read for sender
            MessageRead::create([
                'message_id' => $message->id,
                'user_id' => $user->id,
                'read_at' => now()
            ]);

            // Update thread timestamp
            $thread->touch();

            DB::commit();

            // Send email notifications to all participants (except sender)
            try {
                $case->load(['company', 'branch']);
                $participants = $thread->participants()->with('user')->get();

                foreach ($participants as $participant) {
                    if ($participant->user && $participant->user_id !== $user->id) {
                        Mail::to($participant->user->email)
                            ->queue(new ThreadMessageNotification($case, $message, $participant->user, 'participant'));

                        Log::info('Message notification sent', [
                            'message_id' => $message->id,
                            'thread_id' => $thread->id,
                            'case_id' => $case->id,
                            'recipient_email' => $participant->user->email,
                            'sender_id' => $user->id
                        ]);
                    }
                }
            } catch (\Exception $e) {
                Log::error('Failed to send message notifications', [
                    'message_id' => $message->id,
                    'thread_id' => $thread->id,
                    'case_id' => $case->id,
                    'error' => $e->getMessage()
                ]);
                // Don't fail the response for notification issues
            }

            // Get sender name safely
            $senderName = 'Unknown';
            if ($message->sender_type === 'system') {
                $senderName = 'System';
            } elseif ($message->sender_type === 'reporter') {
                $senderName = 'Case Reporter';
            } else {
                // Try to load the actual sender for other types (User, etc.)
                try {
                    $message->load('sender:id,name,email');
                    $senderName = $message->sender ? $message->sender->name : ucfirst($message->sender_type);
                } catch (\Exception $e) {
                    $senderName = ucfirst($message->sender_type);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Message added successfully',
                'data' => [
                    'id' => $message->id,
                    'sender_id' => $message->sender_id,
                    'sender_type' => $message->sender_type,
                    'sender_name' => $senderName,
                    'message' => $message->message,
                    'has_attachments' => $message->has_attachments,
                    'files' => $uploadedFiles,
                    'created_at' => $message->created_at
                ]
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to add message',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Mark thread messages as read for current user.
     */
    public function markAsRead(Request $request, string $caseId, string $threadId): JsonResponse
    {
        try {
            $user = $request->user();

            // Check authentication and authorization
            if (!$user || $user->role !== 'branch_admin' || !$user->branch_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Only branch admins can mark messages as read.'
                ], 403);
            }

            // Verify case belongs to branch's company
            $case = CaseModel::where('id', $caseId)
                ->where('company_id', $user->company_id)
                ->firstOrFail();

            // Verify thread exists and user is participant
            $thread = Thread::with('participants')
                ->where('id', $threadId)
                ->where('case_id', $caseId)
                ->firstOrFail();

            $isParticipant = $thread->participants->contains('user_id', $user->id);

            if (!$isParticipant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. You are not a participant in this thread.'
                ], 403);
            }

            $markedCount = $this->markThreadAsRead($threadId, $user->id);

            return response()->json([
                'success' => true,
                'message' => 'Messages marked as read',
                'data' => [
                    'marked_count' => $markedCount
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark messages as read',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get unread message counts for user across all threads in a case.
     */
    public function getUnreadCounts(Request $request, string $caseId): JsonResponse
    {
        try {
            $user = $request->user();

            // Check authentication and authorization
            if (!$user || $user->role !== 'branch_admin' || !$user->branch_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Only branch admins can view unread counts.'
                ], 403);
            }

            // Verify case belongs to branch's company
            $case = CaseModel::where('id', $caseId)
                ->where('company_id', $user->company_id)
                ->firstOrFail();

            $threads = Thread::where('case_id', $caseId)
                ->with('participants')
                ->get()
                ->filter(function ($thread) use ($user) {
                    return $thread->participants->contains('user_id', $user->id);
                })
                ->map(function ($thread) use ($user) {
                    return [
                        'thread_id' => $thread->id,
                        'thread_title' => $thread->title,
                        'unread_count' => $this->getUnreadMessageCount($thread->id, $user->id)
                    ];
                });

            $totalUnread = $threads->sum('unread_count');

            return response()->json([
                'success' => true,
                'data' => [
                    'case_id' => $caseId,
                    'total_unread' => $totalUnread,
                    'threads' => $threads
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get unread counts',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Helper method to get unread message count for a user in a thread.
     */
    private function getUnreadMessageCount(string $threadId, string $userId): int
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
    private function markThreadAsRead(string $threadId, string $userId): int
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
            MessageRead::updateOrCreate([
                'message_id' => $messageId,
                'user_id' => $userId
            ], [
                'read_at' => now()
            ]);
            $markedCount++;
        }

        return $markedCount;
    }

    /**
     * Download an attachment from a thread message.
     */
    public function downloadAttachment(Request $request, string $caseId, string $threadId, string $messageId, string $filename)
    {
        try {
            $user = $request->user();

            // Check authentication and authorization
            if (!$user || $user->role !== 'branch_admin' || !$user->branch_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Only branch admins can download attachments.'
                ], 403);
            }

            // Verify case belongs to branch's company
            $case = CaseModel::where('id', $caseId)
                ->where('company_id', $user->company_id)
                ->firstOrFail();

            // Verify thread exists and user is participant
            $thread = Thread::with('participants')
                ->where('id', $threadId)
                ->where('case_id', $caseId)
                ->firstOrFail();

            $isParticipant = $thread->participants->contains('user_id', $user->id);

            if (!$isParticipant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. You are not a participant in this thread.'
                ], 403);
            }

            // Find the message
            $message = CaseMessage::where('id', $messageId)
                ->where('thread_id', $threadId)
                ->where('case_id', $caseId)
                ->where('has_attachments', true)
                ->with('files')
                ->firstOrFail();

            // Find the attachment file
            $file = $message->files->where('stored_name', $filename)->first();

            if (!$file) {
                abort(404, 'Attachment not found');
            }

            // Check if file exists on storage
            if (!Storage::disk('public')->exists($file->file_path)) {
                abort(404, 'File not found on storage');
            }

            $fullPath = Storage::disk('public')->path($file->file_path);
            return response()->download($fullPath, $file->original_name);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to download attachment',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Categorize file type based on MIME type.
     */
    private function categorizeFileType(string $mimeType): string
    {
        $imageTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
        $documentTypes = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/plain',
            'text/csv'
        ];
        $videoTypes = ['video/mp4', 'video/avi', 'video/mov', 'video/wmv', 'video/webm'];
        $audioTypes = ['audio/mp3', 'audio/wav', 'audio/m4a', 'audio/ogg'];

        if (in_array($mimeType, $imageTypes)) {
            return 'image';
        } elseif (in_array($mimeType, $documentTypes)) {
            return 'document';
        } elseif (in_array($mimeType, $videoTypes)) {
            return 'video';
        } elseif (in_array($mimeType, $audioTypes)) {
            return 'audio';
        } else {
            return 'other';
        }
    }
}
