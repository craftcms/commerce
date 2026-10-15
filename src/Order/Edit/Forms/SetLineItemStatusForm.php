<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Edit\Forms;

use CraftCms\Cms\Ui\Controls\Choice;
use CraftCms\Cms\Ui\Enums\ChoicePresentation;
use CraftCms\Cms\Ui\Nodes\Field;
use CraftCms\Cms\Ui\Ui;
use CraftCms\Commerce\Order\Data\LineItemStatus;
use CraftCms\Commerce\Order\Edit\Operations\SetLineItemStatus;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\LineItemStatuses;

use function CraftCms\Cms\t;

/**
 * @since 6.0.0
 */
final readonly class SetLineItemStatusForm extends OrderOperationForm
{
    public static function type(): string
    {
        return SetLineItemStatus::type();
    }

    public function title(Order $order, array $params): string
    {
        return t('Set Status for {item}', ['item' => $this->lineItem($order, $params)->getDescription()], category: 'commerce');
    }

    public function ui(Order $order, array $params): Ui
    {
        $statuses = app(LineItemStatuses::class)->getAllLineItemStatuses($order->storeId)
            ->map(fn(LineItemStatus $status) => [
                'label' => $status->getUiLabel(),
                'value' => (string)$status->id,
            ])
            ->values()
            ->all();

        return Ui::make([
            Field::make(t('Status', category: 'commerce'), Choice::make('lineItemStatusId')
                ->presentation(ChoicePresentation::Radios)
                ->options([['label' => t('None', category: 'commerce'), 'value' => ''], ...$statuses])),
        ]);
    }

    public function values(Order $order, array $params): array
    {
        return ['lineItemStatusId' => (string)$this->lineItem($order, $params)->lineItemStatusId];
    }

    public function operationData(Order $order, array $input): array
    {
        return [
            'lineItemUid' => $this->lineItem($order, $input)->uid,
            'lineItemStatusId' => $this->nullableId($input['lineItemStatusId'] ?? null),
        ];
    }
}
