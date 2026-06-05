<?php

use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\AppointmentReviewController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\CommissionSettlementController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\CompanyPaymentIntegrationController;
use App\Http\Controllers\CompanyPaymentWebhookController;
use App\Http\Controllers\CustomerMembershipController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DailyDashboardController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\MembershipPlanController;
use App\Http\Controllers\MercadoPagoOAuthController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PdvController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductSaleController;
use App\Http\Controllers\ProfessionalAvailabilityController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicAppointmentReviewController;
use App\Http\Controllers\PublicBookingController;
use App\Http\Controllers\PublicBookingPaymentController;
use App\Http\Controllers\PublicMembershipController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\ServiceOrderController;
use App\Http\Controllers\StockController;
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
Route::get('/agendar/{company}/horarios', [PublicBookingController::class, 'slots'])
    ->name('public-bookings.slots');
Route::get('/agendar/{company}/google', [PublicBookingController::class, 'redirectToGoogle'])
    ->name('public-bookings.google.redirect');
Route::get('/agendar/google/callback', [PublicBookingController::class, 'handleGoogleCallback'])
    ->name('public-bookings.google.callback');
Route::post('/agendar/{company}', [PublicBookingController::class, 'store'])
    ->name('public-bookings.store');
Route::get('/agendar/{company}/sucesso/{appointment}', [PublicBookingController::class, 'success'])
    ->name('public-bookings.success');
Route::get('/agendar/{company}/pagamento/{reference}/pendente', [PublicBookingPaymentController::class, 'pending'])
    ->name('public-bookings.payment.pending');
Route::get('/agendar/{company}/pagamento/{reference}/sucesso', [PublicBookingPaymentController::class, 'success'])
    ->name('public-bookings.payment.success');
Route::get('/agendar/{company}/pagamento/{reference}/falha', [PublicBookingPaymentController::class, 'failure'])
    ->name('public-bookings.payment.failure');
Route::get('/empresa/{company}/assinaturas', [PublicMembershipController::class, 'index'])
    ->name('public-memberships.index');
Route::post('/empresa/{company}/assinaturas', [PublicMembershipController::class, 'store'])
    ->name('public-memberships.store');

Route::get('/avaliar/{token}', [PublicAppointmentReviewController::class, 'show'])->name('public-reviews.show');
Route::post('/avaliar/{token}', [PublicAppointmentReviewController::class, 'store'])->name('public-reviews.store');

Route::post('webhooks/company-payments/asaas', [CompanyPaymentWebhookController::class, 'asaas'])
    ->name('webhooks.company-payments.asaas');
Route::post('webhooks/company-payments/galaxy-pay', [CompanyPaymentWebhookController::class, 'galaxyPay'])
    ->name('webhooks.company-payments.galaxy-pay');
Route::post('webhooks/company-payments/mercado-pago', [CompanyPaymentWebhookController::class, 'mercadoPago'])
    ->name('webhooks.company-payments.mercado-pago');

Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth', 'verified', 'support_mode', 'active_company'])
    ->name('dashboard');

