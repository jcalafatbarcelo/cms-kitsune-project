<?php

namespace Modules\Pages\Contracts;

interface ContentEditor
{
    public function identifier(): string;

    public function sanitize(string $html, SanitizationPolicy $policy): string;
}
