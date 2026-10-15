<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Edit\Forms;

use CraftCms\Cms\Support\Facades\I18N;
use CraftCms\Cms\Ui\Ui;
use CraftCms\Commerce\Order\Edit\OrderOperation;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\LineItem\Data\LineItem;

use function CraftCms\Cms\t;

/**
 * The focused form an order edit screen action opens, producing one {@see OrderOperation}.
 *
 * Its controls are named after the operation's own keys, so the operation's validation errors
 * land on the matching control.
 *
 * @since 6.0.0
 */
abstract readonly class OrderOperationForm
{
    /**
     * The type of the operation the form produces, which also names the form.
     */
    abstract public static function type(): string;

    /** @param array<string, mixed> $params */
    abstract public function title(Order $order, array $params): string;

    /** @param array<string, mixed> $params */
    abstract public function ui(Order $order, array $params): Ui;

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    abstract public function values(Order $order, array $params): array;

    /**
     * The operation data for the submitted values, before the operation's own validation.
     *
     * @param array<string, mixed> $input The submitted values along with the form's params.
     * @return array<string, mixed>
     */
    abstract public function operationData(Order $order, array $input): array;

    public function submitLabel(Order $order): string
    {
        return t('Save');
    }

    /**
     * A Money control's submitted value as a number, or `null` when it was left empty.
     */
    protected function money(mixed $value): mixed
    {
        if (is_array($value)) {
            $value = isset($value['value']) && $value['value'] !== ''
                ? I18N::normalizeNumber($value['value'], $value['locale'] ?? null)
                : null;
        }

        return $value === '' ? null : $value;
    }

    protected function nullableId(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : (string)$value;
    }

    /**
     * @param array<int|string, string> $list Labels by value.
     * @return list<array{label: string, value: string}>
     */
    protected function choiceOptions(array $list): array
    {
        return array_map(
            fn(int|string $value, string $label) => ['label' => $label, 'value' => (string)$value],
            array_keys($list),
            $list,
        );
    }

    /** @param array<string, mixed> $params */
    protected function lineItem(Order $order, array $params): LineItem
    {
        foreach ($order->getLineItems() as $lineItem) {
            if ($lineItem->uid === ($params['lineItemUid'] ?? null)) {
                return $lineItem;
            }
        }

        abort(404, t('The line item no longer exists.', category: 'commerce'));
    }
}
