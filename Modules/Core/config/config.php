<?php

$defaultFallbackMode = env('APP_ENV', 'production') === 'production' ? 'base' : 'key';

return [
    'catalog_path' => dirname(__DIR__).'/Resources/lang',
    'template_path' => base_path('Templates'),
    'ui_catalog_fallback_mode' => env('CMS_UI_CATALOG_FALLBACK_MODE', $defaultFallbackMode),
];
