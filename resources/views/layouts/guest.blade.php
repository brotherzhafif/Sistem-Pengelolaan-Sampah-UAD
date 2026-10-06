<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="emerald">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'PS2 UAD') }}</title>
        <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">

        <!-- Google Fonts: Plus Jakarta Sans -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

        <!-- Scripts & Styles -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-base-content bg-base-200 min-h-screen antialiased selection:bg-primary selection:text-white">
        <div class="min-h-screen flex flex-col justify-center items-center px-4 py-8 relative overflow-hidden">
            <!-- Subtle background decorative blurs -->
            <div class="absolute -top-32 -left-32 w-96 h-96 bg-primary/10 rounded-full blur-3xl pointer-events-none"></div>
            <div class="absolute -bottom-32 -right-32 w-96 h-96 bg-secondary/10 rounded-full blur-3xl pointer-events-none"></div>

            <div class="mb-6 text-center z-10">
                <a href="/" wire:navigate class="inline-flex items-center gap-2 group transition transform hover:scale-105 duration-200">
                    <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-primary to-emerald-400 flex items-center justify-center text-white shadow-lg shadow-primary/20">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                    </div>
                    <div class="text-left">
                        <span class="text-2xl font-black tracking-tight text-neutral">PS2 <span class="text-primary">UAD</span></span>
                        <p class="text-xs text-base-content/60 font-medium">Sistem Pengelolaan Sampah Kampus</p>
                    </div>
                </a>
            </div>

            <div class="w-full sm:max-w-md z-10">
                <div class="card bg-base-100 shadow-xl border border-base-300 backdrop-blur-md">
                    <div class="card-body p-6 sm:p-8">
                        {{ $slot }}
                    </div>
                </div>
                
                <div class="text-center mt-6 text-xs text-base-content/60">
                    &copy; {{ date('Y') }} Universitas Ahmad Dahlan. All rights reserved.
                </div>
            </div>
        </div>
    </body>
</html>
