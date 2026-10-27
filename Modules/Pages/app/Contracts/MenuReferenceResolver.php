<?php

namespace Modules\Pages\Contracts;

interface MenuReferenceResolver
{
    /** @return array<int, string> menu_item_id => label referencing the page */
    public function referencesForPage(int $pageId): array;
}
