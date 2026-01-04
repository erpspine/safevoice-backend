<?php

namespace App\Console\Commands;

use App\Models\Investigator;
use App\Models\User;
use Illuminate\Console\Command;

class SyncInvestigators extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-investigators';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create investigator records for users with investigator role';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $users = User::where('role', 'investigator')->get();
        $created = 0;

        foreach ($users as $user) {
            $investigator = Investigator::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'status' => true,
                    'is_external' => false,
                ]
            );

            if ($investigator->wasRecentlyCreated) {
                $created++;
                $this->info("Created investigator for: {$user->name} ({$user->email})");
            }
        }

        $this->info("Done! Created {$created} investigator records. Total: " . Investigator::count());

        return Command::SUCCESS;
    }
}
