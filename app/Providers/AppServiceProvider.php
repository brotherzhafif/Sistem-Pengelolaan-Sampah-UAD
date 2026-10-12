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
        // Mount Volt views directory for Breeze authentication routes without requiring a separate VoltServiceProvider
        if (class_exists(\Livewire\Volt\Volt::class)) {
            \Livewire\Volt\Volt::mount([
                config('livewire.view_path', resource_path('views/livewire')),
            ]);
        }

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
