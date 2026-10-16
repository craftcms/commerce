<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Edit\Operations;

use CraftCms\Commerce\Order\Data\OrderAdjustment;
use CraftCms\Commerce\Order\Edit\Exceptions\OperationNotApplicableException;
use CraftCms\Commerce\Order\Edit\OrderOperation;
use CraftCms\Commerce\Order\Elements\Order;

use function CraftCms\Cms\t;

/**
 * Changes a saved adjustment on a completed order. Only the keys present in `changes` are applied.
 *
 * @since 6.0.0
 */
final readonly class UpdateAdjustment extends OrderOperation
{
    /**
     * @param array{
     *     adjustmentType?: string,
     *     name?: string,
     *     description?: string|null,
     *     amount?: float,
     *     included?: bool,
     * } $changes
     */
    public function __construct(
        public int $adjustmentId,
        public array $changes,
    ) {
    }

    public static function type(): string
    {
        return 'updateAdjustment';
    }

    public function label(): string
    {
        return t('Update Adjustment', category: 'commerce');
    }

    public static function rules(): array
    {
        return [
            'adjustmentId' => ['required', 'integer'],
            'changes' => ['required', 'array'],
            'changes.adjustmentType' => ['in:' . implode(',', AddAdjustment::TYPES)],
            'changes.name' => ['string'],
            'changes.description' => ['nullable', 'string'],
            'changes.amount' => ['numeric'],
            'changes.included' => ['boolean'],
        ];
    }

    public static function fromArray(array $data): static
    {
        $changes = array_intersect_key($data['changes'], array_flip(['adjustmentType', 'name', 'description', 'amount', 'included']));

        if (isset($changes['amount'])) {
            $changes['amount'] = (float)$changes['amount'];
        }

        if (isset($changes['included'])) {
            $changes['included'] = (bool)$changes['included'];
        }

        return new self((int)$data['adjustmentId'], $changes);
    }

    public function toArray(): array
    {
        return [
            'adjustmentId' => $this->adjustmentId,
            'changes' => $this->changes,
        ];
    }

    public function apply(Order $order): void
    {
        $this->requireCompletedOrder($order);

        $adjustment = self::find($order, $this->adjustmentId);

        if (isset($this->changes['adjustmentType'])) {
            $adjustment->type = $this->changes['adjustmentType'];
        }

        if (isset($this->changes['name'])) {
            $adjustment->name = $this->changes['name'];
        }

        if (array_key_exists('description', $this->changes)) {
            $adjustment->description = $this->changes['description'];
        }

        if (isset($this->changes['amount'])) {
            $adjustment->amount = $this->changes['amount'];
        }

        if (isset($this->changes['included'])) {
            $adjustment->included = $this->changes['included'];
        }
    }

    public static function find(Order $order, int $adjustmentId): OrderAdjustment
    {
        foreach ($order->getAdjustments() ?? [] as $adjustment) {
            if ($adjustment->id === $adjustmentId) {
                return $adjustment;
            }
        }

        throw new OperationNotApplicableException(t('The adjustment no longer exists.', category: 'commerce'));
    }
}
