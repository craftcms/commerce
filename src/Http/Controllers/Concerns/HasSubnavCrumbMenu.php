<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers\Concerns;

use CraftCms\Cms\Cp\Data\NavItem;

/**
 * Turns a screen's subnav into the switcher menu on its section crumb, with the subnav's
 * groups as headings — the way an element index's source crumb lists its sources.
 */
trait HasSubnavCrumbMenu
{
    /**
     * @param NavItem[] $subnav
     * @return list<array<string, mixed>>
     */
    protected function subnavCrumbMenu(array $subnav, ?string $selectedUrl): array
    {
        $link = fn(NavItem $item) => [
            'type' => 'link',
            'label' => $item->label,
            'href' => $item->href,
            'selected' => $item->href === $selectedUrl,
        ];

        return array_values(array_map(fn(NavItem $item) => $item->group
            ? ['type' => 'group', 'heading' => $item->label, 'items' => array_map($link, $item->subnav ?: [])]
            : $link($item), $subnav));
    }
}
