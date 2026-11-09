<?php

use App\Models\User;
use App\Services\Admin\SuperAdminBootstrap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Navigation\Exceptions\NavigationOperationException;
use Modules\Navigation\Models\Menu;
use Modules\Navigation\Models\MenuItem;
use Modules\Navigation\Services\MenuManager;
use Modules\Pages\Models\Page;
use Modules\Pages\Models\PageTranslation;
use Modules\Pages\Models\PageTranslationContent;
use Modules\Pages\Services\PageManager;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
});

test('only the superadministrator can reach the Pages and Menus screens', function () {
    $admin = app(SuperAdminBootstrap::class)->create('Administrator', 'admin@example.test', 'Safe-Test-Password9!');
    $page = Page::query()->firstOrFail();
    $menu = Menu::query()->firstOrFail();

    $this->get('/admin/pages')->assertRedirect('/admin/login');
    $this->get('/admin/pages/new')->assertRedirect('/admin/login');
    $this->get('/admin/menus')->assertRedirect('/admin/login');
    $this->get('/admin/menus/new')->assertRedirect('/admin/login');
    $this->get('/admin/pages/'.$page->id)->assertRedirect('/admin/login');
    $this->get('/admin/menus/'.$menu->id)->assertRedirect('/admin/login');

    $this->actingAs(User::factory()->create(), 'web')->get('/admin/pages')->assertForbidden();
    $this->actingAs(User::factory()->create(), 'web')->get('/admin/pages/new')->assertForbidden();
    $this->actingAs(User::factory()->create(), 'web')->get('/admin/menus')->assertForbidden();
    $this->actingAs(User::factory()->create(), 'web')->get('/admin/menus/new')->assertForbidden();
    $this->actingAs(User::factory()->create(), 'web')->get('/admin/pages/'.$page->id)->assertForbidden();
    $this->actingAs(User::factory()->create(), 'web')->get('/admin/menus/'.$menu->id)->assertForbidden();
    $editor = User::factory()->create();
    $this->actingAs($editor, 'web')->post('/admin/pages/'.$page->id, [])->assertForbidden();
    $this->actingAs($editor, 'web')->post('/admin/pages/'.$page->id.'/publish', [])->assertForbidden();
    $this->actingAs($editor, 'web')->post('/admin/pages/'.$page->id.'/unpublish', [])->assertForbidden();
    $this->actingAs($editor, 'web')->post('/admin/pages/'.$page->id.'/move', [])->assertForbidden();
    $this->actingAs($editor, 'web')->post('/admin/pages/'.$page->id.'/template', [])->assertForbidden();
    $this->actingAs($editor, 'web')->post('/admin/pages/'.$page->id.'/delete', [])->assertForbidden();

    $this->actingAs($admin, 'web')->get('/admin/pages')->assertOk()->assertSee('Pages')
        ->assertSee('CMS Kitsune')->assertSee('aria-current="page"', false);
    $this->actingAs($admin, 'web')->get('/admin/menus')->assertOk()->assertSee('Menus');
});

test('Page and Menu mutations reject an invalid CSRF token', function () {
    $admin = app(SuperAdminBootstrap::class)->create('Administrator', 'admin@example.test', 'Safe-Test-Password9!');
    $this->app->instance('env', 'local');
    $this->actingAs($admin, 'web');

    $this->post('/admin/pages', ['name' => 'About', 'locale' => 'en', 'slug' => 'about', 'title' => 'About'])->assertStatus(419);
    $this->post('/admin/menus', ['identifier' => 'main-menu'])->assertStatus(419);
    $page = app(PageManager::class)->create('en', 'about', 'About', name: 'About');
    app(MenuManager::class)->create('main-menu');
    $item = app(MenuManager::class)->createItem('main-menu', 'en', $page->page_id, 'About');

    $this->post('/admin/menus/items', ['menu' => 'main-menu'])->assertStatus(419);
    $this->post('/admin/menu-items/'.$item->id, [])->assertStatus(419);
    $this->post('/admin/menu-items/'.$item->id.'/move', [])->assertStatus(419);
    $this->post('/admin/menu-items/'.$item->id.'/delete')->assertStatus(419);
    $this->post('/admin/menu-items/'.$item->id.'/home')->assertStatus(419);
    $this->post('/admin/home/unmark', [])->assertStatus(419);
    $this->post('/admin/pages/'.$page->page_id, [])->assertStatus(419);
    $this->post('/admin/pages/'.$page->page_id.'/publish', [])->assertStatus(419);
    $this->post('/admin/pages/'.$page->page_id.'/unpublish', [])->assertStatus(419);
    $this->post('/admin/pages/'.$page->page_id.'/move', [])->assertStatus(419);
    $this->post('/admin/pages/'.$page->page_id.'/template', [])->assertStatus(419);
    $this->post('/admin/pages/'.$page->page_id.'/delete', [])->assertStatus(419);
});

test('a superadministrator creates a page from the backoffice and it remains a draft', function () {
    $admin = app(SuperAdminBootstrap::class)->create('Administrator', 'admin@example.test', 'Safe-Test-Password9!');
    $this->actingAs($admin, 'web');

    $this->post('/admin/pages', ['name' => 'About page', 'locale' => 'en', 'slug' => 'about', 'title' => 'About'])
        ->assertRedirect('/admin/pages')
        ->assertSessionHas('admin_flash.type', 'success')
        ->assertSessionHas('admin_flash.key', 'admin.pages.created');

    $this->get('/admin/pages')->assertOk()->assertSee('Page created.');

    expect(PageTranslation::query()->where('slug', 'about')->sole()->is_published)->toBeFalse();
});

test('the Pages list links to a separate creation screen that preserves a failed creation input', function () {
    $admin = app(SuperAdminBootstrap::class)->create('Administrator', 'admin@example.test', 'Safe-Test-Password9!');

    $this->actingAs($admin)->get('/admin/pages')
        ->assertOk()
        ->assertSee('/admin/pages/new', false)
        ->assertDontSee('name="name"', false);
    $this->actingAs($admin)->get('/admin/pages/new')->assertOk()->assertSee('name="name"', false);
    $this->actingAs($admin)->post('/admin/pages', [
        'name' => 'Failed page', 'locale' => 'en', 'slug' => 'failed', 'title' => 'Failed', 'type' => 'system.static',
    ])->assertRedirect('/admin/pages/new')->assertSessionHas('_old_input.name', 'Failed page');
});

test('creating a page with the system static type is rejected with a controlled error', function () {
    $admin = app(SuperAdminBootstrap::class)->create('Administrator', 'admin@example.test', 'Safe-Test-Password9!');
    $this->actingAs($admin, 'web')->from('/admin/pages/new');

    $this->post('/admin/pages', ['name' => 'About page', 'locale' => 'en', 'slug' => 'about', 'title' => 'About', 'type' => 'system.static'])
        ->assertRedirect('/admin/pages/new')
        ->assertSessionHasErrors(['operation'])
        ->assertSessionHas('_old_input.name', 'About page');

    expect(PageTranslation::query()->where('slug', 'about')->exists())->toBeFalse();
});

test('creating a menu redirects to the menus list with a success message', function () {
    $admin = app(SuperAdminBootstrap::class)->create('Administrator', 'admin@example.test', 'Safe-Test-Password9!');

    $this->actingAs($admin, 'web')->post('/admin/menus', ['identifier' => 'main-menu'])
        ->assertRedirect('/admin/menus')
        ->assertSessionHas('admin_flash.type', 'success')
        ->assertSessionHas('admin_flash.key', 'admin.menus.created');

    $this->get('/admin/menus')->assertOk()->assertSee('Menu created.');
});

