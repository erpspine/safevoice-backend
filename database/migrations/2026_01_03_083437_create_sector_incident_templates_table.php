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
        Schema::create('sector_incident_templates', function (Blueprint $table) {
            $table->ulid('id')->primary();
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
            ]);
            $table->string('category_key'); // e.g., safeguarding_welfare
            $table->string('category_name'); // e.g., Safeguarding & Welfare
            $table->string('subcategory_name')->nullable(); // e.g., Bullying (Physical)
            $table->text('description')->nullable();
            $table->boolean('status')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            // Indexes
            $table->index('sector');
            $table->index(['sector', 'category_key']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sector_incident_templates');
    }
};
