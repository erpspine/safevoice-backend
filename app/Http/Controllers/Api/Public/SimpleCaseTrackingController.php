<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Models\CaseModel;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class SimpleCaseTrackingController extends Controller
{
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

        try {
            $case = CaseModel::where('access_id', $request->access_id)->first();

            if (!$case || !password_verify($request->access_password, $case->access_password)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid access credentials'
                ], 401);
            }

            return response()->json([
                'status' => 'success',
                'data' => [
                    'case_id' => $case->id,
                    'case_number' => $case->case_token,
                    'title' => $case->title,
                    'status' => $case->status,
                    'priority' => $case->priority,
                    'submitted_at' => $case->created_at,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Server error: ' . $e->getMessage()
            ], 500);
        }
    }
}