test('a superadministrator creates a root menu item without optional parent and position fields', function () {
    $admin = app(SuperAdminBootstrap::class)->create('Administrator', 'admin@example.test', 'Safe-Test-Password9!');
    $page = app(PageManager::class)->create('en', 'about', 'About', name: 'About');
    $menu = app(MenuManager::class)->create('main-menu');

    $this->actingAs($admin, 'web')->post('/admin/menus/items', [
        'menu' => 'main-menu',
        'locale' => 'en',
        'page_id' => $page->page_id,
        'label' => 'About',
    ])->assertRedirect('/admin/menus/'.$menu->id)
        ->assertSessionHas('admin_flash.key', 'admin.menus.item-created');

    $this->get('/admin/menus/'.$menu->id)->assertOk()->assertSee('Menu item created.');

    expect(MenuItem::query()->where('label', 'About')->sole()->only(['parent_id', 'position']))->toBe([
        'parent_id' => null,
        'position' => 1,
    ]);
});

test('a superadministrator moves a menu item to the root without an optional parent field', function () {
    $admin = app(SuperAdminBootstrap::class)->create('Administrator', 'admin@example.test', 'Safe-Test-Password9!');
    $page = app(PageManager::class)->create('en', 'about', 'About', name: 'About');
    $menus = app(MenuManager::class);
    $menu = $menus->create('main-menu');
    $parent = $menus->createItem('main-menu', 'en', $page->page_id, 'Parent');
    $child = $menus->createItem('main-menu', 'en', $page->page_id, 'Child', $parent->id);

    $this->actingAs($admin, 'web')->post("/admin/menu-items/{$child->id}/move", [
        'position' => 2,
    ])->assertRedirect('/admin/menus/'.$menu->id);

    expect($child->fresh()->only(['parent_id', 'position']))->toBe([
        'parent_id' => null,
        'position' => 2,
    ]);
});

test('menu item optional fields remain validated when present', function () {
    $admin = app(SuperAdminBootstrap::class)->create('Administrator', 'admin@example.test', 'Safe-Test-Password9!');
    $page = app(PageManager::class)->create('en', 'about', 'About', name: 'About');
    $menu = app(MenuManager::class)->create('main-menu');

    $this->actingAs($admin, 'web')->from('/admin/menus/'.$menu->id)->post('/admin/menus/items', [
        'menu' => 'main-menu',
        'locale' => 'en',
        'page_id' => $page->page_id,
        'label' => 'About',
        'parent_id' => 'invalid',
        'position' => 'invalid',
    ])->assertRedirect('/admin/menus/'.$menu->id)
        ->assertSessionHasErrors(['parent_id', 'position']);

    expect(MenuItem::query()->where('label', 'About')->doesntExist())->toBeTrue();
});

test('a non scalar Menu identifier fails validation without lookup or persistence', function () {
    $admin = app(SuperAdminBootstrap::class)->create('Administrator', 'admin@example.test', 'Safe-Test-Password9!');
    $page = app(PageManager::class)->create('en', 'about', 'About', name: 'About');
    app(MenuManager::class)->create('main-menu');
    $before = MenuItem::query()->count();
    $menuQueries = [];
    DB::listen(function ($query) use (&$menuQueries): void {
        if (str_contains($query->sql, 'from "menus"')) {
            $menuQueries[] = $query->sql;
        }
    });

    $this->actingAs($admin)->post('/admin/menus/items', [
        'form_context' => 'add-item',
        'menu' => ['x'],
        'locale' => 'en',
        'page_id' => $page->page_id,
        'label' => 'Invalid menu',
        'parent_id' => null,
        'position' => 1,
    ])->assertRedirect('/admin/menus')
        ->assertSessionHasErrors('menu')
        ->assertSessionHas('_old_input.form_context', 'add-item');

    expect($menuQueries)->toBe([])
        ->and(MenuItem::query()->count())->toBe($before);
});

test('saving content with invalid embed marker grammar returns a controlled error without persisting', function () {
    $admin = app(SuperAdminBootstrap::class)->create('Administrator', 'admin@example.test', 'Safe-Test-Password9!');
    $this->withoutVite();
    $this->actingAs($admin, 'web');
    $page = app(PageManager::class)->create('en', 'about', 'About', name: 'About');

    $this->post('/admin/pages/'.$page->page_id, [
        'locale' => 'en',
        'slug' => 'about',
        'title' => 'About',
        'content' => '<p>x</p><cms-embed data-type="../../secret" data-ref="x"></cms-embed>',
    ])->assertRedirect('/admin/pages/'.$page->page_id)
        ->assertSessionHasErrors('operation');

    expect(PageTranslationContent::query()->where('page_translation_id', $page->id)->exists())->toBeFalse();
});

test('a failed page edit returns to its edit screen with input and leaves translation unchanged', function () {
    $admin = app(SuperAdminBootstrap::class)->create('Administrator', 'admin@example.test', 'Safe-Test-Password9!');
    $page = app(PageManager::class)->create('en', 'about', 'About', name: 'About');
    $this->withoutVite();

    $this->actingAs($admin, 'web')->post('/admin/pages/'.$page->page_id, [
        'locale' => 'en',
        'slug' => 'renamed',
        'title' => 'Renamed',
        'content' => '<cms-embed data-type="../../secret" data-ref="x"></cms-embed>',
    ])->assertRedirect('/admin/pages/'.$page->page_id)
        ->assertSessionHasErrors('operation')
        ->assertSessionHas('_old_input.title', 'Renamed');

    $this->actingAs($admin, 'web')->get('/admin/pages/'.$page->page_id)
        ->assertOk()
        ->assertSee('value="Renamed"', false)
        ->assertSee('value="renamed"', false);

    expect($page->fresh()->only(['slug', 'title']))->toBe(['slug' => 'about', 'title' => 'About']);
});

test('Page detail errors ignore an alien Referer and redirect to their edit route', function () {
    $admin = app(SuperAdminBootstrap::class)->create('Administrator', 'admin@example.test', 'Safe-Test-Password9!');
    $pages = app(PageManager::class);
    $page = $pages->create('en', 'about', 'About', name: 'About');
    $child = $pages->create('en', 'child', 'Child', $page->page_id, name: 'Child');
    $this->actingAs($admin);

    $alienReferer = '/admin/menus/unavailable';

    $this->from($alienReferer)->post('/admin/pages/'.$page->page_id, [
        'locale' => 'en', 'slug' => 'about',
    ])->assertRedirect('/admin/pages/'.$page->page_id)->assertSessionHasErrors('title');
    $this->from($alienReferer)->post('/admin/pages/'.$page->page_id.'/publish', ['locale' => 'unknown'])
        ->assertRedirect('/admin/pages/'.$page->page_id)->assertSessionHasErrors('operation');
    $this->from($alienReferer)->post('/admin/pages/'.$page->page_id.'/unpublish', ['locale' => 'unknown'])
        ->assertRedirect('/admin/pages/'.$page->page_id)->assertSessionHasErrors('operation');
    $this->from($alienReferer)->post('/admin/pages/'.$page->page_id.'/move', ['parent_id' => $page->page_id])
        ->assertRedirect('/admin/pages/'.$page->page_id)->assertSessionHasErrors('operation');
    $this->from($alienReferer)->post('/admin/pages/'.$page->page_id.'/template', ['template' => ['invalid']])
        ->assertRedirect('/admin/pages/'.$page->page_id)->assertSessionHasErrors('template');
    $this->from($alienReferer)->post('/admin/pages/'.$page->page_id.'/delete')
        ->assertRedirect('/admin/pages/'.$page->page_id)->assertSessionHasErrors('operation');

    expect($child->fresh()->page->parent_id)->toBe($page->page_id);
});

