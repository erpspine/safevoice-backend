<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\CaseMessage;
use App\Models\CaseModel;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class InvestigatorMessagingController extends Controller
{
    /**
     * Get all messages for a case (investigator view - can see internal messages)
     */
    public function getMessages(Request $request, string $caseId): JsonResponse
    {
        $validator = Validator::make(array_merge($request->all(), ['case_id' => $caseId]), [
            'case_id' => 'required|ulid|exists:cases,id',
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:50',
            'visibility' => 'in:public,internal,all'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check if user has access to this case
        $user = Auth::user();
        $case = CaseModel::find($caseId);

        if (!$this->canAccessCase($user, $case)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access to case'
            ], 403);
        }

        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 20);
        $visibility = $request->input('visibility', 'all');

        // Build query based on visibility filter
        $query = CaseMessage::where('case_id', $caseId)
            ->with(['case', 'parentMessage']);

        if ($visibility !== 'all') {
            $query->where('visibility', $visibility);
        }

        $messages = $query->orderBy('created_at', 'asc')
            ->paginate($perPage, ['*'], 'page', $page);

        // Format messages for response
        $formattedMessages = $messages->getCollection()->map(function ($message) {
            return [
                'id' => $message->id,
                'sender_type' => $message->sender_type,
                'sender_name' => $this->getSenderName($message),
                'sender_id' => $message->sender_id,
                'visibility' => $message->visibility,
                'message' => $message->message,
                'message_type' => $message->message_type,
                'priority' => $message->priority,
                'has_attachments' => $message->has_attachments,
                'attachments' => $message->attachments,
                'is_read' => $message->is_read,
                'read_at' => $message->read_at,
                'read_by_user_id' => $message->read_by_user_id,
                'parent_message_id' => $message->parent_message_id,
                'metadata' => $message->metadata,
                'created_at' => $message->created_at,
                'updated_at' => $message->updated_at
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => [
                'messages' => $formattedMessages,
                'pagination' => [
                    'current_page' => $messages->currentPage(),
                    'total_pages' => $messages->lastPage(),
                    'per_page' => $messages->perPage(),
                    'total' => $messages->total(),
                    'has_more_pages' => $messages->hasMorePages()
                ],
                'case_info' => [
                    'case_id' => $case->id,
                    'case_token' => $case->case_token,
                    'status' => $case->status,
                    'assigned_to' => $case->assigned_to
                ]
            ]
        ]);
    }

    /**
     * Send a new message from investigator to case reporter
     */
    public function sendMessage(Request $request, string $caseId): JsonResponse
    {
        $validator = Validator::make(array_merge($request->all(), ['case_id' => $caseId]), [
            'case_id' => 'required|ulid|exists:cases,id',
            'message' => 'required|string|max:5000',
            'visibility' => 'required|in:public,internal',
            'message_type' => 'in:comment,update,notification,status_change,assignment',
            'priority' => 'in:low,normal,high,urgent',
            'parent_message_id' => 'nullable|ulid|exists:case_messages,id',
            'attachments' => 'nullable|array|max:10',
            'attachments.*' => 'file|max:10240|mimes:jpg,jpeg,png,pdf,doc,docx,txt,xlsx,xls'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check if user has access to this case
        $user = Auth::user();
        $case = CaseModel::find($caseId);

        if (!$this->canAccessCase($user, $case)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access to case'
            ], 403);
        }

        DB::beginTransaction();
        try {
            // Process file attachments
            $attachmentData = [];
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $attachment = $this->storeAttachment($file, $case->id);
                    $attachmentData[] = $attachment;
                }
            }

            // Create the message
            $message = CaseMessage::create([
                'case_id' => $case->id,
                'sender_id' => $user->id,
                'sender_type' => 'investigator',
                'visibility' => $request->visibility,
                'message' => $request->message,
                'has_attachments' => !empty($attachmentData),
                'attachments' => $attachmentData,
                'message_type' => $request->input('message_type', 'comment'),
                'priority' => $request->input('priority', 'normal'),
                'parent_message_id' => $request->parent_message_id,
                'metadata' => [
                    'sender_name' => $user->name,
                    'sender_role' => $user->role,
                    'sender_ip' => $request->ip(),
                    'user_agent' => $request->userAgent()
                ]
            ]);

            // Update case last activity and assign if needed
            $case->touch();

            // Auto-assign case to sender if not already assigned
            if (!$case->assigned_to) {
                $case->update(['assigned_to' => $user->id]);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Message sent successfully',
                'data' => [
                    'message' => [
                        'id' => $message->id,
                        'sender_type' => $message->sender_type,
                        'sender_name' => $user->name,
                        'visibility' => $message->visibility,
                        'message' => $message->message,
                        'message_type' => $message->message_type,
                        'priority' => $message->priority,
                        'has_attachments' => $message->has_attachments,
                        'attachments' => $message->attachments,
                        'created_at' => $message->created_at
                    ],
                    'case_updated_at' => $case->updated_at,
                    'case_assigned_to' => $case->assigned_to
                ]
            ], 201);
        } catch (\Exception $e) {
            DB::rollback();

            // Clean up uploaded files if transaction failed
            foreach ($attachmentData as $attachment) {
                if (isset($attachment['path']) && Storage::disk('local')->exists($attachment['path'])) {
                    Storage::disk('local')->delete($attachment['path']);
                }
            }

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to send message',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Mark messages as read by investigator
     */
    public function markAsRead(Request $request, string $caseId): JsonResponse
    {
        $validator = Validator::make(array_merge($request->all(), ['case_id' => $caseId]), [
            'case_id' => 'required|ulid|exists:cases,id',
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

        // Check if user has access to this case
        $user = Auth::user();
        $case = CaseModel::find($caseId);

        if (!$this->canAccessCase($user, $case)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access to case'
            ], 403);
        }

        // Mark messages as read
        $updated = CaseMessage::whereIn('id', $request->message_ids)
            ->where('case_id', $caseId)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
                'read_by_user_id' => $user->id
            ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Messages marked as read',
            'data' => [
                'updated_count' => $updated,
                'read_by' => $user->name
            ]
        ]);
    }

    /**
     * Get message statistics for a case
     */
    public function getMessageStats(Request $request, string $caseId): JsonResponse
    {
        $validator = Validator::make(['case_id' => $caseId], [
            'case_id' => 'required|ulid|exists:cases,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check if user has access to this case
        $user = Auth::user();
        $case = CaseModel::find($caseId);

        if (!$this->canAccessCase($user, $case)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access to case'
            ], 403);
        }

        $stats = [
            'total_messages' => CaseMessage::where('case_id', $caseId)->count(),
            'public_messages' => CaseMessage::where('case_id', $caseId)->where('visibility', 'public')->count(),
            'internal_messages' => CaseMessage::where('case_id', $caseId)->where('visibility', 'internal')->count(),
            'unread_messages' => CaseMessage::where('case_id', $caseId)->where('is_read', false)->count(),
            'messages_with_attachments' => CaseMessage::where('case_id', $caseId)->where('has_attachments', true)->count(),
            'by_sender_type' => CaseMessage::where('case_id', $caseId)
                ->selectRaw('sender_type, count(*) as count')
                ->groupBy('sender_type')
                ->pluck('count', 'sender_type'),
            'by_message_type' => CaseMessage::where('case_id', $caseId)
                ->selectRaw('message_type, count(*) as count')
                ->groupBy('message_type')
                ->pluck('count', 'message_type'),
            'last_message' => CaseMessage::where('case_id', $caseId)
                ->orderBy('created_at', 'desc')
                ->first(['created_at', 'sender_type', 'message_type'])
        ];

        return response()->json([
            'status' => 'success',
            'data' => [
                'case_id' => $caseId,
                'statistics' => $stats
            ]
        ]);
    }

    /**
     * Check if user can access the case
     */
    private function canAccessCase(User $user, CaseModel $case): bool
    {
        // Super admin and admin can access all cases
        if (in_array($user->role, [User::ROLE_SUPER_ADMIN, User::ROLE_ADMIN])) {
            return true;
        }

        // Branch managers can access cases in their branch
        if ($user->role === User::ROLE_BRANCH_MANAGER && $user->branch_id === $case->branch_id) {
            return true;
        }

        // Investigators can access assigned cases or cases in their company
        if ($user->role === User::ROLE_INVESTIGATOR) {
            return $case->assigned_to === $user->id || $user->company_id === $case->company_id;
        }

        return false;
    }

    /**
     * Get sender name based on sender type and ID
     */
    private function getSenderName($message): string
    {
        switch ($message->sender_type) {
            case 'reporter':
                return 'Case Reporter';
            case 'investigator':
                if ($message->sender_id) {
                    $user = User::find($message->sender_id);
                    return $user ? $user->name : 'Investigator';
                }
                return 'Investigator';
            case 'system':
                return 'System';
            default:
                return 'Unknown';
        }
    }

    /**
     * Store file attachment
     */
    private function storeAttachment($file, string $caseId): array
    {
        $originalName = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();
        $mimeType = $file->getMimeType();
        $size = $file->getSize();

        // Generate unique filename
        $filename = Str::ulid() . '.' . $extension;

        // Store the file
        $path = $file->storeAs("case-messages/{$caseId}", $filename, 'local');

        if (!$path) {
            throw new \Exception('Failed to store attachment');
        }

        return [
            'original_name' => $originalName,
            'stored_name' => $filename,
            'path' => $path,
            'mime_type' => $mimeType,
            'size' => $size,
            'uploaded_at' => now()->toISOString()
        ];
    }
}