Route::middleware(['auth', 'support_mode', 'active_company'])->group(function () {
    Route::get('daily-dashboard', DailyDashboardController::class)->name('daily-dashboard.index');
    Route::get('appointments/client-history/{client}', [AppointmentController::class, 'clientHistory'])
        ->name('appointments.client-history');
    Route::get('appointments/smart-slots', [AppointmentController::class, 'smartSlots'])
        ->name('appointments.smart-slots');
    Route::resource('appointments', AppointmentController::class);
    Route::patch('appointments/{appointment}/status', [AppointmentController::class, 'updateStatus'])
        ->name('appointments.status');
    Route::post('appointments/{appointment}/no-show', [AppointmentController::class, 'markNoShow'])
        ->name('appointments.no-show');
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
    Route::patch('appointments/{appointment}/payment-method', [PaymentController::class, 'updatePaymentMethod'])
        ->name('appointments.payment-method.update');
    Route::post('clients/inline', [ClientController::class, 'storeInline'])
        ->name('clients.inline.store');
    Route::get('clients/reports/birthdays', [ClientController::class, 'birthdays'])
        ->name('clients.birthdays');
    Route::get('clients/reports/absent', [ClientController::class, 'absent'])
        ->name('clients.absent');
    Route::patch('clients/{client}/deactivate', [ClientController::class, 'deactivate'])
        ->name('clients.deactivate');
    Route::patch('clients/{client}/reactivate', [ClientController::class, 'reactivate'])
        ->name('clients.reactivate');
    Route::post('clients/{client}/unblock', [ClientController::class, 'unblock'])->name('clients.unblock');
    Route::post('clients/{client}/customer-memberships', [CustomerMembershipController::class, 'store'])
        ->name('clients.memberships.store');
    Route::patch('customer-memberships/{customer_membership}/pause', [CustomerMembershipController::class, 'pause'])
        ->name('customer-memberships.pause');
    Route::patch('customer-memberships/{customer_membership}/resume', [CustomerMembershipController::class, 'resume'])
        ->name('customer-memberships.resume');
    Route::patch('customer-memberships/{customer_membership}/cancel', [CustomerMembershipController::class, 'cancel'])
        ->name('customer-memberships.cancel');
    Route::resource('clients', ClientController::class);
    Route::get('company/onboarding', [CompanyController::class, 'onboarding'])->name('company.onboarding');
    Route::get('company', [CompanyController::class, 'edit'])->name('company.edit');
    Route::patch('company', [CompanyController::class, 'update'])->name('company.update');
    Route::patch('company/birthday-message', [CompanyController::class, 'updateBirthdayMessage'])->name('company.birthday-message.update');
    Route::post('company/branding-preview', [CompanyController::class, 'previewBrandingStyle'])->name('company.branding-preview');
    Route::get('company/payment-integrations', [CompanyPaymentIntegrationController::class, 'index'])->name('company.payment-integrations.index');
    Route::get('company/payment-integrations/create', [CompanyPaymentIntegrationController::class, 'create'])->name('company.payment-integrations.create');
    Route::post('company/payment-integrations', [CompanyPaymentIntegrationController::class, 'store'])->name('company.payment-integrations.store');
    Route::get('company/payment-integrations/{integration}/edit', [CompanyPaymentIntegrationController::class, 'edit'])->name('company.payment-integrations.edit');
    Route::patch('company/payment-integrations/{integration}', [CompanyPaymentIntegrationController::class, 'update'])->name('company.payment-integrations.update');
    Route::post('company/payment-integrations/{integration}/test', [CompanyPaymentIntegrationController::class, 'test'])->name('company.payment-integrations.test');
    Route::patch('company/payment-integrations/{integration}/toggle', [CompanyPaymentIntegrationController::class, 'toggle'])->name('company.payment-integrations.toggle');
    Route::get('company/payment-integrations/mercado-pago/connect', [MercadoPagoOAuthController::class, 'connect'])->name('company.payment-integrations.mercado-pago.connect');
    Route::get('company/payment-integrations/mercado-pago/callback', [MercadoPagoOAuthController::class, 'callback'])->name('company.payment-integrations.mercado-pago.callback');
    Route::post('company/payment-integrations/mercado-pago/disconnect', [MercadoPagoOAuthController::class, 'disconnect'])->name('company.payment-integrations.mercado-pago.disconnect');
    Route::get('products/sales', [ProductSaleController::class, 'index'])->name('product-sales.index');
    Route::get('products/sales/create', [ProductSaleController::class, 'create'])->name('product-sales.create');
    Route::post('products/sales', [ProductSaleController::class, 'store'])->name('product-sales.store');
    Route::resource('products', ProductController::class)->except(['show']);
    Route::patch('products/{product}/stock', [ProductController::class, 'adjustStock'])
        ->whereNumber('product')
        ->name('products.stock-adjust');
    Route::get('products/{product}', [ProductController::class, 'show'])
        ->whereNumber('product')
        ->name('products.show');
    Route::get('reviews', [AppointmentReviewController::class, 'index'])->name('reviews.index');
    Route::patch('membership-plans/{membership_plan}/toggle-active', [MembershipPlanController::class, 'toggleActive'])
        ->name('membership-plans.toggle-active');
    Route::resource('membership-plans', MembershipPlanController::class)->except(['destroy']);

    Route::get('pdv', [PdvController::class, 'index'])->name('pdv.index');
    Route::post('pdv', [PdvController::class, 'store'])->name('pdv.store');
    Route::get('pdv/sales', [PdvController::class, 'sales'])->name('pdv.sales');
    Route::get('pdv/sales/{serviceOrder}', [PdvController::class, 'showSale'])->name('pdv.sales.show');
    Route::patch('pdv/sales/{serviceOrder}/payment-method', [PdvController::class, 'updateSalePaymentMethod'])
        ->name('pdv.sales.payment-method.update');
    Route::get('pdv/receipt/{serviceOrder}', [PdvController::class, 'receipt'])->name('pdv.receipt');
    Route::get('sales/{serviceOrder}/receipt', [PdvController::class, 'receipt'])->name('sales.receipt');
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
    Route::get('finance/product-commissions', [FinanceController::class, 'productCommissions'])
        ->name('finance.product-commissions');
    Route::get('finance/product-sales', [FinanceController::class, 'productSalesReport'])
        ->name('finance.product-sales');
    Route::resource('expenses', ExpenseController::class)->only(['index', 'store']);
    Route::patch('expenses/{expense}/paid', [ExpenseController::class, 'markPaid'])
        ->name('expenses.mark-paid');
    Route::get('stock', [StockController::class, 'index'])->name('stock.index');
    Route::get('stock/movements', [StockController::class, 'diary'])->name('stock.movements');
    Route::get('stock/diary', [StockController::class, 'diary'])->name('stock.diary');
    Route::get('stock/daily-checks', [StockController::class, 'dailyChecks'])->name('stock.daily-checks.index');
    Route::get('stock/daily-checks/create', [StockController::class, 'createDailyCheck'])->name('stock.daily-checks.create');
    Route::post('stock/daily-checks/generate', [StockController::class, 'generateDailyCheck'])->name('stock.daily-checks.generate');
    Route::get('stock/daily-checks/{dailyStockCheck}', [StockController::class, 'showDailyCheck'])->name('stock.daily-checks.show');
    Route::post('stock/daily-checks/{dailyStockCheck}/complete', [StockController::class, 'completeDailyCheck'])->name('stock.daily-checks.complete');
    Route::post('stock/daily-checks/{dailyStockCheck}/cancel', [StockController::class, 'cancelDailyCheck'])->name('stock.daily-checks.cancel');
    Route::get('stock/counts', [StockController::class, 'counts'])->name('stock.counts.index');
    Route::get('stock/audit-general', [StockController::class, 'counts'])->name('stock.audit-general.index');
    Route::get('stock/counts/create', [StockController::class, 'createCount'])->name('stock.counts.create');
    Route::post('stock/counts', [StockController::class, 'storeCount'])->name('stock.counts.store');
    Route::get('stock/counts/{stockCount}', [StockController::class, 'showCount'])->name('stock.counts.show');
    Route::post('stock/counts/{stockCount}/complete', [StockController::class, 'completeCount'])->name('stock.counts.complete');
    Route::get('stock/audit', [StockController::class, 'audit'])->name('stock.audit');
    Route::get('stock/sales-audit', [StockController::class, 'salesAudit'])->name('stock.sales-audit');
    Route::get('stock/adjustments', fn (\Illuminate\Http\Request $request) => redirect()->route('stock.adjustments.create', $request->query()))->name('stock.adjustments.index');
    Route::get('stock/adjustments/create', [StockController::class, 'createAdjustment'])->name('stock.adjustments.create');
    Route::post('stock/adjustments', [StockController::class, 'storeAdjustment'])->name('stock.adjustments.store');
    Route::get('stock/low', [StockController::class, 'lowStock'])->name('stock.low');
    Route::get('finance/cash', [FinanceController::class, 'cash'])
        ->name('finance.cash');
    Route::post('finance/cash/open', [FinanceController::class, 'openCash'])
        ->name('finance.cash.open');
    Route::post('finance/cash/close', [FinanceController::class, 'closeCash'])
        ->name('finance.cash.close');
    Route::post('finance/cash/outflow', [FinanceController::class, 'registerCashOutflow'])
        ->name('finance.cash.outflow');
    Route::get('finance/report', [FinanceController::class, 'report'])
        ->name('finance.report');
    Route::get('finance/service-report', [FinanceController::class, 'serviceReport'])
        ->name('finance.service-report');
    Route::get('finance/performance', [FinanceController::class, 'performance'])
        ->name('finance.performance');
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
