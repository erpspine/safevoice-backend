<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class WhatsAppController extends Controller
{
    protected $whatsAppService;

    public function __construct(WhatsAppService $whatsAppService)
    {
        $this->whatsAppService = $whatsAppService;
    }

    public function testMessage(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone_number' => 'required|string',
            'message' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $phoneNumber = $this->formatPhoneNumber($request->phone_number);
        $result = $this->whatsAppService->sendTextMessage($phoneNumber, $request->message);

        return response()->json($result);
    }

    public function sendCaseNotification(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone_number' => 'required|string',
            'case_id' => 'required|string',
            'notification_type' => 'required|in:new_case,case_assigned,case_updated,case_closed',
            'additional_message' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $phoneNumber = $this->formatPhoneNumber($request->phone_number);
        $message = $this->buildNotificationMessage($request->case_id, $request->notification_type, $request->additional_message);
        $result = $this->whatsAppService->sendTextMessage($phoneNumber, $message);

        return response()->json($result);
    }

    private function formatPhoneNumber($phoneNumber)
    {
        $phoneNumber = preg_replace('/[^0-9]/', '', $phoneNumber);
        if (strlen($phoneNumber) === 10) {
            $phoneNumber = '1' . $phoneNumber;
        }
        return $phoneNumber;
    }

    private function buildNotificationMessage($caseId, $type, $additional = null)
    {
        $messages = [
            'new_case' => "New Case Alert\nCase ID: {$caseId}\nA new case has been created.",
            'case_assigned' => "Case Assignment\nCase ID: {$caseId}\nThis case has been assigned to you.",
            'case_updated' => "Case Update\nCase ID: {$caseId}\nThere has been an update to this case.",
            'case_closed' => "Case Closed\nCase ID: {$caseId}\nThis case has been resolved."
        ];

        $message = $messages[$type] ?? "Case Notification\nCase ID: {$caseId}";

        if ($additional) {
            $message .= "\n\nAdditional Info:\n" . $additional;
        }

        return $message . "\n\n---\nSafeVoice System";
    }

    public function sendTemplateMessage(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone_number' => 'required|string',
            'template_name' => 'required|string',
            'parameters' => 'nullable|array',
            'language_code' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $phoneNumber = $this->formatPhoneNumber($request->phone_number);
        $templateName = $request->template_name;
        $parameters = $request->parameters ?? [];
        $languageCode = $request->language_code ?? 'en_US';

        $result = $this->whatsAppService->sendTemplateMessage(
            $phoneNumber,
            $templateName,
            $parameters,
            $languageCode
        );

        return response()->json($result);
    }

    public function sendAddressUpdate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone_number' => 'required|string',
            'customer_name' => 'required|string',
            'new_address' => 'required|string',
            'contact_info' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $phoneNumber = $this->formatPhoneNumber($request->phone_number);

        $result = $this->whatsAppService->sendAddressUpdateNotification(
            $phoneNumber,
            $request->customer_name,
            $request->new_address,
            $request->contact_info
        );

        return response()->json($result);
    }
}
