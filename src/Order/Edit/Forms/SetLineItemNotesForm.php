<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Edit\Forms;

use CraftCms\Cms\Ui\Controls\Textarea;
use CraftCms\Cms\Ui\Nodes\Field;
use CraftCms\Cms\Ui\Ui;
use CraftCms\Commerce\Order\Edit\Operations\SetLineItemNotes;
use CraftCms\Commerce\Order\Elements\Order;

use function CraftCms\Cms\t;

/**
 * @since 6.0.0
 */
final readonly class SetLineItemNotesForm extends OrderOperationForm
{
    public static function type(): string
    {
        return SetLineItemNotes::type();
    }

    public function title(Order $order, array $params): string
    {
        return t('Notes for {item}', ['item' => $this->lineItem($order, $params)->getDescription()], category: 'commerce');
    }

    public function ui(Order $order, array $params): Ui
    {
        return Ui::make([
            Field::make(t('Note', category: 'commerce'), Textarea::make('note')->rows(3))
                ->instructions(t('Shown to the customer.', category: 'commerce')),
            Field::make(t('Private Note', category: 'commerce'), Textarea::make('privateNote')->rows(3))
                ->instructions(t('Only shown in the control panel.', category: 'commerce')),
        ]);
    }

    public function values(Order $order, array $params): array
    {
        $lineItem = $this->lineItem($order, $params);

        return [
            'note' => $lineItem->note,
            'privateNote' => $lineItem->privateNote,
        ];
    }

    public function operationData(Order $order, array $input): array
    {
        return [
            'lineItemUid' => $this->lineItem($order, $input)->uid,
            'note' => (string)($input['note'] ?? ''),
            'privateNote' => (string)($input['privateNote'] ?? ''),
        ];
    }
}
