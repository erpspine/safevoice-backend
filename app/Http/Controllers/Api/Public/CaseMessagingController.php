<?php

namespace App\Http\Controllers\Api\Public;

use App\Models\User;
use App\Models\Thread;
use App\Models\CaseFile;
use App\Models\CaseModel;
use App\Models\CaseMessage;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class CaseMessagingController extends Controller
{
    /**
     * Get all messages for a case (with authentication)
     */
    public function getMessages(Request $request, string $caseId): JsonResponse
    {
        // Get authenticated case from middleware
        $case = $request->input('authenticated_case');

        // Get thread_id from query parameter
        $threadId = $request->query('thread_id');

        if (!$threadId) {
            return response()->json([
                'status' => 'error',
                'message' => 'thread_id is required'
            ], 400);
        }

        // Verify thread belongs to this case
        $thread = \App\Models\Thread::where('id', $threadId)
            ->where('case_id', $case->id)
            ->with('participant.investigator')
            ->first();

        if (!$thread) {
            return response()->json([
                'status' => 'error',
                'message' => 'Thread not found or does not belong to this case'
            ], 404);
        }

        // Get messages for this specific thread
        $messages = CaseMessage::where('thread_id', $thread->id)
            ->with(['sender', 'files'])
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'thread_id' => $thread->id,
                'participant' => $thread->participant ? [
                    'id' => $thread->participant->id,
                    'role' => $thread->participant->role,
                    'investigator_id' => $thread->participant->investigator_id,
                    'investigator_name' => $thread->participant->investigator?->name,
                ] : null,
                'messages' => $messages->map(function ($message) {
                    return [
                        'id' => $message->id,
                        'thread_id' => $message->thread_id,
                        'sender_type' => $message->sender_type,
                        'sender_id' => $message->sender_id,
                        'message' => $message->message,
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
                    ];
                }),
            ]
        ]);
    }

    /**
     * Get all attachments for a case
     */
    public function getCaseAttachments(Request $request): JsonResponse
    {
        // Get authenticated case from middleware
        $case = $request->input('authenticated_case');

        // Get the thread for this case
        $thread = Thread::where('case_id', $case->id)->first();

        if (!$thread) {
            return response()->json([
                'status' => 'success',
                'data' => [
                    'case_id' => $case->id,
                    'total_attachments' => 0,
                    'attachments' => []
                ]
            ]);
        }

        // Get all messages with attachments for this thread
        $messagesWithAttachments = CaseMessage::where('thread_id', $thread->id)
            ->where('has_attachments', true)
            ->with('files')
            ->orderBy('created_at', 'desc')
            ->get();

        // Flatten attachments with message context
        $allAttachments = [];
        foreach ($messagesWithAttachments as $message) {
            foreach ($message->files as $file) {
                $allAttachments[] = [
                    'message_id' => $message->id,
                    'file_id' => $file->id,
                    'message_text' => $message->message,
                    'sender_type' => $message->sender_type,
                    'uploaded_at' => $file->created_at,
                    'original_name' => $file->original_name,
                    'file_type' => $file->file_type,
                    'file_size' => $file->file_size,
                    'is_confidential' => $file->is_confidential,
                ];
            }
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'case_id' => $case->id,
                'total_attachments' => count($allAttachments),
                'attachments' => $allAttachments
            ]
        ]);
    }

    /**
     * Send a new message from the case reporter
     */
    public function sendMessage(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'thread_id' => 'required|ulid|exists:threads,id',
            'message' => 'required_without:attachments|string|max:5000',
            'attachments' => 'required_without:message|nullable|array|max:5',
            'attachments.*' => 'file|max:10240|mimes:jpg,jpeg,png,pdf,doc,docx,txt,xlsx,xls'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Get authenticated case from middleware
        $case = $request->input('authenticated_case');

        // Verify thread belongs to this case
        $thread = Thread::where('id', $request->thread_id)
            ->where('case_id', $case->id)
            ->first();

        if (!$thread) {
            return response()->json([
                'status' => 'error',
                'message' => 'Thread not found or does not belong to this case'
            ], 404);
        }

        DB::beginTransaction();
        try {
            // Create the message
            $messageText = $request->input('message', '');
            if (empty($messageText) && $request->hasFile('attachments')) {
                $messageText = 'Attachment' . ($request->file('attachments')->count() > 1 ? 's' : '') . ' sent';
            }

            $message = CaseMessage::create([
                'case_id' => $case->id,
                'thread_id' => $thread->id,
                'sender_id' => null, // Reporter doesn't have a user ID
                'sender_type' => 'reporter',
                'message' => $messageText,
                'has_attachments' => $request->hasFile('attachments'),
            ]);

            // Process file attachments
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $this->storeAttachment($file, $case->id, $message->id);
                }
                // Reload files relationship
                $message->load('files');
            }

            // Update case last activity
            $case->touch();

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Message sent successfully',
                'data' => [
                    'id' => $message->id,
                    'thread_id' => $message->thread_id,
                    'message' => $message->message,
                    'sender_type' => $message->sender_type,
                    'has_attachments' => $message->has_attachments,
                    'created_at' => $message->created_at,
                    'files' => $message->files->map(function ($file) {
                        return [
                            'id' => $file->id,
                            'original_name' => $file->original_name,
                            'file_type' => $file->file_type,
                            'file_size' => $file->file_size,
                        ];
                    }),
                ]
            ], 201);
        } catch (\Exception $e) {
            DB::rollback();

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to send message',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Mark messages as read
     */
    public function markAsRead(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'message_ids' => 'required|array',
            'message_ids.*' => 'ulid|exists:case_messages,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Get authenticated case from middleware
        $case = $request->input('authenticated_case');

        // Get the thread for this case
        $thread = Thread::where('case_id', $case->id)->first();

        if (!$thread) {
            return response()->json([
                'status' => 'error',
                'message' => 'No thread found for this case'
            ], 404);
        }

        // Mark messages as read by creating/updating message read records
        $updated = 0;
        foreach ($request->message_ids as $messageId) {
            $created = \App\Models\MessageRead::firstOrCreate([
                'message_id' => $messageId,
            ]);
            if ($created->wasRecentlyCreated) {
                $updated++;
            }
        }

        // Update thread participant's last read message
        if (!empty($request->message_ids)) {
            $lastMessageId = end($request->message_ids);
            $thread->participant()->update([
                'last_read_message_id' => $lastMessageId,
                'last_read_at' => now(),
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Messages marked as read',
            'data' => [
                'updated_count' => $updated
            ]
        ]);
    }

    /**
     * Get unread message count
     */
    public function getUnreadCount(Request $request): JsonResponse
    {
        // Get authenticated case from middleware
        $case = $request->input('authenticated_case');

        // Get the thread for this case
        $thread = Thread::where('case_id', $case->id)->first();

        if (!$thread || !$thread->participant) {
            return response()->json([
                'status' => 'success',
                'data' => [
                    'unread_count' => 0,
                    'case_id' => $case->id,
                    'case_token' => $case->case_token
                ]
            ]);
        }

        // Calculate unread count based on last read message
        $unreadCount = 0;
        if ($thread->participant->last_read_message_id) {
            $unreadCount = CaseMessage::where('thread_id', $thread->id)
                ->where('id', '>', $thread->participant->last_read_message_id)
                ->where('sender_type', '!=', 'reporter') // Only count messages from investigators
                ->count();
        } else {
            // All messages from investigators are unread
            $unreadCount = CaseMessage::where('thread_id', $thread->id)
                ->where('sender_type', '!=', 'reporter')
                ->count();
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'unread_count' => $unreadCount,
                'case_id' => $case->id,
                'case_token' => $case->case_token
            ]
        ]);
    }

    /**
     * Download attachment
     */
    public function downloadAttachment(Request $request, string $caseId, string $messageId, string $filename)
    {
        // Get authenticated case from middleware
        $case = $request->input('authenticated_case');

        // Find the message
        $message = CaseMessage::where('id', $messageId)
            ->whereHas('thread', function ($query) use ($case) {
                $query->where('case_id', $case->id);
            })
            ->where('has_attachments', true)
            ->with('files')
            ->firstOrFail();

        // Find the attachment file
        $file = $message->files->where('stored_name', $filename)->first();

        if (!$file) {
            abort(404, 'Attachment not found');
        }

        if (!Storage::disk('local')->exists($file->file_path)) {
            abort(404, 'File not found on storage');
        }

        $fullPath = Storage::disk('local')->path($file->file_path);
        return response()->download($fullPath, $file->original_name);
    }



    /**
     * Store file attachment
     */
    private function storeAttachment($file, string $caseId, string $messageId): void
    {
        $originalName = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();
        $mimeType = $file->getMimeType();
        $size = $file->getSize();

        // Generate unique filename
        $filename = Str::ulid() . '.' . $extension;
        $directory = "case-messages/{$caseId}";

        // Store the file
        $path = $file->storeAs($directory, $filename, 'local');

        if (!$path) {
            throw new \Exception('Failed to store attachment');
        }

        // Determine file type category based on mime type
        $fileType = $this->categorizeFileType($mimeType);

        // Create case file record
        CaseFile::create([
            'case_message_id' => $messageId,
            'original_name' => $originalName,
            'stored_name' => $filename,
            'file_path' => $path,
            'mime_type' => $mimeType,
            'file_type' => $fileType,
            'file_size' => $size,
            'is_confidential' => false,
        ]);
    }

    /**
     * Categorize file type based on MIME type
     */
    private function categorizeFileType(string $mimeType): string
    {
        if (str_starts_with($mimeType, 'image/')) {
            return 'image';
        }
        if (str_starts_with($mimeType, 'video/')) {
            return 'video';
        }
        if (str_starts_with($mimeType, 'audio/')) {
            return 'audio';
        }
        if (in_array($mimeType, [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/plain'
        ])) {
            return 'document';
        }

        return 'other';
    }
}
