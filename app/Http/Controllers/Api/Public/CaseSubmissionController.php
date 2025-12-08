<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Models\CaseModel;
use App\Models\CaseFile;
use App\Models\CaseInvolvedParty;
use App\Models\CaseAdditionalParty;
use App\Models\Company;
use App\Models\Branch;
use App\Models\Department;
use App\Models\IncidentCategory;
use App\Models\User;
use App\Models\Notification;
use App\Services\AutoThreadService;
use Illuminate\Support\Facades\Hash;
use App\Mail\NewCaseNotification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class CaseSubmissionController extends Controller
{
    /**
     * Map priority strings to integers.
     */
    private function mapPriority(string $priority): int
    {
        return match ($priority) {
            'low' => 1,
            'medium' => 2,
            'high' => 3,
            'urgent', 'critical' => 4,
            default => 2
        };
    }



    /**
     * Map integer priority back to string for API responses.
     */
    private function getPriorityString(int $priority): string
    {
        return match ($priority) {
            1 => 'low',
            2 => 'medium',
            3 => 'high',
            4 => 'urgent',
            default => 'medium'
        };
    }



    /**
     * Submit a new case with files and involved parties.
     */
    public function submit(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'company_id' => 'required|string|exists:companies,id',
            'branch_id' => 'nullable|string|exists:branches,id',
            'description' => 'required|string',
            'location_description' => 'nullable|string|max:255',
            'date_time_type' => 'required|string',
            'date_occurred' => 'nullable|date',
            'time_occurred' => 'nullable|string',
            'general_timeframe' => 'nullable|string',
            'company_relationship' => 'required|string',
            'contact_info.name' => 'nullable|string|max:255',
            'contact_info.email' => 'nullable|email|max:255',
            'contact_info.phone' => 'nullable|string|max:20',
            'contact_info.is_anonymous' => 'nullable|boolean',
            'involved_parties' => 'nullable|array',
            'involved_parties.*.employee_id' => 'required|string|max:50',
            'involved_parties.*.nature_of_involvement' => 'required|string',
            'additional_parties' => 'nullable|array',
            'additional_parties.*.name' => 'required|string|max:255',
            'additional_parties.*.email' => 'nullable|email|max:255',
            'additional_parties.*.phone' => 'nullable|string|max:20',
            'additional_parties.*.job_title' => 'nullable|string|max:255',
            'additional_parties.*.role' => 'required|string|max:255',
            'access_id' => 'required|string|unique:cases,access_id',
            'access_password' => 'required|string|min:6',
            'files' => 'nullable|array|max:10',
            'files.*.file' => 'nullable|file|max:10240', // 10MB max
            'files.*.type' => 'nullable',
            'files.*.name' => 'nullable|string|max:500',
            'files.*.is_confidential' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Get access credentials from input
            $accessId = $request->access_id;
            $accessPassword = $request->access_password;

            // Prepare contact information
            $contactInfo = $request->input('contact_info', []);
            $isAnonymous = $contactInfo['is_anonymous'] ?? false;

            // Generate session token for case tracking (valid for 24 hours)
            $sessionToken = Str::random(64);
            $sessionExpiresAt = now()->addHours(24);

            // Create case
            $case = CaseModel::create([
                'company_id' => $request->company_id,
                'branch_id' => $request->branch_id,
                'type' => 'incident',
                'description' => $request->description,
                'location_description' => $request->location_description,
                'date_time_type' => $request->date_time_type,
                'date_occurred' => $request->date_occurred,
                'time_occurred' => $request->time_occurred,
                'general_timeframe' => $request->general_timeframe,
                'company_relationship' => $request->company_relationship,
                'status' => 'open',
                'source' => 'web_submission',
                'created_by_type' => $isAnonymous ? 'anonymous' : 'identified',
                'created_by_contact_json' => $isAnonymous ? null : json_encode($contactInfo),
                'case_token' => $accessId,
                'access_id' => $accessId,
                'access_password' => bcrypt($accessPassword),
                'session_token' => Hash::make($sessionToken),
                'session_expires_at' => $sessionExpiresAt,
                'follow_up_required' => !$isAnonymous,
                'is_anonymous' => $isAnonymous
            ]);

            // Handle involved parties (simplified structure)
            if ($request->has('involved_parties')) {
                foreach ($request->involved_parties as $partyData) {
                    CaseInvolvedParty::create([
                        'case_id' => $case->id,
                        'employee_id' => $partyData['employee_id'],
                        'nature_of_involvement' => $partyData['nature_of_involvement'],
                    ]);
                }
            }

            // Handle additional parties
            if ($request->has('additional_parties')) {
                foreach ($request->additional_parties as $partyData) {
                    CaseAdditionalParty::create([
                        'case_id' => $case->id,
                        'name' => $partyData['name'],
                        'email' => $partyData['email'] ?? null,
                        'phone' => $partyData['phone'] ?? null,
                        'job_title' => $partyData['job_title'] ?? null,
                        'role' => $partyData['role'],
                    ]);
                }
            }

            // Handle file uploads (optional)
            $uploadedFiles = [];
            if ($request->hasFile('files')) {
                foreach ($request->file('files') as $index => $fileData) {
                    if (isset($fileData['file']) && $fileData['file']) {
                        $file = $fileData['file'];
                        $fileType = $request->input("files.{$index}.type", 'document');
                        $name = $request->input("files.{$index}.name");
                        $isConfidential = $request->boolean("files.{$index}.is_confidential");

                        // Generate unique filename
                        $originalName = $file->getClientOriginalName();
                        $extension = $file->getClientOriginalExtension();
                        $storedName = $case->id . '_' . time() . '_' . Str::random(8) . '.' . $extension;

                        // Store file
                        $filePath = $file->storeAs('case_files/' . $case->id, $storedName, 'public');

                        // Create file record
                        $caseFile = CaseFile::create([
                            'case_id' => $case->id,
                            'original_name' => $originalName,
                            'stored_name' => $storedName,
                            'file_path' => $filePath,
                            'mime_type' => $file->getMimeType(),
                            'file_size' => $file->getSize(),
                            'file_type' => $fileType,
                            'description' => $name,
                            'uploaded_by_type' => 'user',
                            'is_confidential' => $isConfidential,
                            'processing_status' => 'completed'
                        ]);

                        $uploadedFiles[] = [
                            'id' => $caseFile->id,
                            'name' => $originalName,
                            'type' => $fileType,
                            'size' => $caseFile->formatted_file_size
                        ];
                    }
                }
            }

            // Auto-create initial thread for the case
            try {
                AutoThreadService::createInitialThread($case);
            } catch (\Exception $e) {
                Log::error('Failed to auto-create thread for case', [
                    'case_id' => $case->id,
                    'case_token' => $case->case_token,
                    'error' => $e->getMessage()
                ]);
                // Don't fail the entire case creation for thread creation issues
            }

            DB::commit();

            // Send notifications after successful case creation (outside transaction)
            try {
                $this->sendCaseNotifications($case, $request);
            } catch (\Exception $e) {
                Log::error('Failed to send case notifications', [
                    'case_id' => $case->id,
                    'case_token' => $case->case_token,
                    'error' => $e->getMessage()
                ]);
                // Don't fail the response for notification issues
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Case submitted successfully',
                'data' => [
                    'case_id' => $case->id,
                    'case_number' => $case->case_token,
                    'access_id' => $accessId,
                    'status' => $case->status,
                    'submitted_at' => $case->created_at,
                    'files_uploaded' => count($uploadedFiles),
                    'tracking_info' => [
                        'message' => 'Save your access credentials to track case progress',
                        'access_id' => $accessId,
                        'access_token' => $sessionToken,
                        'token_expires_at' => $sessionExpiresAt,
                        'note' => 'You will need these credentials to check your case status'
                    ]
                ]
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to submit case',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Track case progress using access credentials.
     */
    public function track(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'access_id' => 'required|string',
            'access_password' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $case = CaseModel::where('access_id', $request->access_id)->first();

        if (!$case || !password_verify($request->access_password, $case->access_password)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid access credentials'
            ], 401);
        }

        // Load relationships
        $case->load([
            'company:id,name',
            'branch:id,name',
            'incidentCategory:id,name',
            'files:id,case_id,original_name,file_type,file_size,is_confidential,created_at',
            'involvedParties:id,case_id,employee_id,nature_of_involvement',
            'involvedParties.user:id,employee_id,name,email,phone',
            'additionalParties:id,case_id,name,email,phone,job_title,role'
        ]);

        return response()->json([
            'status' => 'success',
            'data' => [
                'case_id' => $case->id,
                'case_number' => $case->case_token,
                'description' => $case->description,
                'status' => $case->status,
                'location_description' => $case->location_description,
                'date_time_type' => $case->date_time_type,
                'date_occurred' => $case->date_occurred,
                'time_occurred' => $case->time_occurred,
                'general_timeframe' => $case->general_timeframe,
                'company_relationship' => $case->company_relationship,
                'submitted_at' => $case->created_at,
                'last_updated' => $case->updated_at,
                'company' => $case->company,
                'branch' => $case->branch,
                'category' => $case->incidentCategory,
                'files_count' => $case->files->count(),
                'parties_count' => $case->involvedParties->count(),
                'additional_parties_count' => $case->additionalParties->count(),
                'is_anonymous' => $case->is_anonymous,
                'follow_up_required' => $case->follow_up_required,
                'resolution_note' => $case->resolution_note,
                'resolved_at' => $case->resolved_at,
                'timeline' => [
                    'submitted' => $case->created_at,
                    'last_update' => $case->updated_at,
                    'resolved' => $case->resolved_at
                ]
            ]
        ]);
    }

    /**
     * Send notifications to appropriate recipients when a case is submitted.
     * Logic:
     * 1. Get all primary recipients for the branch
     * 2. Exclude involved parties from recipients
     * 3. If all primary recipients are involved, send to alternative recipients
     */
    private function sendCaseNotifications(CaseModel $case, Request $request): void
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
                Log::warning('No recipients available for case notification', [
                    'case_id' => $case->id,
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
                    'notification_type' => 'case_created',
                    'channel' => 'email', // Can be extended to support multiple channels
                    'status' => 'pending',
                    'priority' => 'normal',
                    'subject' => 'New Case Submitted - ' . $case->case_token,
                    'message_preview' => 'A new case has been submitted and requires your attention.',
                    'payload_json' => [
                        'case_id' => $case->id,
                        'case_number' => $case->case_token,
                        'case_type' => $case->type,
                        'description' => substr($case->description, 0, 200),
                        'status' => $case->status,
                        'submitted_at' => $case->created_at->toISOString(),
                        'is_anonymous' => $case->is_anonymous,
                    ],
                    'metadata' => [
                        'recipient_type' => $recipient->recipient_type,
                        'involved_parties_count' => count($involvedUserIds),
                    ],
                ];

                $notification = Notification::create($notificationData);

                // Send email notification (queued for async processing)
                try {
                    Mail::to($recipient->email)
                        ->queue(new NewCaseNotification($case, $recipient, $recipientType));

                    // Update notification status to sent
                    $notification->update([
                        'status' => 'sent',
                        'sent_at' => now(),
                    ]);

                    Log::info('Case notification email queued', [
                        'case_id' => $case->id,
                        'recipient_email' => $recipient->email,
                        'recipient_type' => $recipientType,
                    ]);
                } catch (\Exception $emailError) {
                    Log::error('Failed to queue case notification email', [
                        'case_id' => $case->id,
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
            // Log error but don't fail the case submission
            Log::error('Failed to send case notifications', [
                'case_id' => $case->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}
