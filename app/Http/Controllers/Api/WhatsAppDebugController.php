<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppDebugController extends Controller
{
    public function checkConfig()
    {
        return response()->json([
            'base_url' => config('services.whatsapp.base_url'),
            'phone_number_id' => config('services.whatsapp.phone_number_id'),
            'business_account_id' => config('services.whatsapp.business_account_id'),
            'has_access_token' => !empty(config('services.whatsapp.access_token')),
            'token_preview' => substr(config('services.whatsapp.access_token'), 0, 20) . '...',
            'verify_token' => config('services.whatsapp.verify_token')
        ]);
    }

    public function checkPhoneNumber(Request $request)
    {
        $phoneNumber = $request->input('phone', '255759383748');

        $accessToken = config('services.whatsapp.access_token');
        $businessAccountId = config('services.whatsapp.business_account_id');

        // Check if phone number is registered on WhatsApp
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $accessToken
        ])->get("https://graph.facebook.com/v22.0/{$businessAccountId}/phone_numbers");

        $phoneNumbers = $response->json();

        // Also check phone number format
        $formatted = preg_replace('/[^0-9]/', '', $phoneNumber);
        if (strlen($formatted) === 10) {
            $formatted = '255' . substr($formatted, 1); // Tanzania country code
        }

        return response()->json([
            'original_phone' => $phoneNumber,
            'formatted_phone' => $formatted,
            'business_phone_numbers' => $phoneNumbers,
            'api_response_status' => $response->status()
        ]);
    }

    public function checkDeliveryStatus(Request $request)
    {
        $messageId = $request->input('message_id');

        if (!$messageId) {
            return response()->json([
                'error' => 'message_id parameter is required'
            ], 400);
        }

        $accessToken = config('services.whatsapp.access_token');

        // Check message status
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $accessToken
        ])->get("https://graph.facebook.com/v22.0/{$messageId}");

        return response()->json([
            'message_id' => $messageId,
            'status_response' => $response->json(),
            'api_status' => $response->status()
        ]);
    }

    public function deliveryTroubleshooting()
    {
        return response()->json([
            'common_delivery_issues' => [
                '1. Phone number not on WhatsApp' => 'The recipient phone number must have WhatsApp installed and active',
                '2. Wrong phone number format' => 'Use international format with country code (e.g., 255759383748)',
                '3. Rate limiting' => 'WhatsApp has sending limits - check if you exceeded them',
                '4. Account restrictions' => 'New WhatsApp Business accounts have limited capabilities',
                '5. Template required' => 'For first message to users, you might need approved message templates',
                '6. 24-hour window' => 'Free-form messages only work within 24h of user interaction'
            ],
            'solutions' => [
                'Use approved message templates for notifications',
                'Ensure phone numbers are in correct international format',
                'Check WhatsApp Business account status and limits',
                'Verify recipient has WhatsApp and accepts business messages'
            ],
            'test_recommendations' => [
                '1. Test with your own WhatsApp number first',
                '2. Use message templates instead of free-form text',
                '3. Check delivery status with message ID',
                '4. Enable webhooks to receive delivery receipts'
            ]
        ]);
    }
}
