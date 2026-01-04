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
            $table->enum('sector', [
                'education',
                'corporate_workplace',
                'financial_insurance',
                'healthcare',
                'manufacturing_industrial',
                'construction_engineering',
                'security_uniformed_services',
                'hospitality_travel_tourism',
                'ngo_cso_donor_funded',
                'religious_institutions',
                'transport_logistics',
            ])->nullable()->after('description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('sector');
        });
    }
};
