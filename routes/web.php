<?php

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/pos');
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', fn () => redirect()->route('pos.index'))->name('dashboard');
    Route::get('/pos', [PosController::class, 'index'])->name('pos.index');
    Route::post('/pos/checkout', [PosController::class, 'checkout'])->middleware('throttle:30,1')->name('pos.checkout');
    Route::get('/sales/{sale}/receipt', [PosController::class, 'receipt'])->name('pos.receipt');
    Route::middleware('administrator')->group(function () {
        Route::resource('products', ProductController::class);
        Route::resource('categories', CategoryController::class)->except('show');
        Route::post('/sales/{sale}/void', [PosController::class, 'void'])->name('sales.void');
    });
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
