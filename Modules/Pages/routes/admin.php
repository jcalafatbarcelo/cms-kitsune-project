<?php

use App\Http\Middleware\RequireSuperAdmin;
use Illuminate\Support\Facades\Route;
use Modules\Pages\Http\Controllers\Admin\PageController;

Route::middleware(['web', RequireSuperAdmin::class])->prefix('admin/pages')->name('admin.pages.')->group(function () {
    Route::get('/', [PageController::class, 'index'])->name('index');
    Route::post('/', [PageController::class, 'store'])->name('store');
    Route::get('{page}', [PageController::class, 'edit'])->whereNumber('page')->name('edit');
    Route::post('{page}', [PageController::class, 'update'])->whereNumber('page')->name('update');
    Route::post('{page}/publish', [PageController::class, 'publish'])->whereNumber('page')->name('publish');
    Route::post('{page}/unpublish', [PageController::class, 'unpublish'])->whereNumber('page')->name('unpublish');
    Route::post('{page}/move', [PageController::class, 'move'])->whereNumber('page')->name('move');
    Route::post('{page}/template', [PageController::class, 'changeTemplate'])->whereNumber('page')->name('template');
    Route::post('{page}/delete', [PageController::class, 'delete'])->whereNumber('page')->name('delete');
});