test('Menu creation and home removal errors use explicit create or selected detail routes', function () {
    $admin = app(SuperAdminBootstrap::class)->create('Administrator', 'admin@example.test', 'Safe-Test-Password9!');
    app(MenuManager::class)->create('main-menu');
    $menu = Menu::query()->where('identifier', 'main-menu')->sole();

    $this->actingAs($admin)->post('/admin/menus', ['identifier' => 'main-menu'])
        ->assertRedirect('/admin/menus/new')->assertSessionHasErrors('operation');
    $this->from('/admin/pages')->post('/admin/home/unmark', [
        'menu' => 'main-menu', 'locale' => 'unknown', 'confirm' => '1',
    ])->assertRedirect('/admin/menus/'.$menu->id)->assertSessionHasErrors('operation');
});

test('a superadministrator can change a page template from the edit screen', function () {
    $admin = app(SuperAdminBootstrap::class)->create('Administrator', 'admin@example.test', 'Safe-Test-Password9!');
    $page = app(PageManager::class)->create('en', 'about', 'About', templateIdentifier: 'base', name: 'About');

    $this->actingAs($admin, 'web')->post('/admin/pages/'.$page->page_id.'/template', ['template' => ''])
        ->assertRedirect('/admin/pages')
        ->assertSessionHas('admin_flash.key', 'admin.pages.template-updated');

    expect(Page::query()->findOrFail($page->page_id)->only(['uses_explicit_template', 'explicit_template_id']))->toBe([
        'uses_explicit_template' => false,
        'explicit_template_id' => null,
    ]);
});

test('saving a page translation redirects to the pages list with a success message', function () {
    $admin = app(SuperAdminBootstrap::class)->create('Administrator', 'admin@example.test', 'Safe-Test-Password9!');
    $page = app(PageManager::class)->create('en', 'about', 'About', name: 'About');

    $this->actingAs($admin, 'web')->post('/admin/pages/'.$page->page_id, [
        'locale' => 'en',
        'slug' => 'renamed',
        'title' => 'Renamed',
    ])->assertRedirect('/admin/pages')
        ->assertSessionHas('admin_flash.type', 'success')
        ->assertSessionHas('admin_flash.key', 'admin.pages.updated');

    $this->get('/admin/pages')->assertOk()->assertSee('Page saved.');
    expect($page->fresh()->only(['slug', 'title']))->toBe(['slug' => 'renamed', 'title' => 'Renamed']);
});

test('unpublishing a page redirects to the list with a warning message', function () {
    $admin = app(SuperAdminBootstrap::class)->create('Administrator', 'admin@example.test', 'Safe-Test-Password9!');
    $page = app(PageManager::class)->create('en', 'about', 'About', name: 'About');
    app(PageManager::class)->publish($page->page_id, 'en');

    $this->actingAs($admin, 'web')->post('/admin/pages/'.$page->page_id.'/unpublish', ['locale' => 'en'])
        ->assertRedirect('/admin/pages')
        ->assertSessionHas('admin_flash.type', 'warning')
        ->assertSessionHas('admin_flash.key', 'admin.pages.unpublished');

    $this->get('/admin/pages')->assertOk()->assertSee('Page unpublished');
});

test('a superadministrator can update a menu item destination and label and move it', function () {
    $admin = app(SuperAdminBootstrap::class)->create('Administrator', 'admin@example.test', 'Safe-Test-Password9!');
    $pages = app(PageManager::class);
    $firstPage = $pages->create('en', 'first', 'First', name: 'First');
    $secondPage = $pages->create('en', 'second', 'Second', name: 'Second');
    $menus = app(MenuManager::class);
    $menu = $menus->create('main-menu');
    $parent = $menus->createItem('main-menu', 'en', $firstPage->page_id, 'Parent');
    $item = $menus->createItem('main-menu', 'en', $firstPage->page_id, 'First');

    $this->actingAs($admin, 'web')->post('/admin/menu-items/'.$item->id, [
        'page_id' => $secondPage->page_id,
        'label' => 'Second',
    ])->assertRedirect('/admin/menus/'.$menu->id);
    $this->actingAs($admin, 'web')->post('/admin/menu-items/'.$item->id.'/move', [
        'parent_id' => $parent->id,
        'position' => 1,
    ])->assertRedirect('/admin/menus/'.$menu->id);

    expect($item->fresh()->only(['page_id', 'label', 'parent_id', 'position']))->toBe([
        'page_id' => $secondPage->page_id,
        'label' => 'Second',
        'parent_id' => $parent->id,
        'position' => 1,
    ]);
});

test('item validation and domain errors always return to the selected or owning menu detail', function () {
    $admin = app(SuperAdminBootstrap::class)->create('Administrator', 'admin@example.test', 'Safe-Test-Password9!');
    $page = app(PageManager::class)->create('en', 'about', 'About', name: 'About');
    $menus = app(MenuManager::class);
    $menus->create('main-menu');
    $menus->create('footer-menu');
    $main = Menu::query()->where('identifier', 'main-menu')->sole();
    $footer = Menu::query()->where('identifier', 'footer-menu')->sole();
    $item = $menus->createItem('main-menu', 'en', $page->page_id, 'About');

    $this->actingAs($admin)->from('/admin/menus/'.$footer->id)->post('/admin/menus/items', [
        'menu' => 'main-menu', 'locale' => 'en', 'page_id' => $page->page_id, 'label' => 'Invalid', 'position' => 'invalid',
    ])->assertRedirect('/admin/menus/'.$main->id)->assertSessionHasErrors('position');
    $this->actingAs($admin)->from('/admin/menus/'.$footer->id)->post('/admin/menu-items/'.$item->id, [
        'page_id' => 'invalid', 'label' => 'About',
    ])->assertRedirect('/admin/menus/'.$main->id)->assertSessionHasErrors('page_id');
    $this->actingAs($admin)->from('/admin/menus/'.$footer->id)->post('/admin/menu-items/'.$item->id.'/move', [
        'position' => 'invalid',
    ])->assertRedirect('/admin/menus/'.$main->id)->assertSessionHasErrors('position');
    $this->actingAs($admin)->from('/admin/menus/'.$footer->id)->post('/admin/menu-items/'.$item->id.'/home')
        ->assertRedirect('/admin/menus/'.$main->id)->assertSessionHasErrors('operation');
});

