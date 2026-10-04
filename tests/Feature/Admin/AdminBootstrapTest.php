<?php

use App\Models\User;
use App\Services\Admin\SuperAdminBootstrap;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

test('bootstrap creates the only superadministrator and hashes credentials', function () {
    $user = app(SuperAdminBootstrap::class)->create('Administrator', ' ADMIN@example.test ', 'Safe-Test-Password9!');

    expect($user->email)->toBe('admin@example.test')
        ->and(Hash::check('Safe-Test-Password9!', $user->password))->toBeTrue()
        ->and(DB::table('administration_access')->sole()->super_admin_user_id)->toBe($user->id);

    expect(fn () => app(SuperAdminBootstrap::class)->create('Other', 'other@example.test', 'Safe-Test-Password9!'))
        ->toThrow(DomainException::class);
    expect(User::count())->toBe(1);
})->group('database-integration');

test('administrative singleton rejects deletion and identity changes', function () {
    expect(DB::table('administration_access')->sole()->super_admin_user_id)->toBeNull();
    expect(fn () => DB::table('administration_access')->delete())->toThrow(QueryException::class);
    expect(fn () => DB::table('administration_access')->update(['singleton' => 2]))->toThrow(QueryException::class);
    expect(fn () => DB::table('administration_access')->insert(['singleton' => 2]))->toThrow(QueryException::class);
})->group('database-integration');

test('bootstrap rejects invalid password boundaries without creating a user', function (string $password) {
    expect(fn () => app(SuperAdminBootstrap::class)->create('Administrator', 'admin@example.test', $password))
        ->toThrow(ValidationException::class);
    expect(User::count())->toBe(0);
})->with(['short' => 'Short9!', 'over bytes' => str_repeat('é', 35).'Ab9!', 'no symbol' => 'LongPasswordWithoutSymbol9', 'null byte' => "Safe-Test-Password9!\0"]);

test('bootstrap accepts exactly 72 bytes and prevents deleting the referenced user', function () {
    $user = app(SuperAdminBootstrap::class)->create('Administrator', 'admin@example.test', str_repeat('é', 34).'Ab9!');
    expect(fn () => $user->delete())->toThrow(QueryException::class);
})->group('database-integration');

test('bootstrap does not elevate or replace an existing email', function () {
    $user = User::factory()->create(['email' => 'admin@example.test']);
    expect(fn () => app(SuperAdminBootstrap::class)->create('Administrator', 'ADMIN@example.test', 'Safe-Test-Password9!'))
        ->toThrow(ValidationException::class);
    expect(User::count())->toBe(1)
        ->and(DB::table('administration_access')->sole()->super_admin_user_id)->toBeNull();
})->group('database-integration');

test('interactive command creates the superadministrator and refuses a second bootstrap', function () {
    $this->artisan('cms:admin:create')
        ->expectsQuestion('Name', 'Administrator')
        ->expectsQuestion('Email', 'admin@example.test')
        ->expectsQuestion('Password', 'Safe-Test-Password9!')
        ->expectsQuestion('Confirm password', 'Safe-Test-Password9!')
        ->expectsOutputToContain('Superadministrator created.')
        ->assertSuccessful();
    $this->artisan('cms:admin:create')->expectsOutputToContain('already exists')->assertFailed();
});

test('mismatched confirmation and unattended bootstrap leave identity untouched', function () {
    $this->artisan('cms:admin:create')
        ->expectsQuestion('Name', 'Administrator')
        ->expectsQuestion('Email', 'admin@example.test')
        ->expectsQuestion('Password', 'Safe-Test-Password9!')
        ->expectsQuestion('Confirm password', 'Different-Password9!')
        ->expectsOutputToContain('Passwords do not match')->assertFailed();
    $this->artisan('cms:admin:create', ['--no-interaction' => true])->assertFailed();
    expect(User::count())->toBe(0);
});
