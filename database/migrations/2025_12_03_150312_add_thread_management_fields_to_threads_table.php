<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('threads', function (Blueprint $table) {
            // Add columns as nullable first
            $table->string('title')->nullable()->after('case_id');
            $table->text('description')->nullable()->after('title');
            $table->enum('status', ['active', 'inactive', 'closed'])->default('active')->after('description');
            $table->ulid('created_by')->nullable()->after('status');
            $table->string('created_by_type')->default('system')->after('created_by');

            // Add foreign key for created_by
            $table->foreign('created_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');
        });

        // Update existing records with default values
        DB::statement("UPDATE threads SET title = CONCAT('Thread for Case #', case_id) WHERE title IS NULL");

        // Now make title required
        Schema::table('threads', function (Blueprint $table) {
            $table->string('title')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('threads', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropColumn([
                'title',
                'description',
                'status',
                'created_by',
                'created_by_type'
            ]);
        });
    }
};
