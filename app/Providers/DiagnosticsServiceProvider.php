<?php

namespace App\Providers;

use App\Support\Diagnostics\ErrorDetails;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class DiagnosticsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (! config('diagnostics.error_details') || ! ErrorDetails::isProduction()) {
            return;
        }

        if (Cache::add('cms.diagnostics.production_warning', true, now()->addHour())) {
            Log::warning('CMS_ERROR_DETAILS is enabled in production and has been ignored.');
        }
    }
}
