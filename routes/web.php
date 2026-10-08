<?php

use Livewire\Volt\Volt;

Route::view('/', 'welcome');

Volt::route('dashboard', 'pages.dashboard.index')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Volt::route('weighing', 'pages.weighing.index')
    ->middleware(['auth', 'verified'])
    ->name('weighing');

Volt::route('sales', 'pages.sales.index')
    ->middleware(['auth', 'verified'])
    ->name('sales');

Volt::route('pickups', 'pages.pickups.index')
    ->middleware(['auth', 'verified'])
    ->name('pickups');

Volt::route('expenses', 'pages.expenses.index')
    ->middleware(['auth', 'verified'])
    ->name('expenses');

Volt::route('finance', 'pages.finance.index')
    ->middleware(['auth', 'verified'])
    ->name('finance');

Volt::route('master-data', 'pages.master.index')
    ->middleware(['auth', 'verified'])
    ->name('master-data');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

require __DIR__.'/auth.php';
