<?php

use App\Http\Middleware\RequireSuperAdmin;
use Illuminate\Support\Facades\Route;
use Modules\Navigation\Http\Controllers\Admin\MenuController;

Route::middleware(['web', RequireSuperAdmin::class])->prefix('admin/menus')->name('admin.menus.')->group(function () {
    Route::get('/', [MenuController::class, 'index'])->name('index');
    Route::post('/', [MenuController::class, 'storeMenu'])->name('store');
    Route::post('items', [MenuController::class, 'storeItem'])->name('items.store');
});

Route::middleware(['web', RequireSuperAdmin::class])->prefix('admin/menu-items')->name('admin.menu-items.')->group(function () {
    Route::post('{item}', [MenuController::class, 'updateItem'])->whereNumber('item')->name('update');
    Route::post('{item}/move', [MenuController::class, 'moveItem'])->whereNumber('item')->name('move');
    Route::post('{item}/delete', [MenuController::class, 'removeItem'])->whereNumber('item')->name('delete');
    Route::post('{item}/home', [MenuController::class, 'markHome'])->whereNumber('item')->name('home');
});

Route::middleware(['web', RequireSuperAdmin::class])->post('admin/home/unmark', [MenuController::class, 'unmarkHome'])->name('admin.home.unmark');
