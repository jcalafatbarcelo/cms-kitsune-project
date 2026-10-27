<?php

namespace Modules\Core\Language\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Modules\Core\Language\Models\Language;

class LanguageInstalled
{
    use Dispatchable;

    public function __construct(public readonly Language $language) {}
}
