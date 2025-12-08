<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SmsService
{
    protected $client;
    protected $config;

    public function __construct()
    {
        $this->client = new Client();
        $this->config = config('sms.drivers.' . config('sms.default'));
    }

    /**
     * Send SMS to a single phone number
     *
     * @param string $phoneNumber
     * @param string $message
     * @param string|null $reference
     * @return array
     */
    public function sendSingle(string $phoneNumber, string $message, string $reference = null): array
    {
        // Format phone number for Tanzania (add 255 country code if not present)
        $formattedNumber = $this->formatPhoneNumber($phoneNumber);


        return $this->sendMultiple([$formattedNumber], $message, $reference);
    }

    /**
     * Send SMS to multiple phone numbers
     *
     * @param array $phoneNumbers
     * @param string $message
     * @param string|null $reference
     * @return array
     */
    public function sendMultiple(array $phoneNumbers, string $message, string $reference = null): array
    {
        if (!config('sms.enabled')) {
            return [
                'success' => false,
                'message' => 'SMS service is disabled',
                'data' => null
            ];
        }

        // Format all phone numbers
        $formattedNumbers = array_map([$this, 'formatPhoneNumber'], $phoneNumbers);

        // Generate reference if not provided
        if (!$reference) {
            $reference = 'SMS_' . Str::random(10) . '_' . time();
        }

        $payload = [
            'from' => $this->config['from'],
            'to' => $formattedNumbers,
            'text' => $message,
            'reference' => $reference
        ];



        try {
            $response = $this->client->post($this->config['endpoint'], [
                'headers' => [
                    'Authorization' => 'Basic ' . $this->config['auth_header'],
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                'json' => $payload,
                'timeout' => $this->config['timeout'],
            ]);



            $responseBody = $response->getBody()->getContents();
            $responseData = json_decode($responseBody, true);

            if (config('sms.log_requests')) {
                Log::info('SMS sent successfully', [
                    'phone_numbers' => $formattedNumbers,
                    'message_length' => strlen($message),
                    'reference' => $reference,
                    'response' => $responseData
                ]);
            }

            return [
                'success' => true,
                'message' => 'SMS sent successfully',
                'data' => [
                    'reference' => $reference,
                    'phone_numbers' => $formattedNumbers,
                    'response' => $responseData,
                    'status_code' => $response->getStatusCode()
                ]
            ];
        } catch (RequestException $e) {
            $errorMessage = $e->getMessage();
            $response = $e->getResponse();
            $statusCode = $response ? $response->getStatusCode() : null;
            $responseBody = $response ? $response->getBody()->getContents() : null;

            Log::error('SMS sending failed', [
                'phone_numbers' => $formattedNumbers,
                'message_length' => strlen($message),
                'reference' => $reference,
                'error' => $errorMessage,
                'status_code' => $statusCode,
                'response_body' => $responseBody
            ]);

            return [
                'success' => false,
                'message' => 'Failed to send SMS: ' . $errorMessage,
                'data' => [
                    'reference' => $reference,
                    'phone_numbers' => $formattedNumbers,
                    'error' => $errorMessage,
                    'status_code' => $statusCode,
                    'response_body' => $responseBody
                ]
            ];
        }
    }

    /**
     * Send invitation SMS to user
     *
     * @param string $phoneNumber
     * @param string $userName
     * @param string $invitationLink
     * @param string $companyName
     * @return array
     */
    public function sendInvitation(string $phoneNumber, string $userName, string $invitationLink, string $companyName): array
    {
        $message = "Hello {$userName}! You've been invited to join {$companyName} on SafeVoice. Complete your registration: {$invitationLink}";

        return $this->sendSingle($phoneNumber, $message, 'INVITE_' . Str::random(8));
    }

    /**
     * Send password reset SMS
     *
     * @param string $phoneNumber
     * @param string $resetCode
     * @return array
     */
    public function sendPasswordReset(string $phoneNumber, string $resetCode): array
    {
        $message = "Your SafeVoice password reset code is: {$resetCode}. This code will expire in 15 minutes.";

        return $this->sendSingle($phoneNumber, $message, 'RESET_' . Str::random(8));
    }

    /**
     * Send verification SMS
     *
     * @param string $phoneNumber
     * @param string $verificationCode
     * @return array
     */
    public function sendVerification(string $phoneNumber, string $verificationCode): array
    {
        $message = "Your SafeVoice verification code is: {$verificationCode}. Enter this code to verify your phone number.";

        return $this->sendSingle($phoneNumber, $message, 'VERIFY_' . Str::random(8));
    }

    /**
     * Format phone number for Tanzania
     * Converts formats like 0760299974 or 760299974 to 255760299974
     *
     * @param string $phoneNumber
     * @return string
     */
    protected function formatPhoneNumber(string $phoneNumber): string
    {
        // Remove any spaces, dashes, or special characters
        $cleaned = preg_replace('/[^0-9]/', '', $phoneNumber);

        // If it starts with 0, remove the 0 and add 255
        if (str_starts_with($cleaned, '0')) {
            return '255' . substr($cleaned, 1);
        }

        // If it starts with 255, keep as is
        if (str_starts_with($cleaned, '255')) {
            return $cleaned;
        }

        // If it's 9 digits (like 760299974), add 255
        if (strlen($cleaned) === 9) {
            return '255' . $cleaned;
        }

        // Return as is if format is unclear
        return $cleaned;
    }

    /**
     * Validate phone number format
     *
     * @param string $phoneNumber
     * @return bool
     */
    public function isValidPhoneNumber(string $phoneNumber): bool
    {
        $formatted = $this->formatPhoneNumber($phoneNumber);

        // Tanzania numbers should be 12 digits starting with 255
        return preg_match('/^255[67]\d{8}$/', $formatted);
    }

    /**
     * Get SMS service status
     *
     * @return array
     */
    public function getStatus(): array
    {
        return [
            'enabled' => config('sms.enabled'),
            'driver' => config('sms.default'),
            'endpoint' => $this->config['endpoint'],
            'from' => $this->config['from'],
            'timeout' => $this->config['timeout'],
            'log_requests' => config('sms.log_requests'),
        ];
    }
}
