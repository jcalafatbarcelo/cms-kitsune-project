<?php

namespace Modules\Navigation\View\Components;

use Illuminate\View\Component;
use Modules\Navigation\Services\PublicMenuResolver;

class NavigationMenu extends Component
{
    /** @var array<int, array{id: int, label: string, url: string, children: array}> */
    public array $items;

    public function __construct(string $identifier, string $locale, PublicMenuResolver $menus)
    {
        $this->items = $menus->forMenu($identifier, $locale);
    }

    public function render(): string
    {
        return 'navigation::components.menu';
    }
}
