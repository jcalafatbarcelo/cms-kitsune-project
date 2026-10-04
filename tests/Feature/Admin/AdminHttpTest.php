<?php

use App\Models\User;
use App\Services\Admin\SuperAdminBootstrap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Core\Language\Models\LanguageSetting;
use Modules\Core\Template\Exceptions\TemplateOperationException;
use Modules\Core\Template\Services\TemplatePresentationResolver;

uses(RefreshDatabase::class);

test('guests reach the Blade login and cannot reach the dashboard', function () {
    $this->get('/admin')->assertRedirect('/admin/login');
    $this->get('/admin/login')->assertOk()->assertSee('name="password"', false)->assertSee('name="_token"', false);
    $this->get('/admin/unknown')->assertNotFound();
});

test('superadministrator logs in with normalized email and logs out with a new session', function () {
    $user = app(SuperAdminBootstrap::class)->create('Administrator', 'admin@example.test', 'Safe-Test-Password9!');
    $this->withSession(['marker' => 'old-session']);
    $oldId = session()->getId();
    $oldToken = session()->token();
    $this->post('/admin/login', ['email' => ' ADMIN@example.test ', 'password' => 'Safe-Test-Password9!'])->assertRedirect('/admin');
    $this->assertAuthenticatedAs($user, 'web');
    expect(session()->getId())->not->toBe($oldId);
    $this->get('/admin')->assertOk()->assertSee('Administration')->assertSee('/admin/logout')->assertDontSee('PageBuilder');
    $this->get('/admin/login')->assertRedirect('/admin');
    $this->post('/admin/logout')->assertRedirect('/admin/login');
    $this->assertGuest('web');
    expect(session()->get('marker'))->toBeNull()->and(session()->token())->not->toBe($oldToken);
});

test('ordinary users cannot authenticate or reach administrative views', function () {
    $user = User::factory()->create(['email' => 'ordinary@example.test', 'password' => 'Safe-Test-Password9!']);
    $this->post('/admin/login', ['email' => $user->email, 'password' => 'Safe-Test-Password9!'])->assertSessionHasErrors('email');
    $this->assertGuest('web');
    $this->actingAs($user, 'web')->get('/admin')->assertForbidden();
    $this->get('/admin/login')->assertForbidden();
    $this->post('/admin/login')->assertForbidden();
});

test('unknown accounts and wrong passwords are indistinguishable', function () {
    app(SuperAdminBootstrap::class)->create('Administrator', 'admin@example.test', 'Safe-Test-Password9!');
    $unknown = $this->post('/admin/login', ['email' => 'ghost@example.test', 'password' => 'Valid-Format-Password9!']);
    $wrong = $this->post('/admin/login', ['email' => 'admin@example.test', 'password' => 'Wrong-Format-Password9!']);

    $unknown->assertRedirect('/admin/login')->assertSessionHasErrors('email');
    $wrong->assertRedirect('/admin/login')->assertSessionHasErrors('email');
    expect($unknown->getSession()->get('errors')->first('email'))
        ->toBe($wrong->getSession()->get('errors')->first('email'));
    $this->assertGuest('web');
});

test('failed login attempts share a normalized rate limit without retaining passwords', function () {
    for ($i = 0; $i < 5; $i++) {
        $this->post('/admin/login', ['email' => $i % 2 ? ' UNKNOWN@example.test ' : 'unknown@example.test', 'password' => 'Wrong-Test-Password9!'])
            ->assertSessionHasErrors('email');
    }
    $this->post('/admin/login', ['email' => 'unknown@example.test', 'password' => 'Wrong-Test-Password9!'])->assertStatus(429);
    $this->assertGuest('web');
    expect(session()->getOldInput('password'))->toBeNull();
});

test('authentication posts enforce CSRF outside the test bypass', function () {
    $this->app->instance('env', 'local');
    $this->post('/admin/login', ['email' => 'admin@example.test', 'password' => 'Safe-Test-Password9!'])->assertStatus(419);
    $user = User::factory()->create();
    $this->actingAs($user, 'web')->post('/admin/logout')->assertStatus(419);
});

test('invalid persisted manifest hash returns a controlled unavailable response', function () {
    DB::table('cms_templates')->where('identifier', 'base')->update(['manifest_hash' => str_repeat('0', 64)]);
    $this->get('/admin/login')->assertStatus(503)->assertDontSee(base_path());
});

test('registration recovery and management endpoints are absent', function () {
    foreach (['/admin/register', '/admin/forgot-password', '/admin/users'] as $path) {
        $this->get($path)->assertNotFound();
        $this->post($path)->assertNotFound();
    }
});

test('login accepts a valid CSRF token and rejects expired sessions', function () {
    $this->app->instance('env', 'local');
    app(SuperAdminBootstrap::class)->create('Administrator', 'admin@example.test', 'Safe-Test-Password9!');
    $this->withSession(['_token' => 'test-csrf-token']);
    $this->post('/admin/login', ['email' => 'admin@example.test', 'password' => 'Safe-Test-Password9!', '_token' => 'test-csrf-token'])->assertRedirect('/admin');
    $token = session()->token();
    $this->post('/admin/logout', ['_token' => $token])->assertRedirect('/admin/login');
    $this->post('/admin/login', ['_token' => $token])->assertStatus(419);
});

test('administrative templates use the configured backoffice locale and escape old input', function () {
    $spanishId = DB::table('languages')->insertGetId([
        'locale' => 'es_ES', 'url_prefix' => 'es-es', 'name' => 'Spanish (Spain)',
        'native_name' => 'Español (España)', 'text_direction' => 'ltr', 'is_active' => true,
        'installed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);
    LanguageSetting::query()->whereKey(1)->update(['backoffice_default_language_id' => $spanishId]);
    $this->withSession(['_old_input' => ['email' => '"><script>alert(1)</script>']])
        ->get('/admin/login?presentation=../../outside')->assertOk()
        ->assertSee('Acceso de administración')->assertDontSee('<script>alert(1)</script>', false);
});

test('presentation resolution failures do not expose paths', function () {
    $this->mock(TemplatePresentationResolver::class)->shouldReceive('resolve')->once()->andThrow(new TemplateOperationException('Unsafe file at '.base_path()));
    $this->get('/admin/login')->assertStatus(503)->assertDontSee(base_path());
});
