<?php

use App\Models\User;
use App\Services\Admin\SuperAdminBootstrap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Navigation\Models\MenuItem;
use Modules\Navigation\Services\MenuManager;
use Modules\Pages\Models\Page;
use Modules\Pages\Models\PageTranslation;
use Modules\Pages\Models\PageTranslationContent;
use Modules\Pages\Services\PageManager;

uses(RefreshDatabase::class);

test('only the superadministrator can reach the Pages and Menus screens', function () {
    $admin = app(SuperAdminBootstrap::class)->create('Administrator', 'admin@example.test', 'Safe-Test-Password9!');

    $this->get('/admin/pages')->assertRedirect('/admin/login');
    $this->get('/admin/menus')->assertRedirect('/admin/login');

    $this->actingAs(User::factory()->create(), 'web')->get('/admin/pages')->assertForbidden();
    $this->actingAs(User::factory()->create(), 'web')->get('/admin/menus')->assertForbidden();

    $this->actingAs($admin, 'web')->get('/admin/pages')->assertOk()->assertSee('Pages');
    $this->actingAs($admin, 'web')->get('/admin/menus')->assertOk()->assertSee('Menus');
});

test('Page and Menu mutations reject an invalid CSRF token', function () {
    $admin = app(SuperAdminBootstrap::class)->create('Administrator', 'admin@example.test', 'Safe-Test-Password9!');
    $this->app->instance('env', 'local');
    $this->actingAs($admin, 'web');

    $this->post('/admin/pages', ['locale' => 'en', 'slug' => 'about', 'title' => 'About'])->assertStatus(419);
    $this->post('/admin/menus', ['identifier' => 'main-menu'])->assertStatus(419);
});

test('a superadministrator creates a page from the backoffice and it remains a draft', function () {
    $admin = app(SuperAdminBootstrap::class)->create('Administrator', 'admin@example.test', 'Safe-Test-Password9!');
    $this->actingAs($admin, 'web');

    $this->post('/admin/pages', ['locale' => 'en', 'slug' => 'about', 'title' => 'About'])
        ->assertRedirect('/admin/pages');

    expect(PageTranslation::query()->where('slug', 'about')->sole()->is_published)->toBeFalse();
});

test('creating a page with the system static type is rejected with a controlled error', function () {
    $admin = app(SuperAdminBootstrap::class)->create('Administrator', 'admin@example.test', 'Safe-Test-Password9!');
    $this->actingAs($admin, 'web');

    $this->post('/admin/pages', ['locale' => 'en', 'slug' => 'about', 'title' => 'About', 'type' => 'system.static'])
        ->assertOk()
        ->assertSee('cannot be used to create editorial pages');

    expect(PageTranslation::query()->where('slug', 'about')->exists())->toBeFalse();
});

test('a superadministrator creates a root menu item without optional parent and position fields', function () {
    $admin = app(SuperAdminBootstrap::class)->create('Administrator', 'admin@example.test', 'Safe-Test-Password9!');
    $page = app(PageManager::class)->create('en', 'about', 'About');
    app(MenuManager::class)->create('main-menu');

    $this->actingAs($admin, 'web')->post('/admin/menus/items', [
        'menu' => 'main-menu',
        'locale' => 'en',
        'page_id' => $page->page_id,
        'label' => 'About',
    ])->assertRedirect('/admin/menus');

    expect(MenuItem::query()->where('label', 'About')->sole()->only(['parent_id', 'position']))->toBe([
        'parent_id' => null,
        'position' => 1,
    ]);
});

test('a superadministrator moves a menu item to the root without an optional parent field', function () {
    $admin = app(SuperAdminBootstrap::class)->create('Administrator', 'admin@example.test', 'Safe-Test-Password9!');
    $page = app(PageManager::class)->create('en', 'about', 'About');
    $menus = app(MenuManager::class);
    $menus->create('main-menu');
    $parent = $menus->createItem('main-menu', 'en', $page->page_id, 'Parent');
    $child = $menus->createItem('main-menu', 'en', $page->page_id, 'Child', $parent->id);

    $this->actingAs($admin, 'web')->post("/admin/menu-items/{$child->id}/move", [
        'position' => 2,
    ])->assertRedirect('/admin/menus');

    expect($child->fresh()->only(['parent_id', 'position']))->toBe([
        'parent_id' => null,
        'position' => 2,
    ]);
});

test('menu item optional fields remain validated when present', function () {
    $admin = app(SuperAdminBootstrap::class)->create('Administrator', 'admin@example.test', 'Safe-Test-Password9!');
    $page = app(PageManager::class)->create('en', 'about', 'About');
    app(MenuManager::class)->create('main-menu');

    $this->actingAs($admin, 'web')->from('/admin/menus')->post('/admin/menus/items', [
        'menu' => 'main-menu',
        'locale' => 'en',
        'page_id' => $page->page_id,
        'label' => 'About',
        'parent_id' => 'invalid',
        'position' => 'invalid',
    ])->assertRedirect('/admin/menus')
        ->assertSessionHasErrors(['parent_id', 'position']);

    expect(MenuItem::query()->where('label', 'About')->doesntExist())->toBeTrue();
});

