<?php

use App\Http\Controllers\{ProductController,UserAuthController};
use App\Livewire\Pages\{Dashboard, Inventory, Products,Logs, Settings, Stocks};
use Illuminate\Support\Facades\Route;

// Route::get('/', function () {
//     return view('index');
// });



// Route::controller(ProductController::class)->group(function(){
    
// });

Route::middleware(['auth'])->group(function(){

Route::get('/',Dashboard::class)->name('dashboard');

Route::get('products',Products::class)->name('products');

Route::get('stocks',Stocks::class)->name('stocks');

Route::get('inventory',Inventory::class)->name('inventory');

Route::get('settings',Settings::class)->name('settings');

Route::get('logs',Logs::class)->name('logs');


});

Route::post('logout', [UserAuthController::class, 'destroy'])->name('logout');


Route::middleware(['guest'])->controller(UserAuthController::class)->group(function(){
    Route::get('login','login')->name('login');

    

    Route::post('signin','signin')->name('signin');

});

