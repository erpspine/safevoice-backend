<?php

namespace App\Services;

use App\Models\Thread;
use App\Models\ThreadParticipant;
use App\Models\CaseMessage;
use App\Models\MessageRead;
use App\Models\CaseModel;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\ThreadCreatedNotification;

class AutoThreadService
{
    /**
     * Automatically create initial thread when a case is reported.
     */
    public static function createInitialThread(CaseModel $case): ?Thread
    {
        try {
            DB::beginTransaction();

            // Create main thread for the case
            $thread = Thread::create([
                'case_id' => $case->id,
                'title' => 'Case Discussion - ' . $case->case_token,
                'description' => 'Main discussion thread for case ' . $case->case_token,
                'status' => 'active',
                'created_by' => null, // System created
                'created_by_type' => 'system'
            ]);

            // Get participants based on case type and company structure
            $participants = self::getInitialParticipants($case);

            // Add participants to thread
            foreach ($participants as $participant) {
                ThreadParticipant::create([
                    'thread_id' => $thread->id,
                    'user_id' => $participant['user_id'],
                    'role' => $participant['role'],
                    'joined_at' => now()
                ]);
            }

            // Create initial system message
            $systemMessage = CaseMessage::create([
                'case_id' => $case->id,
                'thread_id' => $thread->id,
                'sender_id' => null,
                'sender_type' => 'system',
                'message' => self::generateInitialMessage($case),
                'has_attachments' => false
            ]);

            // Mark system message as read for all participants (since it's just informational)
            foreach ($participants as $participant) {
                MessageRead::create([
                    'message_id' => $systemMessage->id,
                    'user_id' => $participant['user_id'],
                    'read_at' => now()
                ]);
            }

            DB::commit();

            Log::info('Auto-created thread for case', [
                'case_id' => $case->id,
                'case_token' => $case->case_token,
                'thread_id' => $thread->id,
                'participants_count' => count($participants)
            ]);

            // Send email notifications to all participants about the auto-created thread
            try {
                $case->load(['company', 'branch']);

                foreach ($participants as $participant) {
                    $user = User::find($participant['user_id']);
                    if ($user) {
                        // For auto-created threads, we use system as creator (null user)
                        Mail::to($user->email)->queue(new ThreadCreatedNotification(
                            $case,
                            $thread,
                            $user,
                            new User(['name' => 'System', 'email' => 'system@safevoice.tz']) // System as creator
                        ));

                        Log::info('Auto-thread creation notification sent', [
                            'thread_id' => $thread->id,
                            'case_id' => $case->id,
                            'recipient_email' => $user->email
                        ]);
                    }
                }
            } catch (\Exception $e) {
                Log::error('Failed to send auto-thread creation notifications', [
                    'thread_id' => $thread->id,
                    'case_id' => $case->id,
                    'error' => $e->getMessage()
                ]);
                // Don't fail the thread creation for notification issues
            }

            return $thread;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to auto-create thread for case', [
                'case_id' => $case->id,
                'case_token' => $case->case_token,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return null;
        }
    }

    /**
     * Get initial participants for a case thread based on case type and company structure.
     */
    private static function getInitialParticipants(CaseModel $case): array
    {
        $participants = [];

        try {
            // Get IDs of implicated parties to exclude them from thread participants
            $implicatedUserIds = $case->involvedParties()
                ->pluck('employee_id') // employee_id actually contains user_id
                ->filter() // Remove null values
                ->toArray();

            // Add company admins who are not implicated
            $companyAdmins = User::where('company_id', $case->company_id)
                ->where('role', 'company_admin')
                ->where('status', 'active')
                ->whereNotIn('id', $implicatedUserIds) // Exclude implicated parties
                ->get();

            foreach ($companyAdmins as $admin) {
                $participants[] = [
                    'user_id' => $admin->id,
                    'role' => 'company_admin'
                ];
            }

            // Add branch admins if case is associated with a branch (exclude implicated ones)
            if ($case->branch_id) {
                $branchAdmins = User::where('company_id', $case->company_id)
                    ->where('branch_id', $case->branch_id)
                    ->where('role', 'branch_admin')
                    ->where('status', 'active')
                    ->whereNotIn('id', $implicatedUserIds) // Exclude implicated parties
                    ->get();

                foreach ($branchAdmins as $branchAdmin) {
                    $participants[] = [
                        'user_id' => $branchAdmin->id,
                        'role' => 'branch_admin'
                    ];
                }
            }

            // Add assigned investigator if case is already assigned (and not implicated)
            if ($case->assigned_to && !in_array($case->assigned_to, $implicatedUserIds)) {
                $investigator = User::find($case->assigned_to);
                if ($investigator && $investigator->status === 'active') {
                    $participants[] = [
                        'user_id' => $investigator->id,
                        'role' => 'investigator'
                    ];
                }
            } else {
                // Add available investigators from the company (exclude implicated ones)
                $investigators = User::where('company_id', $case->company_id)
                    ->where('role', 'investigator')
                    ->where('status', 'active')
                    ->whereNotIn('id', $implicatedUserIds) // Exclude implicated parties
                    ->limit(3) // Limit to avoid too many participants initially
                    ->get();

                foreach ($investigators as $investigator) {
                    $participants[] = [
                        'user_id' => $investigator->id,
                        'role' => 'investigator'
                    ];
                }
            }

            // Log information about excluded parties for transparency
            if (!empty($implicatedUserIds)) {
                Log::info('Excluded implicated parties from thread participants', [
                    'case_id' => $case->id,
                    'case_token' => $case->case_token,
                    'excluded_user_ids' => $implicatedUserIds,
                    'total_participants_added' => count($participants)
                ]);
            }

            // Remove duplicates based on user_id
            $uniqueParticipants = [];
            $seenUserIds = [];

            foreach ($participants as $participant) {
                if (!in_array($participant['user_id'], $seenUserIds)) {
                    $uniqueParticipants[] = $participant;
                    $seenUserIds[] = $participant['user_id'];
                }
            }

            return $uniqueParticipants;
        } catch (\Exception $e) {
            Log::error('Failed to get initial participants for case thread', [
                'case_id' => $case->id,
                'error' => $e->getMessage()
            ]);

            return [];
        }
    }

