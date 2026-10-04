<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('collation equivalent emails cannot obtain independent authentication budgets', function () {
    User::factory()->create(['email' => 'admin@example.test']);
    if (! User::query()->where('email', 'ádmin@example.test')->exists()) {
        $this->markTestSkipped('This database collation treats the two email identities as distinct.');
    }
    for ($i = 0; $i < 5; $i++) {
        $this->post('/admin/login', ['email' => 'admin@example.test', 'password' => 'Wrong-Test-Password9!'])->assertSessionHasErrors('email');
    }
    $this->post('/admin/login', ['email' => 'ádmin@example.test', 'password' => 'Wrong-Test-Password9!'])->assertStatus(429);
})->group('database-integration');
