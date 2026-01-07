<?php

namespace App\Jobs;

use App\Models\User;
use App\Mail\UserInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendInvitationEmail implements ShouldQueue
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
            $user = User::find($this->userId);

            if (!$user) {
                Log::error('User not found for email invitation', ['user_id' => $this->userId]);
                return;
            }

            Mail::to($user->email)->send(
                new UserInvitation($user, $this->invitationUrl, $this->isAdminUser)
            );

            Log::info('Invitation email sent successfully', [
                'user_id' => $user->id,
                'email' => $user->email
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send invitation email', [
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
        Log::error('SendInvitationEmail job failed', [
            'user_id' => $this->userId,
            'error' => $exception->getMessage()
        ]);
    }
}
