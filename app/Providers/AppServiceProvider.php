<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use LogicException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (! $this->app->environment('production')) {
            return;
        }

        $store = (string) config('cache.default');
        $driver = config('cache.stores.'.$store.'.driver');
        if (in_array($driver, ['array', 'null', 'session', 'failover'], true)) {
            throw new LogicException('The production cache store must share atomic locks between backoffice processes.');
        }
    }
}
