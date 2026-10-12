<?php

namespace App\Providers;

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
        \App\Models\Sale::observe(\App\Observers\SaleObserver::class);
        \App\Models\Pickup::observe(\App\Observers\PickupObserver::class);
        \App\Models\Expense::observe(\App\Observers\ExpenseObserver::class);

        // Bypass Cloudflare Rocket Loader for Livewire & Vite scripts
        // Cloudflare Rocket Loader breaks/delays single-page & AJAX script hydration
        if (class_exists(\Livewire\Livewire::class)) {
            app('livewire')->useScriptTagAttributes([
                'data-cfasync' => 'false',
            ]);
        }

        \Illuminate\Support\Facades\Vite::useScriptTagAttributes([
            'data-cfasync' => 'false',
        ]);
    }
}
