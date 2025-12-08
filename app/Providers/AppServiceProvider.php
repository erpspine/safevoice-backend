<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Relations\Relation;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Configure polymorphic type mapping for sender relationships
        Relation::enforceMorphMap([
            'user' => 'App\Models\User',
            'User' => 'App\Models\User', // Handle capitalized version
            'investigator' => 'App\Models\User', // Investigators are Users with specific roles
            'admin' => 'App\Models\User',
            'branch_admin' => 'App\Models\User',
            'company_admin' => 'App\Models\User',
        ]);
    }
}
