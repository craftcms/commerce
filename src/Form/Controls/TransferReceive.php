<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Form\Controls;

use CraftCms\Cms\Ui\ControlPayload;
use CraftCms\Cms\Ui\Controls\Control;
use CraftCms\Cms\Ui\UiHtmlRenderer;
use Override;

use function CraftCms\Cms\t;

/**
 * Receives a pending transfer's items: one row per item with what's been accepted and rejected so far, and
 * how many more to accept or reject.
 *
 * The value is keyed by detail UID — `{uid: {accept, reject}}` — the shape
 * {@see \CraftCms\Commerce\Transfer\Transfers::receive()} takes.
 */
class TransferReceive extends Control
{
    /** @var list<array{uid: string, label: string, quantity: int, accepted: int, rejected: int, deletedMessage: ?string}> */
    private array $rows = [];

    public static function renderHtml(ControlPayload $control, mixed $value, array $attributes, UiHtmlRenderer $renderer): string
    {
        return '';
    }

    public function component(): string
    {
        return 'commerce:transfer-receive';
    }

    /**
     * @param list<array{uid: string, label: string, quantity: int, accepted: int, rejected: int, deletedMessage: ?string}> $rows
     *     `deletedMessage` is set when the item's inventory item has since been deleted, which stops it being received.
     */
    public function rows(array $rows): static
    {
        $this->rows = $rows;

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
            'rows' => $this->rows,
            'labels' => [
                'item' => t('Item', category: 'commerce'),
                'accepted' => t('Accepted', category: 'commerce'),
                'accept' => t('Accept', category: 'commerce'),
                'rejected' => t('Rejected', category: 'commerce'),
                'reject' => t('Reject', category: 'commerce'),
            ],
        ];
    }
}
