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
        Schema::table('company_settings', function (Blueprint $table) {
            // Make columns with defaults nullable
            $table->string('country')->nullable()->default('Tanzania')->change();
            $table->decimal('vat_rate', 5, 2)->nullable()->default(18.00)->change();
            $table->boolean('vat_enabled')->nullable()->default(true)->change();
            $table->string('invoice_prefix')->nullable()->default('INV')->change();
            $table->integer('invoice_starting_number')->nullable()->default(1000)->change();
            $table->string('currency_code')->nullable()->default('TZS')->change();
            $table->string('currency_symbol')->nullable()->default('TSh')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->string('country')->nullable(false)->default('Tanzania')->change();
            $table->decimal('vat_rate', 5, 2)->nullable(false)->default(18.00)->change();
            $table->boolean('vat_enabled')->nullable(false)->default(true)->change();
            $table->string('invoice_prefix')->nullable(false)->default('INV')->change();
            $table->integer('invoice_starting_number')->nullable(false)->default(1000)->change();
            $table->string('currency_code')->nullable(false)->default('TZS')->change();
            $table->string('currency_symbol')->nullable(false)->default('TSh')->change();
        });
    }
};
