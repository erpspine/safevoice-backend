<?php

namespace App\Services;

use App\Models\Thread;
use App\Models\ThreadParticipant;
use App\Models\CaseModel;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class ThreadService
{
    /**
     * Get or create a thread for a case.
     * If no thread exists, creates one automatically with primary recipients as participants.
     *
     * @param string $caseId
     * @return Thread|null
     */
    public function getOrCreateCaseThread(string $caseId): ?Thread
    {
        try {
            // Check if thread already exists for this case
            $existingThread = Thread::where('case_id', $caseId)->first();

            if ($existingThread) {
                return $existingThread;
            }

            // Load case with involved parties
            $case = CaseModel::with('involvedParties')->findOrFail($caseId);

            // If no branch, cannot create thread
            if (!$case->branch_id) {
                Log::warning('Cannot create thread - case has no branch', ['case_id' => $caseId]);
                return null;
            }

            // Get IDs of involved parties
            $involvedUserIds = $case->involvedParties()->pluck('employee_id')->toArray();

            // Get primary recipients for the branch (excluding involved parties)
            $primaryRecipients = User::where('branch_id', $case->branch_id)
                ->where('recipient_type', 'primary')
                ->where('status', 'active')
                ->where('is_verified', true)
                ->whereNotIn('id', $involvedUserIds)
                ->get();

            // If no primary recipients available, try alternative recipients
            if ($primaryRecipients->isEmpty()) {
                $primaryRecipients = User::where('branch_id', $case->branch_id)
                    ->where('recipient_type', 'alternative')
                    ->where('status', 'active')
                    ->where('is_verified', true)
                    ->whereNotIn('id', $involvedUserIds)
                    ->get();
            }

            // If still no recipients, cannot create thread
            if ($primaryRecipients->isEmpty()) {
                Log::warning('Cannot create thread - no available recipients', [
                    'case_id' => $caseId,
                    'branch_id' => $case->branch_id,
                ]);
                return null;
            }

            // Create a thread for each primary recipient
            foreach ($primaryRecipients as $recipient) {
                $thread = Thread::create([
                    'case_id' => $caseId,
                    'investigator_id' => $recipient->id,
                    'note' => 'This thread was created automatically for you to communicate with the organization',
                ]);

                // Create thread participant
                ThreadParticipant::create([
                    'thread_id' => $thread->id,
                    'role' => 'investigator',
                    'investigator_id' => $recipient->id,
                ]);
            }

            // Return the first thread created
            $firstThread = Thread::where('case_id', $caseId)->first();

            Log::info('Threads created automatically for case', [
                'case_id' => $caseId,
                'participants_count' => $primaryRecipients->count(),
            ]);

            return $firstThread ? $firstThread->load('participant', 'investigator') : null;
        } catch (\Exception $e) {
            Log::error('Failed to create thread for case', [
                'case_id' => $caseId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    /**
     * Get all threads for a case with participants.
     *
     * @param string $caseId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getCaseThreads(string $caseId)
    {
        return Thread::where('case_id', $caseId)
            ->with([
                'case:id,title,status',
                'investigator:id,name,email',
                'participant.investigator',
                'messages' => function ($query) {
                    $query->latest()->limit(1);
                }
            ])
            ->withCount('messages')
            ->latest()
            ->get();
    }

    /**
     * Create a new thread for a case with specific role.
     *
     * @param string $caseId
     * @param string $role
     * @param string|null $investigatorId
     * @param string|null $note
     * @return Thread|null
     */
    public function createThread(string $caseId, string $role, ?string $investigatorId = null, ?string $note = null): ?Thread
    {
        try {
            $case = CaseModel::findOrFail($caseId);

            $thread = Thread::create([
                'case_id' => $caseId,
                'note' => $note ?? 'This thread was created automatically for you to communicate with the organization',
            ]);

            ThreadParticipant::create([
                'thread_id' => $thread->id,
                'role' => $role,
                'investigator_id' => $investigatorId,
            ]);

            Log::info('Thread created manually', [
                'case_id' => $caseId,
                'thread_id' => $thread->id,
                'role' => $role,
                'investigator_id' => $investigatorId,
            ]);

            return $thread->load('participant');
        } catch (\Exception $e) {
            Log::error('Failed to create thread', [
                'case_id' => $caseId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
