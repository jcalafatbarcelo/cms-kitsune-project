<?php

namespace Modules\Pages\Contracts;

interface HomePageLocator
{
    /** @return array<int, int> language_id => page_id for every assigned home */
    public function homePageIds(): array;
}
