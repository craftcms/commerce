<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers\Concerns;

use CraftCms\Cms\Shared\Enums\Color;

trait HasColorPalette
{
    /**
     * The color values shared by any component keyed to {@see Color} (tax and shipping
     * categories, order statuses), for a {@see \CraftCms\Cms\Ui\Controls\ColorSelect}
     * control's `->colors()` — narrows its default (the shared UI palette, which includes a
     * couple of colors outside this enum) down to exactly what {@see Color::tryFrom()} accepts.
     *
     * @return list<string>
     */
    protected function colorPalette(): array
    {
        return array_map(fn(Color $color) => $color->value, Color::cases());
    }
}
