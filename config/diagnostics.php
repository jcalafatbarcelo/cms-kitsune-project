<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Error details
    |--------------------------------------------------------------------------
    |
    | When enabled, 5XX responses render a diagnostics view with the exception
    | class, message, location and trace. It is available outside production
    | only; in production the flag is ignored and a warning is logged. Values
    | are provided by the deployment environment and are never committed.
    |
    */

    'error_details' => (bool) env('CMS_ERROR_DETAILS', false),
];
