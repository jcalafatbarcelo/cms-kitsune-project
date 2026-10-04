<?php

namespace App\Services\Admin;

use App\Models\AdministrationAccess;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class SuperAdminBootstrap
{
    public function create(string $name, string $email, #[\SensitiveParameter] string $password): User
    {
        $email = AdminCredentials::email($email);
        $name = trim($name);
        Validator::make(compact('name', 'email', 'password'), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255', 'unique:users,email'],
            'password' => AdminCredentials::passwordRules(),
        ])->validate();

        $hash = Hash::driver('bcrypt')->make($password);

        return DB::transaction(function () use ($name, $email, $hash): User {
            // A write before any read acquires SQLite's writer lock as well as an InnoDB row lock.
            AdministrationAccess::query()->whereKey(1)->update(['singleton' => 1]);
            $access = AdministrationAccess::query()->lockForUpdate()->findOrFail(1);
            if ($access->super_admin_user_id !== null) {
                throw new DomainException('A superadministrator already exists.');
            }
            $user = User::query()->create(['name' => $name, 'email' => $email, 'password' => $hash]);
            $access->super_admin_user_id = $user->id;
            $access->save();

            return $user;
        }, 3);
    }
}