test('saving content with invalid embed marker grammar returns a controlled error without persisting', function () {
    $admin = app(SuperAdminBootstrap::class)->create('Administrator', 'admin@example.test', 'Safe-Test-Password9!');
    $this->withoutVite();
    $this->actingAs($admin, 'web');
    $page = app(PageManager::class)->create('en', 'about', 'About');

    $this->post('/admin/pages/'.$page->page_id, [
        'locale' => 'en',
        'slug' => 'about',
        'title' => 'About',
        'content' => '<p>x</p><cms-embed data-type="../../secret" data-ref="x"></cms-embed>',
    ])->assertOk()
        ->assertSee('embed marker is invalid');

    expect(PageTranslationContent::query()->where('page_translation_id', $page->id)->exists())->toBeFalse();
});

test('a failed page edit returns to its edit screen with input and leaves translation unchanged', function () {
    $admin = app(SuperAdminBootstrap::class)->create('Administrator', 'admin@example.test', 'Safe-Test-Password9!');
    $page = app(PageManager::class)->create('en', 'about', 'About');
    $this->withoutVite();

    $this->actingAs($admin, 'web')->post('/admin/pages/'.$page->page_id, [
        'locale' => 'en',
        'slug' => 'renamed',
        'title' => 'Renamed',
        'content' => '<cms-embed data-type="../../secret" data-ref="x"></cms-embed>',
    ])->assertOk()
        ->assertSee('Edit page')
        ->assertSee('Renamed')
        ->assertSee('embed marker is invalid');

    expect($page->fresh()->only(['slug', 'title']))->toBe(['slug' => 'about', 'title' => 'About']);
});

test('a superadministrator can change a page template from the edit screen', function () {
    $admin = app(SuperAdminBootstrap::class)->create('Administrator', 'admin@example.test', 'Safe-Test-Password9!');
    $page = app(PageManager::class)->create('en', 'about', 'About', templateIdentifier: 'base');

    $this->actingAs($admin, 'web')->post('/admin/pages/'.$page->page_id.'/template', ['template' => ''])
        ->assertRedirect('/admin/pages/'.$page->page_id);

    expect(Page::query()->findOrFail($page->page_id)->only(['uses_explicit_template', 'explicit_template_id']))->toBe([
        'uses_explicit_template' => false,
        'explicit_template_id' => null,
    ]);
});

test('a superadministrator can update a menu item destination and label and move it', function () {
    $admin = app(SuperAdminBootstrap::class)->create('Administrator', 'admin@example.test', 'Safe-Test-Password9!');
    $pages = app(PageManager::class);
    $firstPage = $pages->create('en', 'first', 'First');
    $secondPage = $pages->create('en', 'second', 'Second');
    $menus = app(MenuManager::class);
    $menus->create('main-menu');
    $parent = $menus->createItem('main-menu', 'en', $firstPage->page_id, 'Parent');
    $item = $menus->createItem('main-menu', 'en', $firstPage->page_id, 'First');

    $this->actingAs($admin, 'web')->post('/admin/menu-items/'.$item->id, [
        'page_id' => $secondPage->page_id,
        'label' => 'Second',
    ])->assertRedirect('/admin/menus');
    $this->actingAs($admin, 'web')->post('/admin/menu-items/'.$item->id.'/move', [
        'parent_id' => $parent->id,
        'position' => 1,
    ])->assertRedirect('/admin/menus');

    expect($item->fresh()->only(['page_id', 'label', 'parent_id', 'position']))->toBe([
        'page_id' => $secondPage->page_id,
        'label' => 'Second',
        'parent_id' => $parent->id,
        'position' => 1,
    ]);
});

test('the public web does not download the editor assets', function () {
    $response = $this->get('/');

    $response->assertOk()
        ->assertDontSee('js-editor', false)
        ->assertDontSee('tinymce', false);
});

test('the editor assets are referenced only by the backoffice edit screen', function () {
    $edit = file_get_contents(base_path('Templates/Base/Resources/views/system/admin/pages/edit.blade.php'));
    $public = file_get_contents(base_path('Templates/Base/Resources/views/public/page/standard.blade.php'));

    expect($edit)->toContain('js-editor')
        ->and($edit)->toContain('data-editor')
        ->and($edit)->toContain('editor.js')
        ->and($public)->not->toContain('tinymce')
        ->and($public)->not->toContain('js-editor');
});

test('the self-hosted TinyMCE editor explicitly uses the GPL license', function () {
    $editor = file_get_contents(base_path('resources/js/editor.js'));

    expect($editor)->toContain("license_key: 'gpl'");
});
