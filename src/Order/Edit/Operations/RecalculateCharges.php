<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Edit\Operations;

use CraftCms\Commerce\Order\Data\OrderAdjustment;
use CraftCms\Commerce\Order\Edit\Exceptions\OperationNotApplicableException;
use CraftCms\Commerce\Order\Edit\OrderOperation;
use CraftCms\Commerce\Order\Elements\Order;

use function CraftCms\Cms\t;

/**
 * Regenerates a completed order's shipping, tax and discount adjustments at this point in the edit,
 * keeping line item prices and any adjustments staff added by hand.
 *
 * The generated adjustments are worked out without the manual ones, so a manual discount doesn't
 * reduce the tax.
 *
 * @since 6.0.0
 */
final readonly class RecalculateCharges extends OrderOperation
{
    public static function type(): string
    {
        return 'recalculateCharges';
    }

    public function label(): string
    {
        return t('Recalculate Charges', category: 'commerce');
    }

    public static function rules(): array
    {
        return [];
    }

    public static function fromArray(array $data): static
    {
        return new self();
    }

    public function toArray(): array
    {
        return [];
    }

    public function apply(Order $order): void
    {
        $this->requireCompletedOrder($order);

        if ($order->shippingMethodHandle && !isset($order->getAvailableShippingMethodOptions()[$order->shippingMethodHandle])) {
            throw new OperationNotApplicableException(t('The shipping method isn’t available for this order, so shipping can’t be recalculated.', category: 'commerce'));
        }

        $manual = array_values(array_filter($order->getAdjustments() ?? [], fn(OrderAdjustment $adjustment) => $adjustment->isManual()));

        $order->setRecalculationMode(Order::RECALCULATION_MODE_ADJUSTMENTS_ONLY);
        $order->recalculate();
        $order->setRecalculationMode(Order::RECALCULATION_MODE_NONE);
        $order->setAdjustments([...$order->getAdjustments() ?? [], ...$manual]);
    }
}
