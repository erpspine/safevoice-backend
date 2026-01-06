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
        Schema::create('sector_department_templates', function (Blueprint $table) {
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
                'government_public_sector'
            ])->index();
            $table->string('department_code', 20)->comment('Unique code for the department within sector');
            $table->string('department_name', 100)->comment('Display name for the department');
            $table->text('description')->nullable()->comment('Description of the department');
            $table->boolean('status')->default(true)->comment('Whether this template is active');
            $table->integer('sort_order')->default(0)->comment('Order for display purposes');
            $table->timestamps();
            $table->softDeletes();

            // Ensure unique department codes within each sector
            $table->unique(['sector', 'department_code'], 'sector_dept_code_unique');

            // Index for faster lookups
            $table->index(['sector', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sector_department_templates');
    }
};