test('detail mutations delete, assign home, and remove home in their owning menu', function () {
    $admin = app(SuperAdminBootstrap::class)->create('Administrator', 'admin@example.test', 'Safe-Test-Password9!');
    $page = app(PageManager::class)->create('en', 'about', 'About', name: 'About');
    app(PageManager::class)->publish($page->page_id, 'en');
    $menus = app(MenuManager::class);
    $menus->create('main-menu');
    $menus->create('footer-menu');
    $main = Menu::query()->where('identifier', 'main-menu')->sole();
    $footer = Menu::query()->where('identifier', 'footer-menu')->sole();
    $home = $menus->createItem('main-menu', 'en', $page->page_id, 'Home');
    $removable = $menus->createItem('main-menu', 'en', $page->page_id, 'Remove');

    $this->actingAs($admin)->from('/admin/menus/'.$footer->id)->post('/admin/menu-items/'.$home->id.'/home')
        ->assertRedirect('/admin/menus/'.$main->id);
    $this->actingAs($admin)->from('/admin/menus/'.$footer->id)->post('/admin/home/unmark', [
        'menu' => 'main-menu', 'locale' => 'en',
    ])->assertRedirect('/admin/menus/'.$main->id)->assertSessionHasErrors('confirm');
    $this->actingAs($admin)->from('/admin/menus/'.$footer->id)->post('/admin/home/unmark', [
        'menu' => 'footer-menu', 'locale' => 'en', 'confirm' => '1',
    ])->assertRedirect('/admin/menus/'.$main->id);
    $this->actingAs($admin)->from('/admin/menus/'.$footer->id)->post('/admin/menu-items/'.$removable->id.'/delete')
        ->assertRedirect('/admin/menus/'.$main->id);

    expect(MenuItem::query()->find($removable->id))->toBeNull();
});

test('the backoffice uses internal names for page selectors and preserves a root page', function () {
    $admin = app(SuperAdminBootstrap::class)->create('Administrator', 'admin@example.test', 'Safe-Test-Password9!');
    $pages = app(PageManager::class);
    $parent = $pages->create('en', 'parent', 'Translated parent', name: 'Parent internal');
    $child = $pages->create('en', 'child', 'Translated child', $parent->page_id, name: 'Child internal');

    $this->actingAs($admin)->get('/admin/pages')
        ->assertOk()
        ->assertSee('Parent internal')
        ->assertDontSee('Translated parent');
    $this->actingAs($admin)->get('/admin/pages/new')
        ->assertOk()
        ->assertSee('Parent internal');
    $this->actingAs($admin)->get('/admin/pages/'.$parent->page_id)
        ->assertOk()
        ->assertDontSee('Child internal')
        ->assertDontSee('Parent internal</option>', false)
        ->assertSee('-- None --');
    $menu = app(MenuManager::class)->create('main-menu');
    $this->actingAs($admin)->get('/admin/menus/'.$menu->id)
        ->assertOk()
        ->assertSee('Parent internal')
        ->assertSee('Add item')
        ->assertDontSee('Translated parent')
        ->assertDontSee('#'.$parent->page_id, false);

    $this->actingAs($admin)->post('/admin/pages/'.$child->page_id.'/move', [])->assertRedirect();
    expect($child->fresh()->page->parent_id)->toBeNull();
});

test('the Menu list has no item forms and a detail only renders its own items and accessible tabs', function () {
    $admin = app(SuperAdminBootstrap::class)->create('Administrator', 'admin@example.test', 'Safe-Test-Password9!');
    $page = app(PageManager::class)->create('en', 'about', 'About', name: 'About');
    $menus = app(MenuManager::class);
    $main = $menus->create('main-menu');
    $menus->create('footer-menu');
    $menus->createItem('main-menu', 'en', $page->page_id, 'Main item');
    $menus->createItem('footer-menu', 'en', $page->page_id, 'Footer item');

    $this->actingAs($admin)->get('/admin/menus')
        ->assertOk()
        ->assertSee('/admin/menus/new', false)
        ->assertDontSee('Main item')
        ->assertDontSee('action="'.route('admin.menus.items.store').'"', false);
    $this->actingAs($admin)->get('/admin/menus/'.$main->id)
        ->assertOk()
        ->assertSee('Main item')
        ->assertDontSee('Footer item')
        ->assertSee('role="tablist"', false)
        ->assertSee('role="tab"', false)
        ->assertSee('role="tabpanel"', false);
});

test('the Menu detail renders three accessible tabs with Items selected first', function () {
    $admin = app(SuperAdminBootstrap::class)->create('Administrator', 'admin@example.test', 'Safe-Test-Password9!');
    $menu = app(MenuManager::class)->create('main-menu');

    $response = $this->actingAs($admin)->get('/admin/menus/'.$menu->id)->assertOk();
    $document = new DOMDocument;
    $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new DOMXPath($document);
    $tabs = $xpath->query('//*[@role="tab"]');
    $panels = $xpath->query('//*[@role="tabpanel"]');

    expect($tabs->length)->toBe(3)
        ->and(array_map(fn (DOMNode $tab) => trim($tab->textContent), iterator_to_array($tabs)))->toBe(['Items', 'Add item', 'Remove home'])
        ->and($tabs->item(0)->getAttribute('aria-selected'))->toBe('true')
        ->and($tabs->item(0)->getAttribute('aria-controls'))->toBe('items-panel')
        ->and($panels->length)->toBe(3)
        ->and(array_map(fn (DOMElement $panel) => $panel->hasAttribute('hidden'), iterator_to_array($panels)))->toBe([false, false, false])
        ->and(array_map(fn (DOMElement $panel) => $panel->getAttribute('aria-hidden'), iterator_to_array($panels)))->toBe(['false', 'false', 'false'])
        ->and($xpath->query('//*[@id="items-panel"]//table')->length)->toBe(1);
});

