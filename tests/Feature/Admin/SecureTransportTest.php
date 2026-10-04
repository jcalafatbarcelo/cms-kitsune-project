<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['app.url' => 'https://localhost']);
});

test('published sites redirect safe methods globally preserving path and query', function (string $path, string $method) {
    $this->app->instance('env', 'production');
    $this->call($method, 'http://localhost'.$path)->assertStatus(308)->assertHeader('Location', 'https://localhost'.$path);
})->with(['/admin/login', '/', '/es/path?preview=no'])->with(['GET', 'HEAD']);

test('published sites reject unsafe cleartext methods before CSRF or authentication', function (string $method) {
    $this->app->instance('env', 'production');
    $this->call($method, 'http://localhost/admin/login', ['password' => 'Not-To-Be-Processed9!'])->assertStatus(400);
    $this->assertGuest('web');
})->with(['POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS']);

test('published HTTPS login issues secure session cookies even with unsafe configuration', function () {
    $this->app->instance('env', 'production');
    config(['session.secure' => false, 'session.http_only' => false, 'session.same_site' => 'none']);
    $response = $this->get('https://localhost/admin/login')->assertOk();
    $cookie = collect($response->headers->getCookies())->first(fn ($cookie) => $cookie->getName() === config('session.cookie'));
    expect($cookie)->not->toBeNull()->and($cookie->isSecure())->toBeTrue()->and($cookie->isHttpOnly())->toBeTrue()->and($cookie->getSameSite())->toBe('lax');
});

test('only configured proxies may supply the client scheme and address', function () {
    $this->app->instance('env', 'production');
    config(['security.trusted_proxies' => []]);
    $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])
        ->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-For' => '198.51.100.5'])
        ->get('http://localhost/admin/login')->assertStatus(308);
    config(['security.trusted_proxies' => ['192.0.2.10']]);
    $this->get('http://localhost/admin/login')->assertOk();
    expect(request()->ip())->toBe('198.51.100.5');
});

test('local and testing permit cleartext development', function (string $environment) {
    $this->app->instance('env', $environment);
    $this->get('http://localhost/admin/login')->assertOk();
})->with(['local', 'testing']);

test('method overrides cannot turn an insecure credential post into a redirect', function () {
    $this->app->instance('env', 'staging');
    $this->post('http://localhost/admin/login', ['_method' => 'GET', 'password' => 'Not-To-Be-Processed9!'])->assertStatus(400);
});

test('redirect authority comes from configuration rather than the request host', function () {
    $this->app->instance('env', 'production');
    $this->get('http://untrusted.example/path?q=1')->assertRedirect('https://localhost/path?q=1')->assertStatus(308);
});

test('public localized pages still render over HTTPS', function () {
    $this->app->instance('env', 'production');
    $this->get('https://localhost/')->assertOk()->assertSee('Under construction');
});
