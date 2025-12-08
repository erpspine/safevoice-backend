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
        Schema::table('feedback_categories', function (Blueprint $table) {
            // Drop foreign key constraint for auto_notify_department if it exists
            if (Schema::hasColumn('feedback_categories', 'auto_notify_department')) {
                $table->dropForeign(['auto_notify_department']);
            }

            // Drop columns
            if (Schema::hasColumn('feedback_categories', 'icon')) {
                $table->dropColumn('icon');
            }
            if (Schema::hasColumn('feedback_categories', 'color')) {
                $table->dropColumn('color');
            }
            if (Schema::hasColumn('feedback_categories', 'requires_response')) {
                $table->dropColumn('requires_response');
            }
            if (Schema::hasColumn('feedback_categories', 'auto_notify_department')) {
                $table->dropColumn('auto_notify_department');
            }
            if (Schema::hasColumn('feedback_categories', 'sort_order')) {
                $table->dropColumn('sort_order');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('feedback_categories', function (Blueprint $table) {
            // Restore columns
            $table->string('icon', 50)->nullable();
            $table->string('color', 7)->nullable()->default('#007bff');
            $table->boolean('requires_response')->default(false);
            $table->ulid('auto_notify_department')->nullable();
            $table->integer('sort_order')->default(0);

            // Restore foreign key constraint
            $table->foreign('auto_notify_department')
                ->references('id')
                ->on('departments')
                ->onDelete('set null');
        });
    }
};