test('Add item errors restore only coherent scalar values and identify the recovered tab', function () {
    $admin = app(SuperAdminBootstrap::class)->create('Administrator', 'admin@example.test', 'Safe-Test-Password9!');
    DB::table('languages')->insert([
        'locale' => 'es_ES', 'url_prefix' => 'es-es', 'name' => 'Spanish', 'native_name' => 'Español',
        'text_direction' => 'ltr', 'is_active' => true, 'is_url_general' => false, 'installed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);
    $page = app(PageManager::class)->create('en', 'about', 'About', name: 'About');
    $menus = app(MenuManager::class);
    $menu = $menus->create('main-menu');
    $parent = $menus->createItem('main-menu', 'es_ES', $page->page_id, 'Padre');
    $child = $menus->createItem('main-menu', 'es_ES', $page->page_id, 'Hijo', $parent->id);
    $this->mock(MenuManager::class)
        ->shouldReceive('createItem')->once()->andThrow(new NavigationOperationException('Expected failure.'));

    $this->actingAs($admin)->post('/admin/menus/items', [
        'form_context' => 'add-item', 'menu' => 'main-menu', 'locale' => 'es_ES',
        'page_id' => $page->page_id, 'label' => 'Recuperado', 'parent_id' => $parent->id, 'position' => 2,
    ])->assertRedirect('/admin/menus/'.$menu->id)->assertSessionHasErrors('operation');

    $response = $this->get('/admin/menus/'.$menu->id)->assertOk();
    $document = new DOMDocument;
    $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new DOMXPath($document);

    expect($xpath->query('//*[@data-menu-tabs and @data-active-tab="add-item-tab"]')->length)->toBe(1)
        ->and($xpath->query('//*[@id="locale"]/option[@value="es_ES" and @selected]')->length)->toBe(1)
        ->and($xpath->query('//*[@id="page_id"]/option[@value="'.$page->page_id.'" and @selected]')->length)->toBe(1)
        ->and($xpath->query('//*[@id="label" and @value="Recuperado"]')->length)->toBe(1)
        ->and($xpath->query('//*[@id="parent_id"]/option[@value="'.$parent->id.'" and @selected and normalize-space(text())="Padre (#'.$parent->id.')"]')->length)->toBe(1)
        ->and($xpath->query('//*[@id="position"]/option[@value="2" and @selected and normalize-space(text())="Last position, after Hijo (#'.$child->id.')"]')->length)->toBe(1);
});

test('an update error does not contaminate Add item old input', function () {
    $admin = app(SuperAdminBootstrap::class)->create('Administrator', 'admin@example.test', 'Safe-Test-Password9!');
    $page = app(PageManager::class)->create('en', 'about', 'About', name: 'About');
    $menus = app(MenuManager::class);
    $menu = $menus->create('main-menu');
    $item = $menus->createItem('main-menu', 'en', $page->page_id, 'Existing');

    $this->actingAs($admin)->post('/admin/menu-items/'.$item->id, [
        'form_context' => 'update-item', 'page_id' => 'invalid', 'label' => 'Must not leak',
    ])->assertRedirect('/admin/menus/'.$menu->id)->assertSessionHasErrors('page_id');

    $response = $this->get('/admin/menus/'.$menu->id)->assertOk();
    $document = new DOMDocument;
    $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new DOMXPath($document);

    expect($xpath->query('//*[@data-menu-tabs and not(@data-active-tab)]')->length)->toBe(1)
        ->and($xpath->query('//*[@id="label" and @value=""]')->length)->toBe(1)
        ->and($xpath->query('//*[@id="locale"]/option[@value="en" and @selected]')->length)->toBe(1);
});

test('Add item recovery ignores non scalar and incoherent old input', function () {
    $admin = app(SuperAdminBootstrap::class)->create('Administrator', 'admin@example.test', 'Safe-Test-Password9!');
    $page = app(PageManager::class)->create('en', 'about', 'About', name: 'About');
    $menus = app(MenuManager::class);
    $menu = $menus->create('main-menu');
    $menus->createItem('main-menu', 'en', $page->page_id, 'Existing');

    $this->actingAs($admin)->post('/admin/menus/items', [
        'form_context' => 'add-item', 'menu' => 'main-menu', 'locale' => ['en'],
        'page_id' => [$page->page_id], 'label' => ['Invalid'], 'parent_id' => ['1'], 'position' => ['1'],
    ])->assertRedirect('/admin/menus/'.$menu->id)->assertSessionHasErrors(['locale', 'page_id', 'label', 'parent_id', 'position']);

    $response = $this->get('/admin/menus/'.$menu->id)->assertOk();
    $document = new DOMDocument;
    $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new DOMXPath($document);

    expect($xpath->query('//*[@data-menu-tabs and @data-active-tab="add-item-tab"]')->length)->toBe(1)
        ->and($xpath->query('//*[@id="locale"]/option[@value="en" and @selected]')->length)->toBe(1)
        ->and($xpath->query('//*[@id="label" and @value=""]')->length)->toBe(1)
        ->and($xpath->query('//*[@id="parent_id"]/option[@selected]')->length)->toBe(0)
        ->and($xpath->query('//*[@id="position"]/option[@value="2" and @selected]')->length)->toBe(1);
});

test('Menu parent selectors use localized labels and exclude other trees and moving subtrees', function () {
    $admin = app(SuperAdminBootstrap::class)->create('Administrator', 'admin@example.test', 'Safe-Test-Password9!');
    DB::table('languages')->insert([
        'locale' => 'es_ES', 'url_prefix' => 'es-es', 'name' => 'Spanish', 'native_name' => 'Español',
        'text_direction' => 'ltr', 'is_active' => true, 'is_url_general' => false, 'installed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);
    $page = app(PageManager::class)->create('en', 'about', 'About', name: 'About');
    $menus = app(MenuManager::class);
    $menu = $menus->create('main-menu');
    $otherMenu = $menus->create('footer-menu');
    $parent = $menus->createItem('main-menu', 'en', $page->page_id, 'Parent');
    $child = $menus->createItem('main-menu', 'en', $page->page_id, 'Child', $parent->id);
    $grandchild = $menus->createItem('main-menu', 'en', $page->page_id, 'Grandchild', $child->id);
    $sibling = $menus->createItem('main-menu', 'en', $page->page_id, 'Sibling');
    $spanish = $menus->createItem('main-menu', 'es_ES', $page->page_id, 'Español');
    $foreign = $menus->createItem('footer-menu', 'en', $page->page_id, 'Foreign');

    $response = $this->actingAs($admin)->get('/admin/menus/'.$menu->id)->assertOk();
    $document = new DOMDocument;
    $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new DOMXPath($document);
    $options = $xpath->query('//*[@id="item-parent-'.$parent->id.'"]/option');
    $labels = array_map(fn (DOMNode $option) => trim($option->textContent), iterator_to_array($options));

    expect($labels)->toContain('-- None --', 'Sibling (#'.$sibling->id.')')
        ->not->toContain('Parent (#'.$parent->id.')', 'Child (#'.$child->id.')', 'Grandchild (#'.$grandchild->id.')', 'Español (#'.$spanish->id.')', 'Foreign (#'.$foreign->id.')')
        ->and($response->getContent())->toContain('data-parent-option="Sibling (#'.$sibling->id.')"')
        ->not->toContain('data-parent-option="Foreign (#'.$foreign->id.')"');
});

test('Menu items render depth first with exact columns, depth and Page metadata', function () {
    $admin = app(SuperAdminBootstrap::class)->create('Administrator', 'admin@example.test', 'Safe-Test-Password9!');
    DB::table('languages')->insert([
        'locale' => 'es_ES', 'url_prefix' => 'es-es', 'name' => 'Spanish', 'native_name' => 'Español',
        'text_direction' => 'ltr', 'is_active' => true, 'is_url_general' => false, 'installed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);
    $page = app(PageManager::class)->create('en', 'about', 'About', name: 'About page');
    $menus = app(MenuManager::class);
    $menu = $menus->create('main-menu');
    $first = $menus->createItem('main-menu', 'en', $page->page_id, 'First');
    $second = $menus->createItem('main-menu', 'en', $page->page_id, 'Second');
    $child = $menus->createItem('main-menu', 'en', $page->page_id, 'Child', $first->id);
    $grandchild = $menus->createItem('main-menu', 'en', $page->page_id, 'Grandchild', $child->id);
    $spanish = $menus->createItem('main-menu', 'es_ES', $page->page_id, 'Español');

    $response = $this->actingAs($admin)->get('/admin/menus/'.$menu->id)->assertOk();
    $document = new DOMDocument;
    $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new DOMXPath($document);
    $headers = $xpath->query('//*[@id="items-panel"]//thead/tr/th');
    $rows = $xpath->query('//*[@id="items-panel"]//tbody/tr');

    expect(array_map(fn (DOMNode $header) => trim($header->textContent), iterator_to_array($headers)))->toBe(['Position', 'Label', 'Language', 'Actions'])
        ->and(array_map(fn (DOMElement $row) => (int) $row->getAttribute('data-menu-item-id'), iterator_to_array($rows)))->toBe([$first->id, $child->id, $grandchild->id, $second->id, $spanish->id])
        ->and(array_map(fn (DOMElement $row) => (int) $row->getAttribute('data-depth'), iterator_to_array($rows)))->toBe([0, 1, 2, 0, 0])
        ->and($xpath->query('//*[@data-menu-item-id="'.$child->id.'"]//*[contains(concat(" ", normalize-space(@class), " "), " c-adminmenu-tree__meta ") and normalize-space(text())="About page"]')->length)->toBe(1);
});

test('deep and wide Menu trees retain structural order, depth, exclusions and branch semantics', function () {
    $admin = app(SuperAdminBootstrap::class)->create('Administrator', 'admin@example.test', 'Safe-Test-Password9!');
    $page = app(PageManager::class)->create('en', 'tree', 'Tree', name: 'Tree page');
    $menus = app(MenuManager::class);
    $menu = $menus->create('main-menu');
    $root = $menus->createItem('main-menu', 'en', $page->page_id, 'Root');
    $otherRoot = $menus->createItem('main-menu', 'en', $page->page_id, 'Other root');
    $wideOne = $menus->createItem('main-menu', 'en', $page->page_id, 'Wide one', $root->id);
    $wideTwo = $menus->createItem('main-menu', 'en', $page->page_id, 'Wide two', $root->id);
    $deepOne = $menus->createItem('main-menu', 'en', $page->page_id, 'Deep one', $wideOne->id);
    $deepTwo = $menus->createItem('main-menu', 'en', $page->page_id, 'Deep two', $deepOne->id);

    $response = $this->actingAs($admin)->get('/admin/menus/'.$menu->id)->assertOk();
    $document = new DOMDocument;
    $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new DOMXPath($document);
    $rows = $xpath->query('//*[@id="items-panel"]//tbody/tr');
    $rootCandidates = array_map(
        fn (DOMElement $option) => $option->getAttribute('value'),
        iterator_to_array($xpath->query('//*[@id="item-parent-'.$root->id.'"]/option')),
    );

    expect(array_map(fn (DOMElement $row) => (int) $row->getAttribute('data-menu-item-id'), iterator_to_array($rows)))
        ->toBe([$root->id, $wideOne->id, $deepOne->id, $deepTwo->id, $wideTwo->id, $otherRoot->id])
        ->and(array_map(fn (DOMElement $row) => (int) $row->getAttribute('data-depth'), iterator_to_array($rows)))
        ->toBe([0, 1, 2, 3, 1, 0])
        ->and($rootCandidates)->toContain('', (string) $otherRoot->id)
        ->not->toContain((string) $root->id, (string) $wideOne->id, (string) $wideTwo->id, (string) $deepOne->id, (string) $deepTwo->id)
        ->and($xpath->query('//*[@data-menu-item-id="'.$root->id.'"]//*[contains(concat(" ", normalize-space(@class), " "), " c-adminmenu-tree__branch ")]')->length)->toBe(0)
        ->and($xpath->query('//*[@data-menu-item-id="'.$deepTwo->id.'"]//*[contains(concat(" ", normalize-space(@class), " "), " c-adminmenu-tree__branch ") and @aria-hidden="true"]')->length)->toBe(1);
});

test('Blade labels the final insertion gap with its last sibling', function () {
    $admin = app(SuperAdminBootstrap::class)->create('Administrator', 'admin@example.test', 'Safe-Test-Password9!');
    $page = app(PageManager::class)->create('en', 'about', 'About', name: 'About');
    $menus = app(MenuManager::class);
    $menu = $menus->create('main-menu');
    $first = $menus->createItem('main-menu', 'en', $page->page_id, 'First');
    $second = $menus->createItem('main-menu', 'en', $page->page_id, 'Second');

    $response = $this->actingAs($admin)->get('/admin/menus/'.$menu->id)->assertOk();
    $document = new DOMDocument;
    $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new DOMXPath($document);
    $options = $xpath->query('//*[@id="position"]/option');

    expect(array_map(fn (DOMNode $option) => trim($option->textContent), iterator_to_array($options)))->toBe([
        'First position',
        'After First (#'.$first->id.')',
        'Last position, after Second (#'.$second->id.')',
    ]);
});

test('the web flow inserts a child in the selected gap and rejects an out of range gap atomically', function () {
    $admin = app(SuperAdminBootstrap::class)->create('Administrator', 'admin@example.test', 'Safe-Test-Password9!');
    $page = app(PageManager::class)->create('en', 'about', 'About', name: 'About');
    $menus = app(MenuManager::class);
    $menu = $menus->create('main-menu');
    $parent = $menus->createItem('main-menu', 'en', $page->page_id, 'Parent');
    $first = $menus->createItem('main-menu', 'en', $page->page_id, 'First', $parent->id);

    $this->actingAs($admin)->post('/admin/menus/items', [
        'menu' => 'main-menu', 'locale' => 'en', 'page_id' => $page->page_id, 'label' => 'Inserted',
        'parent_id' => $parent->id, 'position' => 1,
    ])->assertRedirect('/admin/menus/'.$menu->id);

    expect(MenuItem::query()->where('parent_id', $parent->id)->orderBy('position')->pluck('label')->all())->toBe(['Inserted', 'First']);

    $before = MenuItem::query()->where('parent_id', $parent->id)->orderBy('position')->pluck('position', 'label')->all();
    $this->actingAs($admin)->post('/admin/menus/items', [
        'menu' => 'main-menu', 'locale' => 'en', 'page_id' => $page->page_id, 'label' => 'Invalid',
        'parent_id' => $parent->id, 'position' => 99,
    ])->assertRedirect('/admin/menus/'.$menu->id)->assertSessionHasErrors('operation');

    expect(MenuItem::query()->where('label', 'Invalid')->doesntExist())->toBeTrue()
        ->and(MenuItem::query()->where('parent_id', $parent->id)->orderBy('position')->pluck('position', 'label')->all())->toBe($before)
        ->and($first->fresh()->position)->toBe(2);
});

test('moving to an out of range position leaves both sibling groups unchanged', function () {
    $admin = app(SuperAdminBootstrap::class)->create('Administrator', 'admin@example.test', 'Safe-Test-Password9!');
    $page = app(PageManager::class)->create('en', 'about', 'About', name: 'About');
    $menus = app(MenuManager::class);
    $menu = $menus->create('main-menu');
    $parent = $menus->createItem('main-menu', 'en', $page->page_id, 'Parent');
    $moving = $menus->createItem('main-menu', 'en', $page->page_id, 'Moving');
    $rootSibling = $menus->createItem('main-menu', 'en', $page->page_id, 'Root sibling');
    $child = $menus->createItem('main-menu', 'en', $page->page_id, 'Child', $parent->id);
    $before = MenuItem::query()->where('menu_id', $menu->id)->orderBy('id')->get()->map->only(['id', 'parent_id', 'position'])->all();

    $this->actingAs($admin)->post('/admin/menu-items/'.$moving->id.'/move', [
        'form_context' => 'move-item', 'parent_id' => $parent->id, 'position' => 99,
    ])->assertRedirect('/admin/menus/'.$menu->id)->assertSessionHasErrors('operation');

    expect(MenuItem::query()->where('menu_id', $menu->id)->orderBy('id')->get()->map->only(['id', 'parent_id', 'position'])->all())->toBe($before)
        ->and($rootSibling->fresh()->position)->toBe(3)
        ->and($child->fresh()->position)->toBe(1);
});

test('the Pages list renders each home locale as a separate accessible badge', function () {
    $admin = app(SuperAdminBootstrap::class)->create('Administrator', 'admin@example.test', 'Safe-Test-Password9!');
    DB::table('languages')->insert([
        'locale' => 'es_ES', 'url_prefix' => 'es-es', 'name' => 'Spanish', 'native_name' => 'Español',
        'text_direction' => 'ltr', 'is_active' => true, 'is_url_general' => false, 'installed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);
    $page = app(PageManager::class)->create('en', 'shared-home', 'Shared home', name: 'Shared home');
    app(PageManager::class)->updateTranslationAndContent($page->page_id, 'es_ES', 'inicio-compartido', 'Inicio compartido', null);
    app(PageManager::class)->publish($page->page_id, 'en');
    app(PageManager::class)->publish($page->page_id, 'es_ES');
    $menus = app(MenuManager::class);
    $menus->create('main-menu');
    $english = $menus->createItem('main-menu', 'en', $page->page_id, 'Home');
    $spanish = $menus->createItem('main-menu', 'es_ES', $page->page_id, 'Inicio');
    $menus->markHome($english->id);
    $menus->markHome($spanish->id);

    $response = $this->actingAs($admin)->get('/admin/pages')->assertOk();
    $document = new DOMDocument;
    $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new DOMXPath($document);

    expect($xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " c-adminbadge-group ") and @aria-label="Home"]')->length)->toBeGreaterThanOrEqual(1)
        ->and($xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " c-adminbadge-group__item ") and normalize-space(text())="en"]')->length)->toBe(1)
        ->and($xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " c-adminbadge-group__item ") and normalize-space(text())="es_ES"]')->length)->toBe(1);
});

test('array input does not break the creation or edit error screen', function () {
    $admin = app(SuperAdminBootstrap::class)->create('Administrator', 'admin@example.test', 'Safe-Test-Password9!');

    $this->actingAs($admin)->post('/admin/pages', [
        'name' => ['x'], 'locale' => 'en', 'slug' => 'array-check', 'title' => 'Array check',
    ])->assertRedirect('/admin/pages/new')->assertSessionHasErrors('name');

    $this->actingAs($admin)->get('/admin/pages/new')->assertOk();

    $page = app(PageManager::class)->create('en', 'array-edit', 'Array edit', name: 'Array edit');

    $this->actingAs($admin)->post('/admin/pages/'.$page->page_id, [
        'locale' => 'en', 'slug' => 'array-edit', 'title' => ['x'],
    ])->assertRedirect('/admin/pages/'.$page->page_id)->assertSessionHasErrors('title');

    $this->actingAs($admin)->get('/admin/pages/'.$page->page_id)->assertOk();
});

test('the menus list uses its own localized empty message', function () {
    $admin = app(SuperAdminBootstrap::class)->create('Administrator', 'admin@example.test', 'Safe-Test-Password9!');
    DB::table('menu_item_homes')->delete();
    DB::table('menu_items')->delete();
    DB::table('menus')->delete();

    $this->actingAs($admin)->get('/admin/menus')
        ->assertOk()
        ->assertSee('No menus have been created yet.')
        ->assertDontSee('No pages have been created yet.');
});

test('the Spanish menu UI localizes the item CTA and page selectors', function () {
    $admin = app(SuperAdminBootstrap::class)->create('Administrator', 'admin@example.test', 'Safe-Test-Password9!');
    DB::table('languages')->insert([
        'locale' => 'es_ES', 'url_prefix' => 'es-es', 'name' => 'Spanish', 'native_name' => 'Español',
        'text_direction' => 'ltr', 'is_active' => true, 'is_url_general' => false, 'installed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('language_settings')->where('id', 1)->update([
        'backoffice_default_language_id' => DB::table('languages')->where('locale', 'es_ES')->value('id'),
    ]);
    $page = app(PageManager::class)->create('es_ES', 'inicio', 'Título traducido', name: 'Nombre interno');
    app()->setLocale('es_ES');

    $menu = app(MenuManager::class)->create('main-menu');
    $this->actingAs($admin)->get('/admin/menus/'.$menu->id)
        ->assertOk()
        ->assertSee('Añadir elemento')
        ->assertSee('Nombre interno')
        ->assertDontSee('Título traducido');

    $this->actingAs($admin)->post('/admin/menus', ['identifier' => 'footer-menu'])
        ->assertRedirect('/admin/menus')
        ->assertSessionHas('admin_flash.key', 'admin.menus.created');

    $this->actingAs($admin)->get('/admin/menus')->assertOk()->assertSee('Menú creado.');
});

test('the pages list marks unpublished pages', function () {
    $admin = app(SuperAdminBootstrap::class)->create('Administrator', 'admin@example.test', 'Safe-Test-Password9!');
    $page = app(PageManager::class)->create('en', 'draft', 'Draft', name: 'Draft page');

    $this->actingAs($admin)->get('/admin/pages')
        ->assertOk()
        ->assertSee('Draft page')
        ->assertSee('Unpublished');

    app(PageManager::class)->publish($page->page_id, 'en');

    $this->actingAs($admin)->get('/admin/pages')
        ->assertOk()
        ->assertDontSee('Unpublished');
});

test('the menus list marks the home item', function () {
    $admin = app(SuperAdminBootstrap::class)->create('Administrator', 'admin@example.test', 'Safe-Test-Password9!');
    $page = app(PageManager::class)->create('en', 'about', 'About', name: 'About page');
    app(PageManager::class)->publish($page->page_id, 'en');
    $menus = app(MenuManager::class);
    $menu = $menus->create('main-menu');
    $item = $menus->createItem('main-menu', 'en', $page->page_id, 'About');
    $menus->markHome($item->id);

    $this->actingAs($admin)->get('/admin/menus/'.$menu->id)
        ->assertOk()
        ->assertSee('Home');
});

test('the menus list marks items whose page is unpublished', function () {
    $admin = app(SuperAdminBootstrap::class)->create('Administrator', 'admin@example.test', 'Safe-Test-Password9!');
    $page = app(PageManager::class)->create('en', 'draft', 'Draft', name: 'Draft page');
    $menus = app(MenuManager::class);
    $menu = $menus->create('main-menu');
    $menus->createItem('main-menu', 'en', $page->page_id, 'Draft');

    $this->actingAs($admin)->get('/admin/menus/'.$menu->id)
        ->assertOk()
        ->assertSee('Draft page')
        ->assertSee('Unpublished');
});

test('the menus list marks items whose translation is unpublished', function () {
    $admin = app(SuperAdminBootstrap::class)->create('Administrator', 'admin@example.test', 'Safe-Test-Password9!');
    $page = app(PageManager::class)->create('en', 'about', 'About', name: 'About page');
    app(PageManager::class)->publish($page->page_id, 'en');
    $menus = app(MenuManager::class);
    $menu = $menus->create('main-menu');
    $menus->createItem('main-menu', 'en', $page->page_id, 'About');
    app(PageManager::class)->unpublish($page->page_id, 'en');

    $this->actingAs($admin)->get('/admin/menus/'.$menu->id)
        ->assertOk()
        ->assertSee('About page')
        ->assertSee('Unpublished');
});

test('the public web does not download administrative editor assets', function () {
    $response = $this->get('/');

    $response->assertOk()
        ->assertDontSee('tinymce', false);
});

test('the editor assets are referenced only by the backoffice edit screen', function () {
    $edit = file_get_contents(base_path('Templates/Base/Resources/views/system/admin/pages/edit.blade.php'));
    $public = file_get_contents(base_path('Templates/Base/Resources/views/public/page/standard.blade.php'));

    expect($edit)->toContain('data-editor')
        ->and($edit)->toContain('page-edit.js')
        ->and($public)->not->toContain('tinymce')
        ->and($public)->not->toContain('js-editor');
});

test('the self-hosted TinyMCE editor explicitly uses the GPL license', function () {
    $editor = file_get_contents(base_path('resources/js/admin/tinymce.js'));

    expect($editor)->toContain("license_key: 'gpl'");
});

test('the page edit screen uses accessible tabs for up to five installed languages and keeps all forms available without JavaScript', function () {
    $admin = app(SuperAdminBootstrap::class)->create('Administrator', 'admin@example.test', 'Safe-Test-Password9!');
    foreach (['de', 'fr', 'it', 'pt'] as $locale) {
        DB::table('languages')->insert([
            'locale' => $locale, 'url_prefix' => $locale, 'name' => strtoupper($locale), 'native_name' => strtoupper($locale),
            'text_direction' => 'ltr', 'is_active' => true, 'is_url_general' => false, 'installed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
    $page = app(PageManager::class)->create('en', 'about', 'About', name: 'About');

    $this->actingAs($admin)->get('/admin/pages/'.$page->page_id)
        ->assertOk()
        ->assertSee('role="tablist"', false)
        ->assertSee('role="tab"', false)
        ->assertSee('role="tabpanel"', false)
        ->assertSee('data-translation-panel="en"', false)
        ->assertSee('data-translation-panel="de"', false)
        ->assertSee('<textarea', false)
        ->assertDontSee('data-language-select', false);
});

test('the page edit screen uses a searchable native selector from six installed languages', function () {
    $admin = app(SuperAdminBootstrap::class)->create('Administrator', 'admin@example.test', 'Safe-Test-Password9!');
    foreach (['de', 'fr', 'it', 'pt', 'nl'] as $locale) {
        DB::table('languages')->insert([
            'locale' => $locale, 'url_prefix' => $locale, 'name' => strtoupper($locale), 'native_name' => strtoupper($locale),
            'text_direction' => 'ltr', 'is_active' => true, 'is_url_general' => false, 'installed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
    $page = app(PageManager::class)->create('en', 'about', 'About', name: 'About');

    $this->actingAs($admin)->get('/admin/pages/'.$page->page_id)
        ->assertOk()
        ->assertSee('Current language:')
        ->assertSee('data-language-search', false)
        ->assertSee('data-language-select', false)
        ->assertDontSee('role="tablist"', false);
});

test('the page edit screen activates the configured locale and restores the submitted locale after an error', function () {
    $admin = app(SuperAdminBootstrap::class)->create('Administrator', 'admin@example.test', 'Safe-Test-Password9!');
    $spanishId = DB::table('languages')->insertGetId([
        'locale' => 'es_ES', 'url_prefix' => 'es-es', 'name' => 'Spanish', 'native_name' => 'Español',
        'text_direction' => 'ltr', 'is_active' => true, 'is_url_general' => false, 'installed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('language_settings')->where('id', 1)->update(['backoffice_default_language_id' => $spanishId]);
    $page = app(PageManager::class)->create('en', 'about', 'About', name: 'About');

    $this->actingAs($admin)->get('/admin/pages/'.$page->page_id)
        ->assertOk()
        ->assertSee('data-active-locale="es_ES"', false);
    $this->actingAs($admin)->post('/admin/pages/'.$page->page_id, [
        'locale' => 'en', 'slug' => 'about', 'title' => 'About',
        'content' => '<cms-embed data-type="../../secret" data-ref="x"></cms-embed>',
    ])->assertRedirect('/admin/pages/'.$page->page_id)->assertSessionHasErrors('operation');
    $this->actingAs($admin)->get('/admin/pages/'.$page->page_id)
        ->assertOk()
        ->assertSee('data-active-locale="en"', false);
});

test('the page edit screen localizes each translation status and keeps locale headings only as progressive enhancement fallbacks', function () {
    $admin = app(SuperAdminBootstrap::class)->create('Administrator', 'admin@example.test', 'Safe-Test-Password9!');
    $spanishId = DB::table('languages')->insertGetId([
        'locale' => 'es_ES', 'url_prefix' => 'es-es', 'name' => 'Spanish', 'native_name' => 'Español',
        'text_direction' => 'ltr', 'is_active' => true, 'is_url_general' => false, 'installed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);
    $page = app(PageManager::class)->create('en', 'about', 'About', name: 'About');
    app(PageManager::class)->publish($page->page_id, 'en');

    $panelStatus = function ($response, string $locale): string {
        $document = new DOMDocument;
        $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($document);
        $status = $xpath->query('//*[@data-translation-panel="'.$locale.'"]//*[contains(concat(" ", normalize-space(@class), " "), " c-admintranslation__status ")]');

        expect($status->length)->toBe(1);

        return trim($status->item(0)->textContent);
    };

    $englishResponse = $this->actingAs($admin)->get('/admin/pages/'.$page->page_id)
        ->assertOk()
        ->assertSee('English (en)')
        ->assertSee('Español (es_ES)')
        ->assertSee('class="c-admintranslation__fallback-heading"', false)
        ->assertSee('class="c-adminsection c-admintranslations js-page-edit"', false);

    expect($panelStatus($englishResponse, 'en'))->toBe('Published')
        ->and($panelStatus($englishResponse, 'es_ES'))->toBe('Unpublished');

    DB::table('language_settings')->where('id', 1)->update(['backoffice_default_language_id' => $spanishId]);
    app()->setLocale('es_ES');

    $spanishResponse = $this->actingAs($admin)->get('/admin/pages/'.$page->page_id)->assertOk();

    expect($panelStatus($spanishResponse, 'en'))->toBe('Publicada')
        ->and($panelStatus($spanishResponse, 'es_ES'))->toBe('Despublicada');
});

test('page editor and assets are isolated to the active administration screen', function () {
    $edit = file_get_contents(base_path('Templates/Base/Resources/views/system/admin/pages/edit.blade.php'));
    $login = file_get_contents(base_path('Templates/Base/Resources/views/system/auth/login.blade.php'));
    $public = file_get_contents(base_path('Templates/Base/Resources/views/public/page/standard.blade.php'));
    $pageEdit = file_get_contents(base_path('resources/js/admin/page-edit.js'));
    $pageEditController = file_get_contents(base_path('resources/js/admin/page-edit-controller.js'));
    $tinyMce = file_get_contents(base_path('resources/js/admin/tinymce.js'));
    $vite = file_get_contents(base_path('vite.config.js'));

    expect($edit)->toContain('resources/css/admin.css')
        ->and($edit)->toContain('resources/js/admin/page-edit.js')
        ->and($login)->toContain('resources/css/admin.css')
        ->and($login)->toContain('c-adminform')
        ->and($public)->toContain('resources/css/public.css')
        ->and($public)->not->toContain('admin.css')
        ->and($public)->not->toContain('page-edit')
        ->and($pageEdit)->toContain("import('./tinymce')")
        ->and($pageEditController)->toContain('saveAndDestroy')
        ->and($tinyMce)->toContain('editor.save()')
        ->and($tinyMce)->toContain('editor.remove()')
        ->and($vite)->toContain('resources/css/admin.css')
        ->and($vite)->toContain('resources/css/public.css')
        ->and($vite)->not->toContain('resources/js/app.js');
});
