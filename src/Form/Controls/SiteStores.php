<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Form\Controls;

use CraftCms\Cms\Form\ControlPayload;
use CraftCms\Cms\Form\Controls\Control;
use CraftCms\Cms\Form\FormHtmlRenderer;
use CraftCms\Cms\Form\Nodes\Table;

class SiteStores extends Control
{
    private Table $table;

    public function table(Table $table): static
    {
        $this->table = $table;

        return $this;
    }

    public static function renderHtml(ControlPayload $control, mixed $value, array $attributes, FormHtmlRenderer $renderer): string
    {
        return '';
    }

    public function component(): string
    {
        return 'commerce:site-stores';
    }

    public function emptyValue(): mixed
    {
        return [];
    }

    public function props(mixed $value = null): array
    {
        return $this->table->props();
    }
}
