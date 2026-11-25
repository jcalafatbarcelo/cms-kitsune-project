<?php

namespace Modules\Core\Localization\Services;

use App\Models\AdministrationAccess;
use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Support\Facades\App;
use Modules\Core\Localization\Exceptions\UiCatalogOverrideException;
use Throwable;

class UiCatalogActorResolver
{
    public function __construct(
        private readonly Auth $auth,
        private readonly ?bool $runningInConsole = null,
    ) {}

    public function current(): string
    {
        $user = $this->auth->guard('web')->user();

        if ($user !== null) {
            try {
                $permitted = AdministrationAccess::permits($user);
            } catch (Throwable $exception) {
                throw new UiCatalogOverrideException(
                    'Superadministrator access could not be verified.',
                    previous: $exception,
                );
            }

            if (! $permitted) {
                throw new UiCatalogOverrideException(
                    'Only the superadministrator can mutate UI catalog overrides.',
                );
            }

            return 'user:'.$user->getAuthIdentifier();
        }

        return ($this->runningInConsole ?? App::runningInConsole()) ? 'console' : 'system';
    }
}
