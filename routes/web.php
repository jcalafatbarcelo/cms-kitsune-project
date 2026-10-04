<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\SessionController;
use App\Http\Middleware\RequireSuperAdmin;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware(RequireSuperAdmin::class.':guest')->group(function () {
        Route::get('login', [SessionController::class, 'create'])->name('login');
        Route::post('login', [SessionController::class, 'store'])->name('login.store');
    });
    Route::get('/', DashboardController::class)->middleware(RequireSuperAdmin::class)->name('dashboard');
    Route::post('logout', [SessionController::class, 'destroy'])->middleware('auth:web')->name('logout');
});
