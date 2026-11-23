<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Edit\Forms;

use CraftCms\Cms\FieldLayout\FieldLayoutCompiler;
use CraftCms\Cms\Ui\Ui;
use CraftCms\Cms\Ui\UiContext;
use CraftCms\Commerce\Order\Edit\Operations\SetFieldValues;
use CraftCms\Commerce\Order\Elements\Order;

use function CraftCms\Cms\t;

/**
 * Edits the custom fields of the order's field layout.
 *
 * @since 6.0.0
 */
final readonly class SetFieldValuesForm extends OrderOperationForm
{
    public static function type(): string
    {
        return SetFieldValues::type();
    }

    public function title(Order $order, array $params): string
    {
        return t('Edit Fields', category: 'commerce');
    }

    public function ui(Order $order, array $params): Ui
    {
        return app(FieldLayoutCompiler::class)->ui($order->getFieldLayout(), $order, new UiContext());
    }

    public function values(Order $order, array $params): array
    {
        return ['fields' => $order->getSerializedFieldValues()];
    }

    public function operationData(Order $order, array $input): array
    {
        return ['values' => $input['fields'] ?? []];
    }
}
