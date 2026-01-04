<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class CompanySettings extends Model
{
    use HasUlids;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        // Company Basic Info
        'company_name',
        'trading_name',
        'email',
        'phone',
        'mobile',
        'website',
        'logo',

        // Address
        'address_line_1',
        'address_line_2',
        'city',
        'state',
        'postal_code',
        'country',

        // Tax & Legal Info
        'tax_id',
        'vat_number',
        'registration_number',
        'vat_rate',
        'vat_enabled',

        // Banking Details
        'bank_name',
        'bank_account_name',
        'bank_account_number',
        'bank_branch',
        'bank_swift_code',

        // Invoice Settings
        'invoice_prefix',
        'invoice_starting_number',
        'invoice_notes',
        'invoice_terms',
        'invoice_footer',

        // Currency
        'currency_code',
        'currency_symbol',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'vat_rate' => 'decimal:2',
        'vat_enabled' => 'boolean',
        'invoice_starting_number' => 'integer',
    ];

    /**
     * Get the singleton instance of company settings.
     * Creates default settings if none exist.
     */
    public static function getInstance(): self
    {
        $settings = self::first();

        if (!$settings) {
            $settings = self::create([
                'company_name' => 'SafeVoice',
                'email' => 'info@safevoice.tz',
                'country' => 'Tanzania',
                'currency_code' => 'TZS',
                'currency_symbol' => 'TSh',
                'vat_rate' => 18.00,
                'vat_enabled' => true,
                'invoice_prefix' => 'INV',
                'invoice_starting_number' => 1000,
            ]);
        }

        return $settings;
    }

    /**
     * Get formatted full address.
     */
    public function getFullAddressAttribute(): string
    {
        $parts = array_filter([
            $this->address_line_1,
            $this->address_line_2,
            $this->city,
            $this->state,
            $this->postal_code,
            $this->country,
        ]);

        return implode(', ', $parts);
    }

    /**
     * Get logo URL.
     */
    public function getLogoUrlAttribute(): ?string
    {
        if (!$this->logo) {
            return null;
        }

        return asset('storage/' . $this->logo);
    }

    /**
     * Calculate VAT amount for a given subtotal.
     */
    public function calculateVat(float $subtotal): float
    {
        if (!$this->vat_enabled) {
            return 0;
        }

        return round($subtotal * ($this->vat_rate / 100), 2);
    }

    /**
     * Format currency amount.
     */
    public function formatCurrency(float $amount): string
    {
        return $this->currency_symbol . ' ' . number_format($amount, 2);
    }
}
