<?php

use App\Http\Controllers\{ProductController,UserAuthController};
use App\Livewire\Pages\{Dashboard,Products, Stocks};
use Illuminate\Support\Facades\Route;

// Route::get('/', function () {
//     return view('index');
// });



// Route::controller(ProductController::class)->group(function(){
    
// });


Route::get('/',Dashboard::class)->name('dashboard');

Route::get('products',Products::class)->name('products');

Route::get('stocks',Stocks::class)->name('stocks');
// Route::get('products',Products::class)->name('products');



Route::controller(UserAuthController::class)->group(function(){
    Route::get('login','login')->name('login');

    

    Route::post('signin','signin')->name('signin');

    Route::post('logout','destroy')->name('logout');
});

