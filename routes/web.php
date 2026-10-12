<?php

use Livewire\Volt\Volt;

Route::view('/', 'welcome');

Route::get('dashboard', [App\Http\Controllers\DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::get('weighing', [App\Http\Controllers\WeighingController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('weighing');

Route::get('sales', [App\Http\Controllers\SaleController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('sales');

Route::get('pickups', [App\Http\Controllers\PickupController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('pickups');

Route::get('expenses', [App\Http\Controllers\ExpenseController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('expenses');

Volt::route('finance', 'pages.finance.index')
    ->middleware(['auth', 'verified'])
    ->name('finance');

Volt::route('master-data', 'pages.master.index')
    ->middleware(['auth', 'verified'])
    ->name('master-data');

Volt::route('users', 'pages.users.index')
    ->middleware(['auth', 'verified'])
    ->name('users.index');

Volt::route('reports', 'pages.reports.index')
    ->middleware(['auth', 'verified'])
    ->name('reports');

Volt::route('notifications', 'pages.notifications.index')
    ->middleware(['auth', 'verified'])
    ->name('notifications');

Route::get('reports/export/pdf', [App\Http\Controllers\ReportExportController::class, 'exportPdf'])
    ->middleware(['auth', 'verified'])
    ->name('reports.export.pdf');

Route::get('reports/export/excel', [App\Http\Controllers\ReportExportController::class, 'exportExcel'])
    ->middleware(['auth', 'verified'])
    ->name('reports.export.excel');

Volt::route('survei-kap', 'pages.kap.survey')
    ->name('kap.survey');
Route::redirect('survey', 'survei-kap')->name('public.survey');

Volt::route('kap', 'pages.kap.index')
    ->middleware(['auth', 'verified'])
    ->name('kap');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

require __DIR__.'/auth.php';
