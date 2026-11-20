<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Edit\Operations;

use CraftCms\Commerce\Order\Edit\Exceptions\OperationNotApplicableException;
use CraftCms\Commerce\Order\Edit\OrderOperation;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\OrderStockReconciliation;

use function CraftCms\Cms\t;

/**
 * @since 6.0.0
 */
final readonly class RemoveLineItem extends OrderOperation
{
    public function __construct(
        public string $lineItemUid,
    ) {
    }

    public static function type(): string
    {
        return 'removeLineItem';
    }

    public function label(): string
    {
        return t('Remove Line Item', category: 'commerce');
    }

    public static function rules(): array
    {
        return [
            'lineItemUid' => ['required', 'string'],
        ];
    }

    public static function fromArray(array $data): static
    {
        return new self($data['lineItemUid']);
    }

    public function toArray(): array
    {
        return ['lineItemUid' => $this->lineItemUid];
    }

    public function summary(Order $before): ?string
    {
        return $this->itemName($before, $this->lineItemUid) ?: null;
    }

    public function apply(Order $order): void
    {
        $lineItem = $this->lineItem($order, $this->lineItemUid);

        if ($order->isCompleted && app(OrderStockReconciliation::class)->fulfilledQuantity($lineItem) > 0) {
            throw new OperationNotApplicableException(t('{item} has fulfilled stock, so it can’t be removed.', ['item' => $lineItem->getDescription()], category: 'commerce'));
        }

        $order->removeLineItem($lineItem);
    }
}
