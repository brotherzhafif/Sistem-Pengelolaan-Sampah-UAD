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

Route::get('finance', [App\Http\Controllers\FinanceController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('finance');

Route::get('master-data', [App\Http\Controllers\MasterDataController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('master-data');

Route::get('users', [App\Http\Controllers\UserController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('users.index');

Route::get('reports', [App\Http\Controllers\ReportController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('reports');

Route::get('notifications', [App\Http\Controllers\NotificationController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('notifications');

Route::get('reports/export/pdf', [App\Http\Controllers\ReportExportController::class, 'exportPdf'])
    ->middleware(['auth', 'verified'])
    ->name('reports.export.pdf');

Route::get('reports/export/excel', [App\Http\Controllers\ReportExportController::class, 'exportExcel'])
    ->middleware(['auth', 'verified'])
    ->name('reports.export.excel');

Route::get('survei-kap', [App\Http\Controllers\KapController::class, 'survey'])
    ->name('kap.survey');
Route::redirect('survey', 'survei-kap')->name('public.survey');

Route::get('kap', [App\Http\Controllers\KapController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('kap');

Route::get('profile', [\App\Http\Controllers\ProfileController::class, 'index'])
    ->middleware(['auth'])
    ->name('profile');

require __DIR__.'/auth.php';
