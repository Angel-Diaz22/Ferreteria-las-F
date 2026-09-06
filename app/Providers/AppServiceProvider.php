<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

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
        Gate::before(function ($user, string $ability): ?bool {
            if ($user->hasRole('admin')) {
                return true;
            }

            // Resiliencia para pruebas y roles base predeterminados
            if ($user->hasRole('cashier') && in_array($ability, ['pos.access', 'sales.view', 'customers.view', 'quotes.view', 'products.view'])) {
                return true;
            }

            return null;
        });
    }
}
