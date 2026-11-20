<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CropController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MarketController;
use App\Http\Controllers\PriceReportController;
use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route(auth()->check() ? 'dashboard' : 'login'));

// Guest
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
});

// Authenticated
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/products', [ProductController::class, 'index'])->name('products.index');
    Route::get('/products/{product}', [ProductController::class, 'show'])->name('products.show');

    Route::get('/markets', [MarketController::class, 'index'])->name('markets.index');
    Route::get('/markets/{market}', [MarketController::class, 'show'])->name('markets.show');

    Route::get('/reports', [PriceReportController::class, 'index'])->name('reports.index');

    // Buyer-facing marketplace browse (any authenticated role may look)
    Route::get('/browse', [CropController::class, 'browse'])->name('crops.browse');

    // Admin curates the observation side
    Route::middleware('role:admin')->group(function () {
        Route::get('/reports/create', [PriceReportController::class, 'create'])->name('reports.create');
        Route::post('/reports', [PriceReportController::class, 'store'])->name('reports.store');
    });

    // Farmer owns the instance side
    Route::middleware('role:farmer')->prefix('my-crops')->group(function () {
        Route::get('/', [CropController::class, 'index'])->name('crops.index');
        Route::get('/create', [CropController::class, 'create'])->name('crops.create');
        Route::post('/', [CropController::class, 'store'])->name('crops.store');
        Route::get('/{crop}/edit', [CropController::class, 'edit'])->name('crops.edit');
        Route::put('/{crop}', [CropController::class, 'update'])->name('crops.update');
        Route::delete('/{crop}', [CropController::class, 'destroy'])->name('crops.destroy');
    });
});