    /**
     * Generate initial system message for the thread.
     */
    private static function generateInitialMessage(CaseModel $case): string
    {
        $caseType = ucfirst($case->type ?? 'case');
        $priority = $case->priority ? 'Priority: ' . ucfirst($case->priority) : 'Priority: Normal';

        $message = "🔔 **New {$caseType} Reported**\n\n";
        $message .= "**Case Token:** {$case->case_token}\n";
        $message .= "**{$priority}**\n";

        if ($case->title) {
            $message .= "**Title:** {$case->title}\n";
        }

        if ($case->branch_id && $case->branch) {
            $message .= "**Branch:** {$case->branch->name}\n";
        }

        $message .= "**Status:** " . ucfirst($case->status) . "\n";
        $message .= "**Reported:** " . $case->created_at->format('M j, Y \a\t g:i A') . "\n\n";

        if ($case->description) {
            $message .= "**Description:**\n{$case->description}\n\n";
        }

        $message .= "This thread has been automatically created for case discussions. ";
        $message .= "All relevant team members have been added as participants. ";
        $message .= "Parties implicated in the case have been excluded from this discussion thread for objectivity. ";
        $message .= "You can create additional threads if needed for specific aspects of the case.";

        return $message;
    }

    /**
     * Add participants to existing thread when case is assigned or escalated.
     */
    public static function addParticipantsOnCaseUpdate(CaseModel $case, array $newUserIds): void
    {
        try {
            // Get the main thread for this case
            $mainThread = Thread::where('case_id', $case->id)
                ->where('created_by_type', 'system')
                ->first();

            if (!$mainThread) {
                Log::warning('No main thread found for case when adding participants', [
                    'case_id' => $case->id,
                    'case_token' => $case->case_token
                ]);
                return;
            }

            DB::beginTransaction();

            foreach ($newUserIds as $userId) {
                // Check if user is already a participant
                $existingParticipant = ThreadParticipant::where('thread_id', $mainThread->id)
                    ->where('user_id', $userId)
                    ->exists();

                if (!$existingParticipant) {
                    $user = User::find($userId);
                    if ($user) {
                        ThreadParticipant::create([
                            'thread_id' => $mainThread->id,
                            'user_id' => $userId,
                            'role' => $user->role,
                            'joined_at' => now()
                        ]);

                        // Add notification message
                        $notificationMessage = CaseMessage::create([
                            'case_id' => $case->id,
                            'thread_id' => $mainThread->id,
                            'sender_id' => null,
                            'sender_type' => 'system',
                            'message' => "👥 {$user->name} has been added to the discussion.",
                            'has_attachments' => false
                        ]);

                        // Mark as read for the new participant
                        MessageRead::create([
                            'message_id' => $notificationMessage->id,
                            'user_id' => $userId,
                            'read_at' => now()
                        ]);
                    }
                }
            }

            DB::commit();

            Log::info('Added participants to case thread', [
                'case_id' => $case->id,
                'thread_id' => $mainThread->id,
                'new_participants' => $newUserIds
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to add participants to case thread', [
                'case_id' => $case->id,
                'new_participants' => $newUserIds,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Create notification message in thread when case status changes.
     */
    public static function addStatusChangeMessage(CaseModel $case, string $oldStatus, string $newStatus, ?User $changedBy = null): void
    {
        try {
            // Get the main thread for this case
            $mainThread = Thread::where('case_id', $case->id)
                ->where('created_by_type', 'system')
                ->first();

            if (!$mainThread) {
                return;
            }

            $changedByName = $changedBy ? $changedBy->name : 'System';
            $message = "📝 **Status Updated**\n\n";
            $message .= "Status changed from **" . ucfirst($oldStatus) . "** to **" . ucfirst($newStatus) . "**\n";
            $message .= "Changed by: {$changedByName}\n";
            $message .= "Time: " . now()->format('M j, Y \a\t g:i A');

            CaseMessage::create([
                'case_id' => $case->id,
                'thread_id' => $mainThread->id,
                'sender_id' => $changedBy ? $changedBy->id : null,
                'sender_type' => $changedBy ? class_basename($changedBy) : 'system',
                'message' => $message,
                'has_attachments' => false
            ]);

            Log::info('Added status change message to case thread', [
                'case_id' => $case->id,
                'thread_id' => $mainThread->id,
                'old_status' => $oldStatus,
                'new_status' => $newStatus
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to add status change message to case thread', [
                'case_id' => $case->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}
