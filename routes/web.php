<?php

use App\Http\Controllers\ProductController;
use App\Http\Controllers\UserAuthController;
use App\Livewire\Pages\Analytics;
use App\Livewire\Pages\Dashboard;
use App\Livewire\Pages\Inventory;
use App\Livewire\Pages\Logs;
use App\Livewire\Pages\Products;
use App\Livewire\Pages\Settings;
use App\Livewire\Pages\Stocks;
use Illuminate\Support\Facades\Route;

// Route::get('/', function () {
//     return view('index');
// });

// Route::controller(ProductController::class)->group(function(){

// });

Route::middleware(['auth'])->group(function () {

    Route::get('/', Dashboard::class)->name('dashboard');

    Route::get('products', Products::class)->name('products');

    Route::get('stocks', Stocks::class)->name('stocks');

    Route::get('inventory', Inventory::class)->name('inventory');

    Route::get('settings', Settings::class)->name('settings');

    Route::get('logs', Logs::class)->name('logs');

    Route::get('analytics', Analytics::class)->name('analytics');

});

Route::post('logout', [UserAuthController::class, 'destroy'])->name('logout');

// Route::middleware(['guest'])->controller(UserAuthController::class)->group(function(){
//     Route::get('login','login')->name('login');

//     Route::post('signin','signin')->name('signin');

// });

Route::controller(UserAuthController::class)->group(function () {

    Route::get('login', 'login')->name('login');
    Route::post('signin', 'signin')->name('signin');
});
