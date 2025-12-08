<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    protected $baseUrl;
    protected $accessToken;
    protected $phoneNumberId;

    public function __construct()
    {
        $this->baseUrl = config('services.whatsapp.base_url');
        $this->accessToken = config('services.whatsapp.access_token');
        $this->phoneNumberId = config('services.whatsapp.phone_number_id');
    }

    public function sendTextMessage(string $to, string $message): array
    {
        if (!$this->accessToken || !$this->phoneNumberId) {
            return [
                'success' => false,
                'error' => 'WhatsApp configuration incomplete. Check WHATSAPP_ACCESS_TOKEN and WHATSAPP_PHONE_NUMBER_ID'
            ];
        }

        try {
            $url = $this->baseUrl . '/' . $this->phoneNumberId . '/messages';

            Log::info('WhatsApp API Request', [
                'url' => $url,
                'to' => $to,
                'phone_number_id' => $this->phoneNumberId
            ]);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Content-Type' => 'application/json'
            ])->post($url, [
                'messaging_product' => 'whatsapp',
                'to' => $to,
                'type' => 'text',
                'text' => [
                    'body' => $message
                ]
            ]);

            $responseData = $response->json();

            Log::info('WhatsApp API Response', [
                'status' => $response->status(),
                'response' => $responseData
            ]);

            if ($response->successful()) {
                $messageStatus = $responseData['messages'][0]['message_status'] ?? 'unknown';

                return [
                    'success' => true,
                    'message_id' => $responseData['messages'][0]['id'] ?? null,
                    'message' => 'WhatsApp message sent successfully',
                    'status' => $messageStatus,
                    'delivery_info' => [
                        'to' => $to,
                        'message_status' => $messageStatus,
                        'timestamp' => now()->toISOString()
                    ]
                ];
            } else {
                Log::error('WhatsApp API error', [
                    'status' => $response->status(),
                    'response' => $responseData
                ]);

                return [
                    'success' => false,
                    'error' => $responseData['error']['message'] ?? 'Unknown error occurred',
                    'error_code' => $responseData['error']['code'] ?? null,
                    'debug_info' => [
                        'url' => $url,
                        'phone_number_id' => $this->phoneNumberId,
                        'status' => $response->status()
                    ]
                ];
            }
        } catch (\Exception $e) {
            Log::error('WhatsApp service exception', [
                'error' => $e->getMessage(),
                'to' => $to
            ]);

            return [
                'success' => false,
                'error' => 'Failed to send WhatsApp message: ' . $e->getMessage()
            ];
        }
    }

    public function sendTemplateMessage(string $to, string $templateName, array $parameters = [], string $languageCode = 'en_US'): array
    {
        if (!$this->accessToken || !$this->phoneNumberId) {
            return [
                'success' => false,
                'error' => 'WhatsApp configuration incomplete. Check WHATSAPP_ACCESS_TOKEN and WHATSAPP_PHONE_NUMBER_ID'
            ];
        }

        try {
            $url = $this->baseUrl . '/' . $this->phoneNumberId . '/messages';

            $templateComponents = [];

            if (!empty($parameters)) {
                $templateComponents[] = [
                    'type' => 'body',
                    'parameters' => array_map(function ($param) {
                        return [
                            'type' => 'text',
                            'text' => (string) $param
                        ];
                    }, $parameters)
                ];
            }

            $payload = [
                'messaging_product' => 'whatsapp',
                'to' => $to,
                'type' => 'template',
                'template' => [
                    'name' => $templateName,
                    'language' => [
                        'code' => $languageCode
                    ],
                    'components' => $templateComponents
                ]
            ];

            Log::info('WhatsApp Template API Request', [
                'url' => $url,
                'to' => $to,
                'template' => $templateName,
                'parameters' => $parameters,
                'payload' => $payload
            ]);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Content-Type' => 'application/json'
            ])->post($url, $payload);

            $responseData = $response->json();

            Log::info('WhatsApp Template API Response', [
                'status' => $response->status(),
                'response' => $responseData
            ]);

            if ($response->successful()) {
                $messageStatus = $responseData['messages'][0]['message_status'] ?? 'unknown';

                return [
                    'success' => true,
                    'message_id' => $responseData['messages'][0]['id'] ?? null,
                    'message' => 'WhatsApp template message sent successfully',
                    'status' => $messageStatus,
                    'template_info' => [
                        'template_name' => $templateName,
                        'language_code' => $languageCode,
                        'parameters' => $parameters,
                        'to' => $to,
                        'timestamp' => now()->toISOString()
                    ]
                ];
            } else {
                Log::error('WhatsApp Template API error', [
                    'status' => $response->status(),
                    'response' => $responseData
                ]);

                return [
                    'success' => false,
                    'error' => $responseData['error']['message'] ?? 'Unknown error occurred',
                    'error_code' => $responseData['error']['code'] ?? null,
                    'debug_info' => [
                        'url' => $url,
                        'template_name' => $templateName,
                        'phone_number_id' => $this->phoneNumberId,
                        'status' => $response->status(),
                        'parameters' => $parameters
                    ]
                ];
            }
        } catch (\Exception $e) {
            Log::error('WhatsApp template service exception', [
                'error' => $e->getMessage(),
                'to' => $to,
                'template' => $templateName,
                'parameters' => $parameters
            ]);

            return [
                'success' => false,
                'error' => 'Failed to send WhatsApp template: ' . $e->getMessage()
            ];
        }
    }

    public function sendAddressUpdateNotification(string $to, string $customerName, string $newAddress, string $contactInfo): array
    {
        return $this->sendTemplateMessage(
            $to,
            'address_update',
            [$customerName, $newAddress, $contactInfo],
            'en_US'
        );
    }
}
