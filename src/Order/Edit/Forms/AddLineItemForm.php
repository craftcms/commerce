<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Edit\Forms;

use CraftCms\Cms\Ui\Ui;
use CraftCms\Commerce\Order\Edit\Operations\AddLineItem;
use CraftCms\Commerce\Order\Elements\Order;
use Illuminate\Support\Str;
use LogicException;

use function CraftCms\Cms\t;

/**
 * Adds the purchasables chosen in the order edit screen's purchasables table, each with its own quantity.
 *
 * The table is Commerce's own Vue component, not a server-defined UI, so the form has no controls. It
 * submits `items`, a list of `{purchasableId, qty}`, and produces one operation for each.
 *
 * @since 6.0.0
 */
final readonly class AddLineItemForm extends OrderOperationForm
{
    public static function type(): string
    {
        return AddLineItem::type();
    }

    public function title(Order $order, array $params): string
    {
        return t('Add a line item', category: 'commerce');
    }

    public function submitLabel(Order $order): string
    {
        return t('Add', category: 'commerce');
    }

    public function ui(Order $order, array $params): Ui
    {
        return Ui::make([]);
    }

    public function values(Order $order, array $params): array
    {
        return ['items' => []];
    }

    public function operationData(Order $order, array $input): array
    {
        throw new LogicException('The form adds several line items. Use operationsData().');
    }

    public function operationsData(Order $order, array $input): array
    {
        $items = array_values(array_filter((array)($input['items'] ?? []), is_array(...)));

        return array_map(fn(array $item) => [
            'uid' => (string)Str::uuid(),
            'purchasableId' => $item['purchasableId'] ?? null,
            'qty' => $item['qty'] ?? null,
        ], $items);
    }

    public function errorPrefix(int $index): string
    {
        return "items.$index.";
    }
}
