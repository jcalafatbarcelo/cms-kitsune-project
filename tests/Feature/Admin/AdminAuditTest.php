<?php

use App\Models\AdminAuditEvent;
use App\Models\User;
use App\Services\Admin\AdminAuditLogger;
use App\Services\Admin\SuperAdminBootstrap;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Modules\Navigation\Services\MenuManager;
use Modules\Pages\Models\PageTranslation;
use Modules\Pages\Services\PageManager;

uses(RefreshDatabase::class);

test('page and menu mutations write one scoped audit event with allowed snapshots', function () {
    $pages = app(PageManager::class);
    $created = $pages->create('en', 'about', 'About');
    DB::table('languages')->insert([
        'locale' => 'es_ES', 'url_prefix' => 'es-es', 'name' => 'Spanish', 'native_name' => 'Spanish',
        'text_direction' => 'ltr', 'is_active' => true, 'installed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);
    $translation = $pages->translate($created->page_id, 'es_ES', 'acerca-de', 'Acerca de');
    $pages->publish($created->page_id, 'en');
    $pages->setHome($created->page_id, 'en');
    $replacement = $pages->create('en', 'contact', 'Contact');
    $pages->publish($replacement->page_id, 'en');
    $pages->setHome($replacement->page_id, 'en');
    $pages->unpublish($created->page_id, 'en');
    $menus = app(MenuManager::class);
    $menu = $menus->create('main-menu');
    $item = $menus->createItem('main-menu', 'en', $replacement->page_id, 'Home');
    $menus->createItem('main-menu', 'en', $replacement->page_id, 'Second');
    $menus->updateItem($item->id, $created->page_id, 'About');
    $menus->moveItem($item->id, 2);
    $menus->removeItem($item->id);

    $events = AdminAuditEvent::query()->orderBy('id')->get();

    expect($events->pluck('operation')->all())->toContain(
        'page.create', 'page.translate', 'page.publish', 'page.unpublish', 'page.set_home',
        'menu.create', 'menu.item.create', 'menu.item.update', 'menu.item.move', 'menu.item.remove',
    )->and($events->every(fn (AdminAuditEvent $event) => $event->actor_type === 'console' && $event->origin === 'console'))
        ->toBeTrue()
        ->and($events->firstWhere('operation', 'page.create')->after_state)->toMatchArray([
            'page_id' => $created->page_id,
            'slug' => 'about',
            'title' => 'About',
            'presentation_key' => 'public.page.standard',
        ])
        ->and($events->firstWhere('operation', 'menu.item.update')->before_state)->toMatchArray(['label' => 'Home', 'page_id' => $replacement->page_id])
        ->and($events->firstWhere('operation', 'menu.item.update')->after_state)->toMatchArray(['label' => 'About', 'page_id' => $created->page_id])
        ->and($events->firstWhere('operation', 'menu.item.remove')->after_state)->toBeNull()
        ->and($events->where('operation', 'page.create'))->toHaveCount(2)
        ->and($events->where('operation', 'page.translate'))->toHaveCount(1)
        ->and($events->where('operation', 'page.publish'))->toHaveCount(2)
        ->and($events->where('operation', 'page.unpublish'))->toHaveCount(1)
        ->and($events->where('operation', 'page.set_home'))->toHaveCount(2)
        ->and($events->where('operation', 'menu.create'))->toHaveCount(1)
        ->and($events->where('operation', 'menu.item.create'))->toHaveCount(2)
        ->and($events->where('operation', 'menu.item.update'))->toHaveCount(1)
        ->and($events->where('operation', 'menu.item.move'))->toHaveCount(1)
        ->and($events->where('operation', 'menu.item.remove'))->toHaveCount(1)
        ->and($menu->identifier)->toBe('main-menu')
        ->and($translation->title)->toBe('Acerca de');
});

test('no effective page or menu item change creates an audit event', function () {
    $page = app(PageManager::class)->create('en', 'about', 'About');
    app(PageManager::class)->publish($page->page_id, 'en');
    $item = app(MenuManager::class)->create('main-menu');
    $menuItem = app(MenuManager::class)->createItem($item->identifier, 'en', $page->page_id, 'About');
    $before = AdminAuditEvent::query()->count();

    app(PageManager::class)->publish($page->page_id, 'en');
    app(MenuManager::class)->updateItem($menuItem->id, $page->page_id, 'About');
    app(MenuManager::class)->moveItem($menuItem->id, 1);

    expect(AdminAuditEvent::query()->count())->toBe($before);
});

test('a failed audit write rolls back the domain mutation', function () {
    $audit = Mockery::mock(AdminAuditLogger::class);
    $audit->shouldReceive('record')->once()->andThrow(new RuntimeException('Audit destination unavailable'));
    $this->app->instance(AdminAuditLogger::class, $audit);
    $this->app->forgetInstance(PageManager::class);

    expect(fn () => app(PageManager::class)->create('en', 'about', 'About'))->toThrow(RuntimeException::class)
        ->and(PageTranslation::query()->where('slug', 'about')->exists())->toBeFalse();
});

test('audit events are immutable and retain a deleted user actor without a foreign key', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $page = app(PageManager::class)->create('en', 'about', 'About');
    $event = AdminAuditEvent::query()->sole();

    expect($event->actor_type)->toBe('user')
        ->and($event->actor_id)->toBe($user->id)
        ->and($event->actor_label)->toBe('user:'.$user->id)
        ->and(fn () => $event->update(['operation' => 'menu.create']))->toThrow(LogicException::class)
        ->and(fn () => $event->delete())->toThrow(LogicException::class)
        ->and(fn () => DB::table('admin_audit_events')->where('id', $event->id)->update(['operation' => 'page.create']))->toThrow(QueryException::class)
        ->and(fn () => DB::table('admin_audit_events')->where('id', $event->id)->delete())->toThrow(QueryException::class);

    $user->delete();

    expect(AdminAuditEvent::query()->findOrFail($event->id)->actor_label)->toBe('user:'.$user->id)
        ->and(Schema::getForeignKeys('admin_audit_events'))->toBe([])
        ->and($page->slug)->toBe('about');
});

test('only the superadministrator can page and filter audit history', function () {
    $admin = app(SuperAdminBootstrap::class)->create('Administrator', 'admin@example.test', 'Safe-Test-Password9!');
    AdminAuditEvent::query()->create([
        'occurred_at' => now(), 'operation' => 'page.create', 'entity_type' => 'page', 'entity_id' => 1,
        'actor_type' => 'system', 'actor_label' => 'system', 'origin' => 'system', 'after_state' => ['title' => 'Safe'], 'schema_version' => 1,
    ]);
    AdminAuditEvent::query()->create([
        'occurred_at' => now()->subDay(), 'operation' => 'menu.create', 'entity_type' => 'menu', 'entity_id' => 2,
        'actor_type' => 'console', 'actor_label' => 'console', 'origin' => 'console', 'after_state' => ['identifier' => 'main-menu'], 'schema_version' => 1,
    ]);
    app()->setLocale('en');
    $validationMessage = __('validation.in', ['attribute' => 'operation']);

    expect($validationMessage)->toBe('The selected operation is invalid.');

    $this->get('/admin/audit')->assertRedirect('/admin/login');
    $this->actingAs(User::factory()->create())->get('/admin/audit')->assertForbidden();
    $this->actingAs($admin)->get('/admin/audit?operation=page.create&entity_type=page&per_page=1')
        ->assertOk()->assertSee('Audit history')->assertSee('page.create')->assertDontSee('menu.create');
    $this->actingAs($admin)->get('/admin/audit?from='.now()->subMinute()->format('Y-m-d\TH:i'))
        ->assertOk()->assertSee('page.create')->assertDontSee('menu.create');
    $this->actingAs($admin)
        ->from('/admin/audit')
        ->get('/admin/audit?operation=invalid&entity_type=page')
        ->assertRedirect('/admin/audit')
        ->assertSessionHasErrors('operation');
    $this->followingRedirects()
        ->from('/admin/audit')
        ->get('/admin/audit?operation=invalid&entity_type=page')
        ->assertOk()
        ->assertSee('role="alert"', false)
        ->assertSee('<ul', false)
        ->assertSee($validationMessage)
        ->assertDontSee('validation.in')
        ->assertSee('name="entity_type" value="page"', false);
});
