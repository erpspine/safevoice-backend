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
        Schema::create('company_settings', function (Blueprint $table) {
            $table->ulid('id')->primary();

            // Company Basic Info
            $table->string('company_name');
            $table->string('trading_name')->nullable(); // DBA name
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('mobile')->nullable();
            $table->string('website')->nullable();
            $table->string('logo')->nullable(); // Path to logo file

            // Address
            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country')->default('Tanzania');

            // Tax & Legal Info
            $table->string('tax_id')->nullable(); // TIN
            $table->string('vat_number')->nullable();
            $table->string('registration_number')->nullable(); // Business registration
            $table->decimal('vat_rate', 5, 2)->default(18.00); // VAT percentage
            $table->boolean('vat_enabled')->default(true);

            // Banking Details (for invoices)
            $table->string('bank_name')->nullable();
            $table->string('bank_account_name')->nullable();
            $table->string('bank_account_number')->nullable();
            $table->string('bank_branch')->nullable();
            $table->string('bank_swift_code')->nullable();

            // Invoice Settings
            $table->string('invoice_prefix')->default('INV');
            $table->integer('invoice_starting_number')->default(1000);
            $table->text('invoice_notes')->nullable(); // Default notes on invoices
            $table->text('invoice_terms')->nullable(); // Payment terms
            $table->string('invoice_footer')->nullable();

            // Currency
            $table->string('currency_code')->default('TZS');
            $table->string('currency_symbol')->default('TSh');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_settings');
    }
};
