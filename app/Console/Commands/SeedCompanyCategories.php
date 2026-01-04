<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Services\IncidentCategoryService;
use Illuminate\Console\Command;

class SeedCompanyCategories extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'company:categories 
                            {email? : The company email}
                            {--all : Process all companies with sectors}
                            {--sync : Sync categories (add new, remove obsolete, preserve custom)}
                            {--sector= : Sync all companies for a specific sector}
                            {--force : Force recreate (delete and create fresh)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manage incident categories for companies based on sector templates';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $service = new IncidentCategoryService();

        // Sync all companies for a specific sector
        if ($sector = $this->option('sector')) {
            $this->info("Syncing all companies with sector: {$sector}");

            $results = $service->syncAllCompaniesForSector($sector);

            foreach ($results as $companyId => $data) {
                $result = $data['result'];
                $this->info("{$data['company']}: {$result['message']}");
            }

            $this->info("Completed syncing " . count($results) . " companies");
            return Command::SUCCESS;
        }

        // Process all companies
        if ($this->option('all')) {
            $companies = Company::whereNotNull('sector')->get();
            $syncMode = $this->option('sync');

            foreach ($companies as $company) {
                $existingCount = $company->incidentCategories()->count();

                if ($syncMode || $existingCount > 0) {
                    // Sync mode: intelligently add/remove
                    $result = $service->syncCategoriesFromSector($company);
                    $this->info("{$company->name}: {$result['message']}");
                } else {
                    // Create mode: only for companies with no categories
                    $categories = $service->createCategoriesFromSector($company);
                    $this->info("Created " . count($categories) . " categories for {$company->name} ({$company->sector})");
                }
            }

            return Command::SUCCESS;
        }

        // Single company mode
        $email = $this->argument('email');

        if (!$email) {
            $email = $this->ask('Enter company email');
        }

        $company = Company::where('email', $email)->first();

        if (!$company) {
            $this->error("Company with email '{$email}' not found");
            return Command::FAILURE;
        }

        if (!$company->sector) {
            $this->error("Company '{$company->name}' does not have a sector assigned");
            return Command::FAILURE;
        }

        $this->info("Company: {$company->name}");
        $this->info("Sector: {$company->sector}");

        // Check existing categories
        $existingCount = $company->incidentCategories()->count();
        $this->info("Existing categories: {$existingCount}");

        if ($existingCount > 0) {
            if ($this->option('force')) {
                // Force recreate
                $categories = $service->recreateCategoriesForCompany($company);
                $this->warn("Force recreated " . count($categories) . " incident categories (deleted all existing)");
            } elseif ($this->option('sync') || $this->confirm("Do you want to sync categories? (adds new, removes obsolete, preserves custom)")) {
                // Sync mode
                $result = $service->syncCategoriesFromSector($company);

                $this->info($result['message']);

                if (!empty($result['added'])) {
                    $this->info("Added:");
                    foreach ($result['added'] as $item) {
                        $this->line("  + [{$item['type']}] {$item['name']} ({$item['action']})");
                    }
                }

                if (!empty($result['removed'])) {
                    $this->warn("Removed:");
                    foreach ($result['removed'] as $item) {
                        $this->line("  - [{$item['type']}] {$item['name']}");
                    }
                }

                if (!empty($result['preserved']) && $this->getOutput()->isVerbose()) {
                    $this->info("Preserved: " . count($result['preserved']) . " categories");
                }
            } else {
                $this->info("Aborted.");
            }
        } else {
            $categories = $service->createCategoriesFromSector($company);
            $this->info("Created " . count($categories) . " incident categories");
        }

        return Command::SUCCESS;
    }
}
