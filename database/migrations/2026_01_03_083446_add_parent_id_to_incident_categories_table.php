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
        Schema::table('incident_categories', function (Blueprint $table) {
            $table->foreignUlid('parent_id')->nullable()->after('company_id')->constrained('incident_categories')->onDelete('cascade');
            $table->string('category_key')->nullable()->after('name'); // To track template origin
            $table->integer('sort_order')->default(0)->after('status');

            $table->index('parent_id');
            $table->index('category_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('incident_categories', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropColumn(['parent_id', 'category_key', 'sort_order']);
        });
    }
};
