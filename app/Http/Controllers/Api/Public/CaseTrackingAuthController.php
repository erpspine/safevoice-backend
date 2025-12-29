<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Models\CaseModel;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CaseTrackingAuthController extends Controller
{
    /**
     * Login using access credentials to get a temporary session token for case tracking.
     */
    public function login(Request $request): JsonResponse
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

        // Find case by access_id
        $case = CaseModel::where('access_id', $request->access_id)->first();

        if (!$case || !password_verify($request->access_password, $case->access_password)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid access credentials'
            ], 401);
        }

        // Generate a temporary session token (valid for 24 hours)
        $sessionToken = Str::random(64);
        $expiresAt = now()->addHours(24);

        // Store session token in case record (you might want to create a separate sessions table)
        $case->update([
            'session_token' => Hash::make($sessionToken),
            'session_expires_at' => $expiresAt
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Login successful',
            'data' => [
                'access_token' => $sessionToken,
                'token_type' => 'Bearer',
                'expires_in' => 86400, // 24 hours in seconds
                'expires_at' => $expiresAt,
                'case_info' => [
                    'case_id' => $case->id,
                    'case_number' => $case->case_token,
                    'status' => $case->status,
                    'submitted_at' => $case->created_at
                ]
            ]
        ], 200);
    }

    /**
     * Get case details using session token.
     */
    public function getCaseDetails(Request $request): JsonResponse
    {
        // Get the authenticated case from the middleware
        $case = $request->input('authenticated_case');

        if (!$case) {
            return response()->json([
                'status' => 'error',
                'message' => 'No authenticated case found'
            ], 401);
        }

        // Load relationships based on case type
        $relationships = [
            'company:id,name',
            'branch:id,name',
            'files:id,case_id,original_name,file_type,file_size,is_confidential,created_at',
            'involvedParties:id,case_id,employee_id,nature_of_involvement',
            'involvedParties.user:id,employee_id,name,email,phone',
            'additionalParties:id,case_id,name,email,phone,job_title,role'
        ];

        // Load case categories instead of direct category relationships
        $relationships[] = 'caseCategories.incidentCategory:id,name';
        $relationships[] = 'caseCategories.feedbackCategory:id,name';

        $case->load($relationships);

        // Get category data from case categories
        $categoryData = null;
        if ($case->caseCategories->count() > 0) {
            $categoryData = $case->caseCategories->map(function ($caseCategory) {
                $category = $caseCategory->category_type === 'incident'
                    ? $caseCategory->incidentCategory
                    : $caseCategory->feedbackCategory;
                return [
                    'id' => $category->id ?? null,
                    'name' => $category->name ?? null,
                    'type' => $caseCategory->category_type
                ];
            })->filter(function ($category) {
                return !is_null($category['id']);
            })->values()->toArray();
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'case_id' => $case->id,
                'case_number' => $case->case_token,
                'title' => $case->title,
                'description' => $case->description,
                'status' => $case->status,
                'priority' => $case->priority ? $this->getPriorityString($case->priority) : null,
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
                'category' => $categoryData,
                'parties_count' => $case->involvedParties->count(),
                'additional_parties_count' => $case->additionalParties->count(),
                'is_anonymous' => $case->is_anonymous,
                'follow_up_required' => $case->follow_up_required,
                'resolution_note' => $case->resolution_note,
                'resolved_at' => $case->resolved_at,
                'involved_parties' => $case->involvedParties->map(function ($party) {
                    return [
                        'employee_id' => $party->employee_id,
                        'nature_of_involvement' => $party->nature_of_involvement,
                        'user_info' => $party->user ? [
                            'name' => $party->user->name,
                            'email' => $party->user->email
                        ] : null
                    ];
                }),
                'additional_parties' => $case->additionalParties->map(function ($party) {
                    return [
                        'name' => $party->name,
                        'email' => $party->email,
                        'phone' => $party->phone,
                        'job_title' => $party->job_title,
                        'role' => $party->role
                    ];
                }),
                'files' => $case->files->map(function ($file) use ($case, $request) {
                    // Get session token for download URL
                    $sessionToken = $request->header('X-Session-Token')
                        ?? $request->input('session_token')
                        ?? $request->bearerToken();

                    $downloadUrl = route('public.cases.files.download', [
                        'caseId' => $case->id,
                        'fileId' => $file->id
                    ]);

                    // Add session token as query parameter
                    if ($sessionToken) {
                        $downloadUrl .= '?session_token=' . urlencode($sessionToken);
                    }

                    return [
                        'id' => $file->id,
                        'original_name' => $file->original_name,
                        'file_type' => $file->file_type,
                        'file_size' => $file->file_size,
                        'is_confidential' => $file->is_confidential,
                        'uploaded_at' => $file->created_at,
                        'download_url' => $downloadUrl
                    ];
                }),
                'files_count' => $case->files->count(),
                'timeline' => [
                    'submitted' => $case->created_at,
                    'last_update' => $case->updated_at,
                    'resolved' => $case->resolved_at
                ]
            ]
        ]);
    }

    /**
     * Logout and invalidate session token.
     */
    public function logout(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'access_token' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Find and invalidate session token
        $case = CaseModel::whereNotNull('session_token')
            ->where('session_expires_at', '>', now())
            ->get()
            ->filter(function ($case) use ($request) {
                return Hash::check($request->access_token, $case->session_token);
            })
            ->first();

        if ($case) {
            $case->update([
                'session_token' => null,
                'session_expires_at' => null
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Logged out successfully'
        ], 200);
    }

    /**
     * Download a case file.
     */
    public function downloadFile(Request $request, string $caseId, string $fileId): \Symfony\Component\HttpFoundation\BinaryFileResponse|\Illuminate\Http\JsonResponse
    {
        try {
            // Get the authenticated case from the middleware
            $case = $request->input('authenticated_case');

            if (!$case || $case->id !== $caseId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized access to case file'
                ], 403);
            }

            // Find the file
            $file = \App\Models\CaseFile::where('id', $fileId)
                ->where('case_id', $caseId)
                ->first();

            if (!$file) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'File not found'
                ], 404);
            }

            // Check if file exists on storage
            if (!\Illuminate\Support\Facades\Storage::disk('public')->exists($file->file_path)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'File not found on storage'
                ], 404);
            }

            // Get the full path
            $fullPath = \Illuminate\Support\Facades\Storage::disk('public')->path($file->file_path);

            // Return file download response
            return response()->download($fullPath, $file->original_name);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to download file',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
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
}
