<?php

use App\Http\Controllers\HubtelWebhookController;
use App\Http\Controllers\PinResetController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\UserAuthController;
use App\Livewire\Pages\Analytics;
use App\Livewire\Pages\Dashboard;
use App\Livewire\Pages\DebtPayments;
use App\Livewire\Pages\Inventory;
use App\Livewire\Pages\Logs;
use App\Livewire\Pages\Orders;
use App\Livewire\Pages\Products;
use App\Livewire\Pages\Settings;
use App\Livewire\Pages\Spending;
use App\Livewire\Pages\StockMovements;
use App\Livewire\Pages\Stocks;
use App\Livewire\Pages\Users;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

// Route::get('/', function () {
//     return view('index');
// });

// Route::controller(ProductController::class)->group(function(){

// });

Route::middleware(['auth'])->group(function () {
    Route::get('/change-password', [UserAuthController::class, 'showChangePassword'])
        ->name('password.change');
    Route::post('/change-password', [UserAuthController::class, 'changePassword'])
        ->name('password.update');
});

Route::middleware(['auth', 'must.change.password'])->group(function () {

    // Log::info("Accessing protected routes");
    Route::get('/', Dashboard::class)->name('dashboard');

    Route::get('orders', Orders::class)->name('orders')->middleware('permission:orders,view');

    Route::get('products', Products::class)->name('products')->middleware('permission:products,view');

    Route::get('stocks', Stocks::class)->name('stocks')->middleware('permission:stocks,view');

    Route::get('stock-movements', StockMovements::class)->name('stock-movements')->middleware('permission:stocks,modify');

    Route::get('inventory', Inventory::class)->name('inventory')->middleware('permission:inventory,create');

    // Personal settings (own name and PIN): open to every signed-in user, no module permission needed.
    Route::get('settings', Settings::class)->name('settings');

    Route::get('logs', Logs::class)->name('logs')->middleware('permission:logs,view');

    Route::get('expenses', Spending::class)->name('spending')->middleware('permission:expenses,view');

    Route::get('debt-payments', DebtPayments::class)->name('debt-payments')->middleware('permission:debt_payments,view');

    Route::get('users', Users::class)->name('users')->middleware('permission:users,view');

    Route::get('analytics', Analytics::class)->name('analytics')->middleware('permission:analytics,view');

});

Route::post('logout', [UserAuthController::class, 'destroy'])->name('logout');

Route::middleware(['guest'])->group(function () {
    Route::controller(UserAuthController::class)->group(function () {
        Route::get('login', 'login')->name('login');
        Route::post('signin', 'signin')->name('signin');
    });

    // Forgot PIN: phone -> SMS code -> new PIN
    Route::controller(PinResetController::class)->group(function () {
        Route::get('forgot-pin', 'showRequest')->name('pin.reset.request');
        Route::post('forgot-pin', 'sendCode')->name('pin.reset.send')->middleware('throttle:10,1');
        Route::get('forgot-pin/verify', 'showVerify')->name('pin.reset.verify');
        Route::post('forgot-pin/verify', 'reset')->name('pin.reset.update')->middleware('throttle:10,1');
    });
});

// Hubtel calls this with the result of a mobile-money payment. Not behind login (Hubtel has no session);
// protected by the secret token in the URL and answers 404 while HUBTEL_PAYMENTS_ENABLED is off.
Route::post('webhooks/hubtel/payments/{token}', HubtelWebhookController::class)
    ->name('webhooks.hubtel.payments')
    ->middleware('throttle:120,1');
