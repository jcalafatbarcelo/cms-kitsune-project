<?php

use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Modules\Pages\Services\PublicPageResolver;

Route::get('/{path?}', fn (PublicPageResolver $resolver, string $path = '') => $resolver->handle(request(), $path))
    ->middleware(StartSession::class)
    ->where('path', '.*');
