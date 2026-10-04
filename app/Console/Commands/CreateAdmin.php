<?php

namespace App\Console\Commands;

use App\Models\AdministrationAccess;
use App\Services\Admin\SuperAdminBootstrap;
use DomainException;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

class CreateAdmin extends Command
{
    protected $signature = 'cms:admin:create';

    protected $description = 'Interactively create the first and only superadministrator';

    public function handle(SuperAdminBootstrap $bootstrap): int
    {
        if (AdministrationAccess::query()->whereKey(1)->whereNotNull('super_admin_user_id')->exists()) {
            $this->error('A superadministrator already exists.');

            return self::FAILURE;
        }
        if (! $this->input->isInteractive()) {
            $this->error('An interactive terminal is required.');

            return self::FAILURE;
        }
        $name = (string) $this->ask('Name');
        $email = (string) $this->ask('Email');
        $password = (string) $this->secret('Password', false);
        $confirmation = (string) $this->secret('Confirm password', false);
        if ($password !== $confirmation) {
            $this->error('Passwords do not match.');

            return self::FAILURE;
        }
        try {
            $bootstrap->create($name, $email, $password);
        } catch (ValidationException) {
            $this->error('Invalid name, email or password. Use a unique email and a password of at least 15 characters, at most 72 UTF-8 bytes, with uppercase, lowercase, number and symbol.');

            return self::FAILURE;
        } catch (DomainException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } catch (QueryException) {
            $this->error('Bootstrap could not be completed. No partial user was created.');

            return self::FAILURE;
        }
        $this->info('Superadministrator created.');

        return self::SUCCESS;
    }
}
