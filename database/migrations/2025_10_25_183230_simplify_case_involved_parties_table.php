<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('case_involved_parties', function (Blueprint $table) {
            // Drop all columns except case_id and nature_of_involvement
            $table->dropColumn([
                'user_id',
                'name',
                'email',
                'phone',
                'involvement_type',
                'contact_preference',
                'additional_info',
                'is_anonymous',
                'status'
            ]);

            // Add employee_id column as nullable first
            $table->string('employee_id', 50)->nullable()->after('case_id');

            // Add index for employee_id
            $table->index('employee_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('case_involved_parties', function (Blueprint $table) {
            // Drop employee_id
            $table->dropIndex(['employee_id']);
            $table->dropColumn('employee_id');

            // Add back the removed columns
            $table->ulid('user_id')->nullable();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->enum('involvement_type', [
                'witness',
                'perpetrator',
                'complainant',
                'victim',
                'reporter',
                'other'
            ])->after('phone');
            $table->enum('contact_preference', ['email', 'phone', 'mail', 'none'])
                ->default('email');
            $table->json('additional_info')->nullable();
            $table->boolean('is_anonymous')->default(false);
            $table->boolean('status')->default(true);

            // Add back foreign key
            $table->foreign('user_id')->references('id')->on('users');
        });
    }
};
