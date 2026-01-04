<?php

namespace App\Console\Commands;

use App\Services\CaseTrackingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckCaseEscalations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cases:check-escalations 
                            {--company= : Check escalations for a specific company}
                            {--dry-run : Run without triggering actual escalations}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for overdue cases and trigger escalations based on configured rules';

    protected CaseTrackingService $trackingService;

    public function __construct(CaseTrackingService $trackingService)
    {
        parent::__construct();
        $this->trackingService = $trackingService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting case escalation check...');
        $startTime = now();

        $isDryRun = $this->option('dry-run');
        $companyId = $this->option('company');

        if ($isDryRun) {
            $this->warn('Running in DRY-RUN mode - no actual escalations will be triggered');
        }

        try {
            if ($isDryRun) {
                // In dry-run mode, just report what would be escalated
                $this->performDryRun($companyId);
            } else {
                // Actually trigger escalations
                $escalatedCases = $this->trackingService->checkAndEscalateOverdueCases();

                if (count($escalatedCases) > 0) {
                    $this->info('');
                    $this->info('Escalated ' . count($escalatedCases) . ' case(s):');

                    $headers = ['Case ID', 'Rule', 'Overdue Minutes'];
                    $rows = collect($escalatedCases)->map(function ($item) {
                        return [
                            $item['case_id'],
                            $item['rule_name'],
                            $item['overdue_minutes'],
                        ];
                    })->toArray();

                    $this->table($headers, $rows);

                    Log::info('Case escalation check completed', [
                        'escalated_count' => count($escalatedCases),
                        'escalated_cases' => $escalatedCases,
                    ]);
                } else {
                    $this->info('No cases require escalation.');
                }
            }

            $duration = $startTime->diffInSeconds(now());
            $this->info('');
            $this->info("Completed in {$duration} seconds.");

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Error during escalation check: ' . $e->getMessage());
            Log::error('Case escalation check failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return Command::FAILURE;
        }
    }

    /**
     * Perform a dry run to show what would be escalated
     */
    protected function performDryRun(?string $companyId): void
    {
        $this->info('Checking for overdue cases...');

        // Get the rules and cases
        $rules = \App\Models\CaseEscalationRule::where('is_active', true)
            ->orderBy('priority', 'desc')
            ->get();

        $casesQuery = \App\Models\CaseModel::whereIn('status', ['open', 'assigned', 'in_progress'])
            ->with(['company', 'branch', 'assignedInvestigator']);

        if ($companyId) {
            $casesQuery->where('company_id', $companyId);
        }

        $cases = $casesQuery->get();

        $this->info("Found {$cases->count()} open cases and {$rules->count()} active rules.");
        $this->info('');

        $wouldEscalate = [];

        foreach ($cases as $case) {
            $currentStage = $this->trackingService->getCurrentStage($case);
            $applicableRules = $rules->filter(
                fn($rule) =>
                $rule->stage === $currentStage && $rule->appliesTo($case)
            );

            foreach ($applicableRules as $rule) {
                $stageStartEvent = $this->trackingService->getFirstEventInStage($case, $rule->stage);
                $startTime = $stageStartEvent?->event_at ?? $case->created_at;
                $elapsedMinutes = $startTime->diffInMinutes(now());

                // Check if already escalated at this level
                $existingEscalation = \App\Models\CaseEscalation::where('case_id', $case->id)
                    ->where('escalation_rule_id', $rule->id)
                    ->where('is_resolved', false)
                    ->exists();

                if ($elapsedMinutes >= $rule->escalation_threshold && !$existingEscalation) {
                    $wouldEscalate[] = [
                        'case_id' => $case->id,
                        'case_token' => $case->case_token,
                        'company' => $case->company?->name ?? 'N/A',
                        'branch' => $case->branch?->name ?? 'N/A',
                        'status' => $case->status,
                        'stage' => $currentStage,
                        'rule_name' => $rule->name,
                        'threshold' => $rule->escalation_threshold,
                        'elapsed' => $elapsedMinutes,
                        'overdue_by' => $elapsedMinutes - $rule->escalation_threshold,
                    ];
                    break;
                }
            }
        }

        if (count($wouldEscalate) > 0) {
            $this->warn('The following ' . count($wouldEscalate) . ' case(s) WOULD be escalated:');
            $this->info('');

            $headers = ['Case Token', 'Company', 'Stage', 'Rule', 'Threshold (min)', 'Elapsed (min)', 'Overdue By'];
            $rows = collect($wouldEscalate)->map(function ($item) {
                return [
                    $item['case_token'],
                    $item['company'],
                    $item['stage'],
                    $item['rule_name'],
                    $item['threshold'],
                    $item['elapsed'],
                    $item['overdue_by'] . ' min',
                ];
            })->toArray();

            $this->table($headers, $rows);
        } else {
            $this->info('No cases would be escalated.');
        }
    }
}
