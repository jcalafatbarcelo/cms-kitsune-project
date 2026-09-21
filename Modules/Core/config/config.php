<?php

$defaultFallbackMode = env('APP_ENV', 'production') === 'production' ? 'base' : 'key';

return [
    'catalog_path' => dirname(__DIR__).'/Resources/lang',
    'ui_catalog_fallback_mode' => env('CMS_UI_CATALOG_FALLBACK_MODE', $defaultFallbackMode),
];
