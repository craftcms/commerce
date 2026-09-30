<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Form\Controls;

use CraftCms\Cms\Form\ControlPayload;
use CraftCms\Cms\Form\Controls\Control;
use CraftCms\Cms\Form\FormHtmlRenderer;
use Override;

use function CraftCms\Cms\t;

/**
 * Edits a draft transfer's items: one row per inventory item with its quantity, plus an “Add an item” picker.
 *
 * The value is the transfer's details keyed by UID — `{uid: {id, uid, inventoryItemId, quantity}}` — the shape
 * {@see \CraftCms\Commerce\Transfer\Elements\Transfer::setDetails()} takes.
 */
class TransferDetails extends Control
{
    /** @var array<int|string, string> */
    private array $itemsHtml = [];

    /** @var list<array{label: string, value: string, disabled: bool}> */
    private array $options = [];

    public static function renderHtml(ControlPayload $control, mixed $value, array $attributes, FormHtmlRenderer $renderer): string
    {
        return '';
    }

    public function component(): string
    {
        return 'commerce:transfer-details';
    }

    /**
     * @param array<int|string, string> $itemsHtml Each inventory item's chip HTML, keyed by inventory item ID.
     */
    public function itemsHtml(array $itemsHtml): static
    {
        $this->itemsHtml = $itemsHtml;

        return $this;
    }

    /**
     * @param list<array{label: string, value: string, disabled: bool}> $options The items that can be added.
     */
    public function options(array $options): static
    {
        $this->options = $options;

        return $this;
    }

    #[Override]
    public function emptyValue(): mixed
    {
        return [];
    }

    #[Override]
    public function props(mixed $value = null): array
    {
        return [
            'itemsHtml' => $this->itemsHtml,
            'options' => $this->options,
            'labels' => [
                'item' => t('Inventory Item', category: 'commerce'),
                'quantity' => t('Quantity', category: 'commerce'),
                'total' => t('Total', category: 'commerce'),
                'add' => t('Add an item', category: 'commerce'),
                'select' => t('Select an item', category: 'commerce'),
                'remove' => t('Delete'),
            ],
        ];
    }
}
