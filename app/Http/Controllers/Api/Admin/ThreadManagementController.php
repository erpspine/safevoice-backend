<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Thread;
use App\Models\ThreadParticipant;
use App\Models\CaseMessage;
use App\Models\MessageRead;
use App\Models\CaseModel;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Mail\ThreadCreatedNotification;
use App\Mail\ThreadMessageNotification;

class ThreadManagementController extends Controller
{
    /**
     * Get all threads for a case (Admin view).
     */
    public function index(Request $request, string $caseId): JsonResponse
    {
        try {
            $user = Auth::user();

            // Verify case exists and user has access
            $case = CaseModel::with(['company', 'branch'])
                ->findOrFail($caseId);

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
            $user = Auth::user();

            // Verify case exists
            $case = CaseModel::findOrFail($caseId);

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
                // Determine appropriate role based on user's actual role
                $participantUser = User::find($participantId);
                $participantRole = 'company_admin'; // Default role

                if ($participantUser) {
                    // Use roles that comply with database constraint
                    if (in_array($participantUser->role, ['branch_admin', 'company_admin', 'investigator', 'admin', 'super_admin'])) {
                        $participantRole = $participantUser->role;
                    } else {
                        // For other roles, default to company_admin
                        $participantRole = 'company_admin';
                    }
                }

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
            $user = Auth::user();

            // Verify case and thread exist
            $case = CaseModel::findOrFail($caseId);

            $thread = Thread::with([
                'participants.user:id,name,email,role',
                'messages.sender:id,name,email',
                'messages.readRecords'
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
            $messages = $thread->messages->map(function ($message) use ($user) {
                return [
                    'id' => $message->id,
                    'sender_id' => $message->sender_id,
                    'sender_type' => $message->sender_type,
                    'sender_name' => $message->sender->name ?? 'Unknown',
                    'message' => $message->message,
                    'has_attachments' => $message->has_attachments,
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
     * Add a message to a thread.
     */
    public function addMessage(Request $request, string $caseId, string $threadId): JsonResponse
    {
        try {
            $user = Auth::user();

            $validator = Validator::make($request->all(), [
                'message' => 'required|string|max:5000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

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

            // Create message
            $message = CaseMessage::create([
                'case_id' => $caseId,
                'thread_id' => $threadId,
                'sender_id' => $user->id,
                'sender_type' => class_basename($user),
                'message' => $request->message,
                'has_attachments' => false
            ]);

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
                $case = CaseModel::find($caseId);
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
                    'case_id' => $caseId,
                    'error' => $e->getMessage()
                ]);
                // Don't fail the response for notification issues
            }

            // Load sender details for response
            $message->load('sender:id,name,email');

            return response()->json([
                'success' => true,
                'message' => 'Message added successfully',
                'data' => [
                    'id' => $message->id,
                    'sender_id' => $message->sender_id,
                    'sender_type' => $message->sender_type,
                    'sender_name' => $message->sender->name,
                    'message' => $message->message,
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
     * Add participants to a thread.
     */
    public function addParticipants(Request $request, string $caseId, string $threadId): JsonResponse
    {
        try {
            $user = Auth::user();

            $validator = Validator::make($request->all(), [
                'participants' => 'required|array|min:1',
                'participants.*' => 'required|exists:users,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Verify thread exists and user can manage it
            $thread = Thread::with('participants')
                ->where('id', $threadId)
                ->where('case_id', $caseId)
                ->firstOrFail();

            $userParticipant = $thread->participants->where('user_id', $user->id)->first();

            if (!$userParticipant || !in_array($userParticipant->role, ['creator', 'moderator'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. You cannot add participants to this thread.'
                ], 403);
            }

            DB::beginTransaction();

            $addedParticipants = [];
            foreach ($request->participants as $participantId) {
                // Check if already participant
                $existingParticipant = $thread->participants->where('user_id', $participantId)->first();

                if (!$existingParticipant) {
                    // Determine appropriate role based on user's actual role
                    $participantUser = User::find($participantId);
                    $participantRole = 'company_admin'; // Default role

                    if ($participantUser) {
                        // Use roles that comply with database constraint
                        if (in_array($participantUser->role, ['branch_admin', 'company_admin', 'investigator', 'admin', 'super_admin'])) {
                            $participantRole = $participantUser->role;
                        } else {
                            // For other roles, default to company_admin
                            $participantRole = 'company_admin';
                        }
                    }

                    $participant = ThreadParticipant::create([
                        'thread_id' => $threadId,
                        'user_id' => $participantId,
                        'role' => $participantRole,
                        'joined_at' => now()
                    ]);

                    $participant->load('user:id,name,email');
                    $addedParticipants[] = $participant;
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Participants added successfully',
                'data' => [
                    'added_participants' => $addedParticipants,
                    'total_participants' => $thread->participants()->count()
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to add participants',
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
            $user = Auth::user();

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
            $user = Auth::user();

            // Verify case exists
            CaseModel::findOrFail($caseId);

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
}
