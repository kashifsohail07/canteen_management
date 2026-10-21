<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\ClosingController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\OwnerController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\RentController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SaleSegmentController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\SetupController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VendorController;
use App\Http\Controllers\VendorSearchController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Guest
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.attempt');
});

/*
|--------------------------------------------------------------------------
| Authenticated (admin and staff)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'active'])->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    // Profile (any active user)
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');

    // Dashboard + activity feed
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/activity', [DashboardController::class, 'activity'])->name('dashboard.activity');
    Route::get('/dashboard/activity/{activityLog}', [DashboardController::class, 'showActivity'])->name('dashboard.activity.show');
    Route::get('/activity/{activityLog}', [ActivityController::class, 'show'])->name('activity.show');

    // Vendors + autocomplete API
    Route::get('/api/vendors/search', VendorSearchController::class)->name('vendors.search');
    Route::resource('vendors', VendorController::class)->except(['destroy']); // destroy = admin only below

    // Quick entry
    Route::get('/sales/create', [SaleController::class, 'create'])->name('sales.create');
    Route::post('/sales', [SaleController::class, 'store'])->name('sales.store');

    Route::get('/purchases/create', [PurchaseController::class, 'create'])->name('purchases.create');
    Route::get('/api/purchases/today-deliveries', [PurchaseController::class, 'todayDeliveries'])->name('purchases.today-deliveries');
    Route::post('/purchases', [PurchaseController::class, 'store'])->name('purchases.store');

    Route::get('/payments/create', [PaymentController::class, 'create'])->name('payments.create');
    Route::post('/payments', [PaymentController::class, 'store'])->name('payments.store');

    Route::get('/expenses/create', [ExpenseController::class, 'create'])->name('expenses.create');
    Route::post('/expenses', [ExpenseController::class, 'store'])->name('expenses.store');

    // Rent
    Route::get('/rent/create', [RentController::class, 'create'])->name('rent.create');
    Route::post('/rent/reserve', [RentController::class, 'storeReserve'])->name('rent.reserve');
    Route::post('/rent/skip', [RentController::class, 'skip'])->name('rent.skip');
    Route::get('/rent/pay', [RentController::class, 'payForm'])->name('rent.pay');
    Route::post('/rent/pay', [RentController::class, 'storePay'])->name('rent.pay.store');

    // Owner
    Route::get('/owner', [OwnerController::class, 'index'])->name('owner.index');
    Route::get('/owner/deposit', [OwnerController::class, 'deposit'])->name('owner.deposit');
    Route::post('/owner/deposit', [OwnerController::class, 'storeDeposit'])->name('owner.deposit.store');
    Route::get('/owner/repayment', [OwnerController::class, 'repayment'])->name('owner.repayment');
    Route::post('/owner/repayment', [OwnerController::class, 'storeRepayment'])->name('owner.repayment.store');

    // Day closing (closing itself is admin-only via policy)
    Route::get('/closing', [ClosingController::class, 'show'])->name('closing.show');
    Route::post('/closing', [ClosingController::class, 'close'])->name('closing.close');

    // Reports
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/daily', [ReportController::class, 'daily'])->name('reports.daily');
    Route::get('/reports/vendor/{vendor}', [ReportController::class, 'vendor'])->name('reports.vendor');
    Route::get('/reports/monthly', [ReportController::class, 'monthly'])->name('reports.monthly');

    // Private bill photos
    Route::get('/invoices/{purchase}', [InvoiceController::class, 'show'])->name('invoices.show');
    Route::post('/invoices/{purchase}/replace', [InvoiceController::class, 'replace'])->name('invoices.replace');
});

/*
|--------------------------------------------------------------------------
| Admin only
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'active', 'admin'])->group(function () {
    Route::get('/settings', [SettingController::class, 'edit'])->name('settings.edit');
    Route::put('/settings', [SettingController::class, 'update'])->name('settings.update');

    Route::get('/segments', [SaleSegmentController::class, 'index'])->name('segments.index');
    Route::post('/segments', [SaleSegmentController::class, 'store'])->name('segments.store');
    Route::put('/segments/{segment}', [SaleSegmentController::class, 'update'])->name('segments.update');
    Route::post('/segments/{segment}/move', [SaleSegmentController::class, 'move'])->name('segments.move');
    Route::delete('/segments/{segment}', [SaleSegmentController::class, 'destroy'])->name('segments.destroy');

    Route::post('/closing/reopen', [ClosingController::class, 'reopen'])->name('closing.reopen');

    Route::post('/vendors/{vendor}/adjust-balance', [VendorController::class, 'adjustBalance'])->name('vendors.adjust-balance');
    Route::post('/vendors/{vendor}/opening-balance', [VendorController::class, 'setOpeningBalance'])->name('vendors.opening-balance');
    Route::delete('/vendors/{vendor}', [VendorController::class, 'destroy'])->name('vendors.destroy');

    Route::get('/setup/vendors', [SetupController::class, 'vendors'])->name('setup.vendors');
    Route::post('/setup/vendors', [SetupController::class, 'storeVendors'])->name('setup.vendors.store');
});

/*
|--------------------------------------------------------------------------
| Super User only — user management + edit posted sales
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'active', 'super_user'])->group(function () {
    Route::put('/sales/{sale}', [SaleController::class, 'update'])->name('sales.update');

    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::post('/users/{user}/toggle-active', [UserController::class, 'toggleActive'])->name('users.toggle-active');
});
