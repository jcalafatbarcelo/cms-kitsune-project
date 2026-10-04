<?php

use App\Providers\DiagnosticsServiceProvider;
use App\Services\Admin\SuperAdminBootstrap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

test('shows exception details for 5XX outside production', function () {
    config(['diagnostics.error_details' => true]);
    Route::post('/__diag/boom', fn () => throw new RuntimeException('Diagnostic marker 42'));

    $this->post('/__diag/boom')
        ->assertStatus(500)
        ->assertSee('RuntimeException')
        ->assertSee('Diagnostic marker 42')
        ->assertSee('#0');
});

test('hides exception details when the flag is disabled', function () {
    config(['diagnostics.error_details' => false, 'app.debug' => false]);
    Route::post('/__diag/hidden', fn () => throw new RuntimeException('Hidden marker 42'));

    $this->post('/__diag/hidden')
        ->assertStatus(500)
        ->assertDontSee('Hidden marker 42')
        ->assertDontSee('RuntimeException');
});

test('ignores the flag in production', function () {
    config(['diagnostics.error_details' => true, 'app.url' => 'https://localhost']);
    $this->app->instance('env', 'production');
    Route::post('/__diag/prod', fn () => throw new RuntimeException('Production marker 42'));

    $this->post('https://localhost/__diag/prod')
        ->assertStatus(500)
        ->assertDontSee('Production marker 42')
        ->assertDontSee('RuntimeException');
});

test('the production guard ignores environment case variants', function (string $environment) {
    config(['diagnostics.error_details' => true, 'app.debug' => false, 'app.url' => 'https://localhost']);
    $this->app->instance('env', $environment);
    Route::post('/__diag/case', fn () => throw new RuntimeException('Case marker'));

    $this->post('https://localhost/__diag/case')
        ->assertStatus(500)
        ->assertDontSee('Case marker');
})->with(['Production', 'PRODUCTION', 'production']);

test('the diagnostics page does not expose environment values or call arguments', function () {
    config(['diagnostics.error_details' => true, 'app.key' => 'base64:LEAK_MARKER_KEY']);
    Route::post('/__diag/secret', function () {
        (function (string $argument) {
            throw new RuntimeException('plain failure');
        })('ARG_LEAK_MARKER');
    });

    $this->post('/__diag/secret')
        ->assertStatus(500)
        ->assertSee('plain failure')
        ->assertDontSee('LEAK_MARKER_KEY')
        ->assertDontSee('APP_KEY')
        ->assertDontSee('ARG_LEAK_MARKER');
});

test('dashboard reminds when diagnostic mode is active', function () {
    config(['diagnostics.error_details' => true]);
    $user = app(SuperAdminBootstrap::class)->create('Administrator', 'admin@example.test', 'Safe-Test-Password9!');

    $this->actingAs($user, 'web')->get('/admin')
        ->assertOk()
        ->assertSee('Diagnostic mode is active');
});

test('dashboard hides the reminder when diagnostic mode is inactive', function () {
    config(['diagnostics.error_details' => false]);
    $user = app(SuperAdminBootstrap::class)->create('Administrator', 'admin@example.test', 'Safe-Test-Password9!');

    $this->actingAs($user, 'web')->get('/admin')
        ->assertOk()
        ->assertDontSee('Diagnostic mode is active');
});

test('production logs the ignored flag at most once per hour', function (string $environment) {
    config(['diagnostics.error_details' => true]);
    $this->app->instance('env', $environment);
    Log::spy();

    $provider = new DiagnosticsServiceProvider($this->app);
    $provider->boot();
    $provider->boot();

    Log::shouldHaveReceived('warning')->once();
})->with(['production', 'Production']);
