<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Models\Thread;
use App\Models\CaseModel;
use App\Models\User;
use App\Models\Notification;
use App\Services\ThreadService;
use App\Mail\ThreadMessageNotification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ThreadController extends Controller
{
    public function __construct(
        protected ThreadService $threadService
    ) {}

    /**
     * Get all threads for a specific case.
     * Automatically creates a thread if none exists.
     */
    public function index(Request $request, string $caseId): JsonResponse
    {
        try {
            // Verify case exists and get/create thread
            $case = CaseModel::findOrFail($caseId);

            // This will auto-create thread if none exists
            $this->threadService->getOrCreateCaseThread($caseId);

            // Get all threads with relationships
            $threads = $this->threadService->getCaseThreads($caseId);

            return response()->json([
                'status' => 'success',
                'data'  => $threads->map(function ($thread) {
                    // Calculate unread messages count
                    $unreadCount = 0;
                    if ($thread->participant && $thread->participant->last_read_message_id) {
                        // Count messages after the last read message
                        $unreadCount = \App\Models\CaseMessage::where('thread_id', $thread->id)
                            ->where('id', '>', $thread->participant->last_read_message_id)
                            ->count();
                    } else {
                        // All messages are unread if never read
                        $unreadCount = $thread->messages_count ?? 0;
                    }

                    return [
                        'id' => $thread->id,
                        'note' => $thread->note,
                        'investigator_id' => $thread->investigator_id,
                        'investigator_name' => $thread->investigator?->name,
                        'created_at' => $thread->created_at,
                        'updated_at' => $thread->updated_at,
                        'messages_count' => $thread->messages_count ?? 0,
                        'participant' => $thread->participant ? [
                            'id' => $thread->participant->id,
                            'role' => $thread->participant->role,
                            'investigator_id' => $thread->participant->investigator_id,
                            'investigator_name' => $thread->participant->investigator?->name,
                            'last_read_at' => $thread->participant->last_read_at,
                            'unread_messages_count' => $unreadCount,
                        ] : null,
                        'last_message' => $thread->messages->first() ? [
                            'id' => $thread->messages->first()->id,
                            'message' => $thread->messages->first()->message,
                            'sender_type' => $thread->messages->first()->sender_type,
                            'created_at' => $thread->messages->first()->created_at,
                        ] : null,
                    ];
                }),


            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch threads',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get a specific thread with all messages.
     */
    public function show(Request $request, string $caseId, string $threadId): JsonResponse
    {
        try {
            // Verify case exists
            $case = CaseModel::findOrFail($caseId);

            // Get thread with all relationships
            $thread = Thread::where('case_id', $caseId)
                ->where('id', $threadId)
                ->with([
                    'participant',
                    'messages.sender',
                    'messages.files',
                    'messages.readRecord'
                ])
                ->firstOrFail();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'thread' => [
                        'id' => $thread->id,
                        'case_id' => $thread->case_id,
                        'note' => $thread->note,
                        'created_at' => $thread->created_at,
                        'updated_at' => $thread->updated_at,
                        'participant' => $thread->participant ? [
                            'id' => $thread->participant->id,
                            'role' => $thread->participant->role,
                            'last_read_message_id' => $thread->participant->last_read_message_id,
                            'last_read_at' => $thread->participant->last_read_at,
                        ] : null,
                        'messages' => $thread->messages->map(function ($message) {
                            return [
                                'id' => $message->id,
                                'message' => $message->message,
                                'sender_type' => $message->sender_type,
                                'sender_id' => $message->sender_id,
                                'has_attachments' => $message->has_attachments,
                                'created_at' => $message->created_at,
                                'files' => $message->files->map(function ($file) {
                                    return [
                                        'id' => $file->id,
                                        'original_name' => $file->original_name,
                                        'file_type' => $file->file_type,
                                        'file_size' => $file->file_size,
                                        'is_confidential' => $file->is_confidential,
                                    ];
                                }),
                                'is_read' => $message->readRecord !== null,
                                'read_at' => $message->readRecord?->read_at,
                            ];
                        }),
                        'messages_count' => $thread->messages->count(),
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch thread',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Create a new thread for a case.
     */
    public function store(Request $request, string $caseId): JsonResponse
    {
        $request->validate([
            'title' => 'nullable|string|max:255',
            'note' => 'nullable|string|max:500',
            'role' => 'required|string|in:reporter,investigator',
        ]);

        try {
            // Verify case exists
            $case = CaseModel::findOrFail($caseId);

            // Create thread
            $thread = Thread::create([
                'case_id' => $caseId,
                'title' => $request->title ?? 'Discussion Thread',
                'description' => $request->note,
                'status' => 'active',
                'created_by_type' => 'user',
                'note' => $request->note,
            ]);

            // Create thread participant
            $thread->participant()->create([
                'role' => $request->role,
            ]);

            // Load relationships
            $thread->load('participant');

            return response()->json([
                'status' => 'success',
                'message' => 'Thread created successfully',
                'data' => [
                    'thread' => [
                        'id' => $thread->id,
                        'case_id' => $thread->case_id,
                        'note' => $thread->note,
                        'created_at' => $thread->created_at,
                        'participant' => [
                            'id' => $thread->participant->id,
                            'role' => $thread->participant->role,
                        ]
                    ]
                ]
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create thread',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Add a message to a thread.
     */
    public function addMessage(Request $request, string $caseId, string $threadId): JsonResponse
    {
        $request->validate([
            'message' => 'required|string',
            'sender_type' => 'required|string|in:reporter,investigator',
            'attachments' => 'nullable|array',
            'attachments.*' => 'file|mimes:pdf,doc,docx,jpg,jpeg,png,gif|max:10240'
        ]);

        try {
            // Verify case and thread exist
            $case = CaseModel::findOrFail($caseId);
            $thread = Thread::where('id', $threadId)
                ->where('case_id', $caseId)
                ->firstOrFail();

            // Create message
            $message = $thread->messages()->create([
                'case_id' => $caseId,
                'message' => $request->message,
                'sender_type' => $request->sender_type
            ]);

            // Handle attachments if any
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $filename = time() . '_' . $file->getClientOriginalName();
                    $path = $file->storeAs('case_attachments', $filename, 'public');

                    $message->files()->create([
                        'case_id' => $caseId,
                        'original_name' => $file->getClientOriginalName(),
                        'stored_name' => $filename,
                        'file_path' => $path,
                        'file_size' => $file->getSize(),
                        'mime_type' => $file->getMimeType()
                    ]);
                }
            }

            // Load relationships for response
            $message->load('files');

            // Send notifications to responsible parties (outside main transaction)
            try {
                $this->sendMessageNotifications($case, $message);
            } catch (\Exception $e) {
                Log::error('Failed to send message notifications', [
                    'case_id' => $case->id,
                    'message_id' => $message->id,
                    'error' => $e->getMessage()
                ]);
                // Don't fail the message sending for notification issues
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Message sent successfully',
                'data' => [
                    'message' => [
                        'id' => $message->id,
                        'message' => $message->message,
                        'sender_type' => $message->sender_type,
                        'sent_at' => $message->created_at,
                        'attachments' => $message->files->map(function ($file) use ($message) {
                            return [
                                'id' => $file->id,
                                'filename' => $file->original_name,
                                'file_size' => $file->file_size,
                                'download_url' => route('public.cases.messages.download', [
                                    'caseId' => $message->case_id,
                                    'messageId' => $message->id,
                                    'filename' => $file->original_name
                                ])
                            ];
                        })
                    ]
                ]
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to send message',
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
            // Verify case and thread exist
            $case = CaseModel::findOrFail($caseId);
            $thread = Thread::where('id', $threadId)
                ->where('case_id', $caseId)
                ->firstOrFail();

            // Get messages with pagination
            $messages = $thread->messages()
                ->with('files')
                ->orderBy('created_at', 'asc')
                ->paginate($request->get('per_page', 20));
            return response()->json([
                'status' => 'success',
                'data' => [
                    'messages' => $messages->items(),
                    'pagination' => [
                        'current_page' => $messages->currentPage(),
                        'last_page' => $messages->lastPage(),
                        'per_page' => $messages->perPage(),
                        'total' => $messages->total()
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get messages',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Mark messages as read in a thread.
     */
    public function markAsRead(Request $request, string $caseId, string $threadId): JsonResponse
    {
        $request->validate([
            'message_id' => 'nullable|string|exists:case_messages,id'
        ]);

        try {
            // Verify case and thread exist
            $case = CaseModel::findOrFail($caseId);
            $thread = Thread::where('id', $threadId)
                ->where('case_id', $caseId)
                ->firstOrFail();

            $messageId = $request->message_id;

            // If no specific message ID provided, mark all messages as read
            if (!$messageId) {
                $lastMessage = $thread->messages()->latest('created_at')->first();
                $messageId = $lastMessage?->id;
            }

            // Update thread participant's last read message
            if ($messageId && $thread->participant) {
                $thread->participant->update([
                    'last_read_message_id' => $messageId,
                    'last_read_at' => now()
                ]);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Messages marked as read'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to mark messages as read',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Send notifications to responsible parties when a new message is sent.
     * Logic is similar to case notifications but for thread messages.
     */
    private function sendMessageNotifications(CaseModel $case, \App\Models\CaseMessage $message): void
    {
        try {
            // If no branch is specified, cannot send notifications
            if (!$case->branch_id) {
                return;
            }

            // Get IDs of involved parties (employee_id in involved_parties is actually user_id)
            $involvedUserIds = $case->involvedParties()->pluck('employee_id')->toArray();

            // Get all primary recipients for this branch
            $primaryRecipients = User::where('branch_id', $case->branch_id)
                ->where('recipient_type', 'primary')
                ->where('status', 'active')
                ->where('is_verified', true)
                ->get();

            // Filter out involved parties from primary recipients
            $eligiblePrimaryRecipients = $primaryRecipients->filter(function ($user) use ($involvedUserIds) {
                return !in_array($user->id, $involvedUserIds);
            });

            $recipientType = 'primary'; // Track which type of recipient we're using

            // If we have eligible primary recipients, send to them
            if ($eligiblePrimaryRecipients->count() > 0) {
                $recipients = $eligiblePrimaryRecipients;
            } else {
                // All primary recipients are involved, send to alternative recipients
                $recipients = User::where('branch_id', $case->branch_id)
                    ->where('recipient_type', 'alternative')
                    ->where('status', 'active')
                    ->where('is_verified', true)
                    ->get();

                // Also exclude involved parties from alternative recipients
                $recipients = $recipients->filter(function ($user) use ($involvedUserIds) {
                    return !in_array($user->id, $involvedUserIds);
                });

                $recipientType = 'alternative';
            }

            // If no recipients available, return
            if ($recipients->isEmpty()) {
                Log::warning('No recipients available for message notification', [
                    'case_id' => $case->id,
                    'message_id' => $message->id,
                    'branch_id' => $case->branch_id,
                ]);
                return;
            }

            // Create notification and send email for each recipient
            foreach ($recipients as $recipient) {
                $notificationData = [
                    'branch_id' => $case->branch_id,
                    'case_id' => $case->id,
                    'user_id' => $recipient->id,
                    'notification_type' => 'thread_message',
                    'channel' => 'email',
                    'status' => 'pending',
                    'priority' => 'normal',
                    'subject' => 'New Message in Case ' . $case->case_token,
                    'message_preview' => 'A new message has been posted in case ' . $case->case_token,
                    'payload_json' => [
                        'case_id' => $case->id,
                        'case_number' => $case->case_token,
                        'message_id' => $message->id,
                        'message_preview' => substr($message->message, 0, 200),
                        'sender_type' => $message->sender_type,
                        'sent_at' => $message->created_at->toISOString(),
                    ],
                    'metadata' => [
                        'recipient_type' => $recipient->recipient_type,
                        'involved_parties_count' => count($involvedUserIds),
                        'message_sender' => $message->sender_type,
                    ],
                ];

                $notification = Notification::create($notificationData);

                // Send email notification (queued for async processing)
                try {
                    Mail::to($recipient->email)
                        ->queue(new ThreadMessageNotification($case, $message, $recipient, $recipientType));

                    // Update notification status to sent
                    $notification->update([
                        'status' => 'sent',
                        'sent_at' => now(),
                    ]);

                    Log::info('Thread message notification email queued', [
                        'case_id' => $case->id,
                        'message_id' => $message->id,
                        'recipient_email' => $recipient->email,
                        'recipient_type' => $recipientType,
                    ]);
                } catch (\Exception $emailError) {
                    Log::error('Failed to queue thread message notification email', [
                        'case_id' => $case->id,
                        'message_id' => $message->id,
                        'recipient_email' => $recipient->email,
                        'error' => $emailError->getMessage(),
                    ]);

                    // Update notification status to failed
                    $notification->update([
                        'status' => 'failed',
                        'failed_at' => now(),
                    ]);
                }
            }
        } catch (\Exception $e) {
            // Log error but don't fail the message sending
            Log::error('Failed to send thread message notifications', [
                'case_id' => $case->id,
                'message_id' => $message->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}
