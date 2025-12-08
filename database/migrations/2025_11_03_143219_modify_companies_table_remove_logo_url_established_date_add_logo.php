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
        Schema::table('companies', function (Blueprint $table) {
            // Remove logo_url and established_at columns
            if (Schema::hasColumn('companies', 'logo_url')) {
                $table->dropColumn('logo_url');
            }
            if (Schema::hasColumn('companies', 'established_at')) {
                $table->dropColumn('established_at');
            }

            // Add logo attachment field (stores file path or attachment reference)
            $table->string('logo')->nullable()->after('contact');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            // Re-add the removed columns
            $table->string('logo_url')->nullable()->after('contact');
            $table->date('established_at')->nullable()->after('tax_id');

            // Remove the logo attachment field
            if (Schema::hasColumn('companies', 'logo')) {
                $table->dropColumn('logo');
            }
        });
    }
};
