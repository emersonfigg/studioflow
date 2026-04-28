<?php

use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProfessionalAvailabilityController;
use App\Http\Controllers\PublicBookingController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\SuperAdminCompanyController;
use App\Http\Controllers\SuperAdminDashboardController;
use App\Http\Controllers\SuperAdminUserController;
use App\Http\Controllers\TeamMemberController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/agendar/{company}', [PublicBookingController::class, 'create'])
    ->name('public-bookings.create');
Route::post('/agendar/{company}', [PublicBookingController::class, 'store'])
    ->name('public-bookings.store');
Route::get('/agendar/{company}/sucesso/{appointment}', [PublicBookingController::class, 'success'])
    ->name('public-bookings.success');

Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth', 'verified', 'active_company'])
    ->name('dashboard');

Route::middleware(['auth', 'active_company'])->group(function () {
    Route::resource('appointments', AppointmentController::class);
    Route::patch('appointments/{appointment}/status', [AppointmentController::class, 'updateStatus'])
        ->name('appointments.status');
    Route::get('appointments/{appointment}/complete', [PaymentController::class, 'create'])
        ->name('appointments.payments.create');
    Route::post('appointments/{appointment}/complete', [PaymentController::class, 'store'])
        ->name('appointments.payments.store');
    Route::post('clients/inline', [ClientController::class, 'storeInline'])
        ->name('clients.inline.store');
    Route::resource('clients', ClientController::class);
    Route::resource('services', ServiceController::class);
    Route::resource('team', TeamMemberController::class)->except(['show', 'destroy']);
    Route::patch('team/{team}/toggle-active', [TeamMemberController::class, 'toggleActive'])
        ->name('team.toggle-active');
    Route::get('my-schedule', [ProfessionalAvailabilityController::class, 'editOwn'])
        ->name('schedule.edit');
    Route::put('my-schedule', [ProfessionalAvailabilityController::class, 'updateOwn'])
        ->name('schedule.update');
    Route::get('team/{team}/availability', [ProfessionalAvailabilityController::class, 'editTeam'])
        ->name('team.availability.edit');
    Route::put('team/{team}/availability', [ProfessionalAvailabilityController::class, 'updateTeam'])
        ->name('team.availability.update');
    Route::get('finance', [FinanceController::class, 'index'])
        ->name('finance.index');
    Route::get('finance/production', [FinanceController::class, 'production'])
        ->name('finance.production');
    Route::get('finance/commissions', [FinanceController::class, 'commissions'])
        ->name('finance.commissions');
    Route::get('production', [FinanceController::class, 'production'])
        ->name('production.index');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'super_admin'])->prefix('super-admin')->name('super-admin.')->group(function () {
    Route::get('/dashboard', SuperAdminDashboardController::class)->name('dashboard');
    Route::get('/companies', [SuperAdminCompanyController::class, 'index'])->name('companies.index');
    Route::get('/companies/{company}', [SuperAdminCompanyController::class, 'show'])->name('companies.show');
    Route::patch('/companies/{company}/toggle-active', [SuperAdminCompanyController::class, 'toggleActive'])->name('companies.toggle-active');
    Route::get('/users', [SuperAdminUserController::class, 'index'])->name('users.index');
});

require __DIR__.'/auth.php';
