<?php

use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\CommissionSettlementController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductSaleController;
use App\Http\Controllers\ProfessionalAvailabilityController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicBookingController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\ServiceOrderController;
use App\Http\Controllers\SuperAdminCompanyController;
use App\Http\Controllers\SuperAdminDashboardController;
use App\Http\Controllers\SuperAdminUserController;
use App\Http\Controllers\SupportModeController;
use App\Http\Controllers\TeamMemberController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/agendar/{company}', [PublicBookingController::class, 'create'])
    ->name('public-bookings.create');
Route::get('/agendar/{company}/google', [PublicBookingController::class, 'redirectToGoogle'])
    ->name('public-bookings.google.redirect');
Route::get('/agendar/google/callback', [PublicBookingController::class, 'handleGoogleCallback'])
    ->name('public-bookings.google.callback');
Route::post('/agendar/{company}', [PublicBookingController::class, 'store'])
    ->name('public-bookings.store');
Route::get('/agendar/{company}/sucesso/{appointment}', [PublicBookingController::class, 'success'])
    ->name('public-bookings.success');

Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth', 'verified', 'support_mode', 'active_company'])
    ->name('dashboard');

Route::middleware(['auth', 'support_mode', 'active_company'])->group(function () {
    Route::resource('appointments', AppointmentController::class);
    Route::patch('appointments/{appointment}/status', [AppointmentController::class, 'updateStatus'])
        ->name('appointments.status');
    Route::get('appointments/{appointment}/order', [ServiceOrderController::class, 'show'])
        ->name('appointments.orders.show');
    Route::post('service-orders/{order}/services', [ServiceOrderController::class, 'addService'])
        ->name('service-orders.services.store');
    Route::post('service-orders/{order}/products', [ServiceOrderController::class, 'addProduct'])
        ->name('service-orders.products.store');
    Route::delete('service-orders/{order}/items/{item}', [ServiceOrderController::class, 'removeItem'])
        ->name('service-orders.items.destroy');
    Route::post('service-orders/{order}/close', [ServiceOrderController::class, 'close'])
        ->name('service-orders.close');
    Route::get('appointments/{appointment}/complete', [PaymentController::class, 'create'])
        ->name('appointments.payments.create');
    Route::post('appointments/{appointment}/complete', [PaymentController::class, 'store'])
        ->name('appointments.payments.store');
    Route::post('clients/inline', [ClientController::class, 'storeInline'])
        ->name('clients.inline.store');
    Route::resource('clients', ClientController::class);
    Route::get('company/onboarding', [CompanyController::class, 'onboarding'])->name('company.onboarding');
    Route::get('company', [CompanyController::class, 'edit'])->name('company.edit');
    Route::patch('company', [CompanyController::class, 'update'])->name('company.update');
    Route::resource('products', ProductController::class)->except(['show']);
    Route::get('products/sales', [ProductSaleController::class, 'index'])->name('product-sales.index');
    Route::get('products/sales/create', [ProductSaleController::class, 'create'])->name('product-sales.create');
    Route::post('products/sales', [ProductSaleController::class, 'store'])->name('product-sales.store');
    Route::resource('services', ServiceController::class);
    Route::resource('team', TeamMemberController::class)->except(['show', 'destroy']);
    Route::patch('team/{team}/toggle-active', [TeamMemberController::class, 'toggleActive'])
        ->name('team.toggle-active');
    Route::get('my-schedule', [ProfessionalAvailabilityController::class, 'editOwn'])
        ->name('schedule.edit');
    Route::put('my-schedule', [ProfessionalAvailabilityController::class, 'updateOwn'])
        ->name('schedule.update');
    Route::delete('my-schedule/day', [ProfessionalAvailabilityController::class, 'clearOwn'])
        ->name('schedule.clear');
    Route::get('team/{team}/availability', [ProfessionalAvailabilityController::class, 'editTeam'])
        ->name('team.availability.edit');
    Route::put('team/{team}/availability', [ProfessionalAvailabilityController::class, 'updateTeam'])
        ->name('team.availability.update');
    Route::delete('team/{team}/availability/day', [ProfessionalAvailabilityController::class, 'clearTeam'])
        ->name('team.availability.clear');
    Route::get('finance', [FinanceController::class, 'index'])
        ->name('finance.index');
    Route::get('finance/production', [FinanceController::class, 'production'])
        ->name('finance.production');
    Route::get('finance/commissions', [FinanceController::class, 'commissions'])
        ->name('finance.commissions');
    Route::get('finance/cash', [FinanceController::class, 'cash'])
        ->name('finance.cash');
    Route::post('finance/cash/open', [FinanceController::class, 'openCash'])
        ->name('finance.cash.open');
    Route::post('finance/cash/close', [FinanceController::class, 'closeCash'])
        ->name('finance.cash.close');
    Route::get('finance/report', [FinanceController::class, 'report'])
        ->name('finance.report');
    Route::get('finance/commissions/settlements/create', [CommissionSettlementController::class, 'create'])
        ->name('finance.commissions.settlements.create');
    Route::post('finance/commissions/settlements', [CommissionSettlementController::class, 'store'])
        ->name('finance.commissions.settlements.store');
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
    Route::post('/companies/{company}/support', [SupportModeController::class, 'start'])->name('companies.support.start');
    Route::post('/support/stop', [SupportModeController::class, 'stop'])->name('support.stop');
    Route::get('/users', [SuperAdminUserController::class, 'index'])->name('users.index');
});

require __DIR__.'/auth.php';
