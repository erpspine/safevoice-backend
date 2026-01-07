<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\SmsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendInvitationSms implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $userId;
    protected $invitationUrl;
    protected $isAdminUser;

    /**
     * Create a new job instance.
     */
    public function __construct(string $userId, string $invitationUrl, bool $isAdminUser)
    {
        $this->userId = $userId;
        $this->invitationUrl = $invitationUrl;
        $this->isAdminUser = $isAdminUser;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Fetch user by ID
            $user = User::with('company')->find($this->userId);

            if (!$user) {
                Log::error('User not found for SMS invitation', ['user_id' => $this->userId]);
                return;
            }

            // Skip if no phone number
            if (!$user->phone_number) {
                Log::info('No phone number for SMS invitation', ['user_id' => $this->userId]);
                return;
            }

            $smsService = new SmsService();

            // Determine company name
            $companyName = $user->company ? $user->company->name : 'SafeVoice';

            // Send SMS with the invitation URL
            $result = $smsService->sendInvitation(
                $user->phone_number,
                $user->name,
                $this->invitationUrl,
                $companyName
            );

            // Log the result
            if ($result['success']) {
                Log::info('Invitation SMS sent successfully', [
                    'user_id' => $user->id,
                    'phone_number' => $user->phone_number,
                    'reference' => $result['data']['reference'] ?? null
                ]);
            } else {
                Log::error('Failed to send invitation SMS', [
                    'user_id' => $user->id,
                    'phone_number' => $user->phone_number,
                    'error' => $result['message']
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Exception in SendInvitationSms job', [
                'user_id' => $this->userId,
                'error' => $e->getMessage()
            ]);
            throw $e; // Re-throw to mark job as failed
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('SendInvitationSms job failed', [
            'user_id' => $this->userId,
            'error' => $exception->getMessage()
        ]);
    }
}
