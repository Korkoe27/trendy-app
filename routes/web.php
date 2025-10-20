<?php

use App\Http\Controllers\ProductController;
use App\Http\Controllers\UserAuthController;
use App\Livewire\Pages\{Analytics,Dashboard,Inventory,Logs,Products,Settings,Stocks, Users};
use Illuminate\Support\Facades\Route;

// Route::get('/', function () {
//     return view('index');
// });

// Route::controller(ProductController::class)->group(function(){

// });

Route::middleware('auth')->group(function () {

    Route::get('/', Dashboard::class)->name('dashboard');

    Route::get('products', Products::class)->name('products')->middleware('permission:products,view');

    Route::get('stocks', Stocks::class)->name('stocks')->middleware('permission:stocks,view');

    Route::get('inventory', Inventory::class)->name('inventory')->middleware('permission:inventory,view');

    Route::get('settings', Settings::class)->name('settings')->middleware('permission:settings,view');

    Route::get('logs', Logs::class)->name('logs')->middleware('permission:logs,view');
    
    Route::get('users', Users::class)->name('users')->middleware('permission:users,view');

    Route::get('analytics', Analytics::class)->name('analytics')->middleware('permission:analytics,view');

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
