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
        Schema::table('sector_feedback_templates', function (Blueprint $table) {
            $table->string('category_name_sw')->nullable()->after('category_name');
            $table->string('subcategory_name_sw')->nullable()->after('subcategory_name');
            $table->text('description_sw')->nullable()->after('description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sector_feedback_templates', function (Blueprint $table) {
            $table->dropColumn(['category_name_sw', 'subcategory_name_sw', 'description_sw']);
        });
    }
};
