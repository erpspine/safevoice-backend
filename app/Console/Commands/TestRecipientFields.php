<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TestRecipientFields extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:recipient-fields';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test recipient fields functionality';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Testing recipient fields functionality...');

        // Test fillable fields
        $user = new User();
        $fillable = $user->getFillable();
        $recipientFields = [
            'primary_recipient_name',
            'primary_recipient_email',
            'primary_recipient_phone',
            'primary_recipient_position',
            'alternative_recipient_name',
            'alternative_recipient_email',
            'alternative_recipient_phone',
            'alternative_recipient_position'
        ];

        $this->info('Checking fillable fields...');
        foreach ($recipientFields as $field) {
            if (in_array($field, $fillable)) {
                $this->line("✓ {$field} is fillable");
            } else {
                $this->error("✗ {$field} is NOT fillable");
            }
        }

        // Test database structure
        $this->info('');
        $this->info('Checking database columns...');
        try {
            $columns = DB::getSchemaBuilder()->getColumnListing('users');
            foreach ($recipientFields as $field) {
                if (in_array($field, $columns)) {
                    $this->line("✓ Column {$field} exists");
                } else {
                    $this->error("✗ Column {$field} does NOT exist");
                }
            }
        } catch (\Exception $e) {
            $this->error('Error checking database: ' . $e->getMessage());
        }

        $this->info('');
        $this->info('Test completed!');
        return 0;
    }
}
