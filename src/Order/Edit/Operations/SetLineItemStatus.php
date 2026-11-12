<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Edit\Operations;

use CraftCms\Commerce\Order\Edit\Exceptions\OperationNotApplicableException;
use CraftCms\Commerce\Order\Edit\OrderOperation;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\LineItemStatuses;

use function CraftCms\Cms\t;

/**
 * @since 6.0.0
 */
final readonly class SetLineItemStatus extends OrderOperation
{
    public function __construct(
        public string $lineItemUid,
        public ?int $lineItemStatusId,
    ) {
    }

    public static function type(): string
    {
        return 'setLineItemStatus';
    }

    public function label(): string
    {
        return t('Set Line Item Status', category: 'commerce');
    }

    public static function rules(): array
    {
        return [
            'lineItemUid' => ['required', 'string'],
            'lineItemStatusId' => ['present', 'nullable', 'integer'],
        ];
    }

    public static function fromArray(array $data): static
    {
        return new self(
            $data['lineItemUid'],
            isset($data['lineItemStatusId']) ? (int)$data['lineItemStatusId'] : null,
        );
    }

    public function toArray(): array
    {
        return [
            'lineItemUid' => $this->lineItemUid,
            'lineItemStatusId' => $this->lineItemStatusId,
        ];
    }

    public function summary(Order $before): string
    {
        $statuses = app(LineItemStatuses::class);
        $name = fn(?int $id): ?string => $id === null ? null : $statuses->getLineItemStatusById($id, $before->storeId)?->name;

        return $this->itemSummary(
            $before,
            $this->lineItemUid,
            $this->change($name($this->lineItem($before, $this->lineItemUid)->lineItemStatusId), $name($this->lineItemStatusId)),
        );
    }

    public function apply(Order $order): void
    {
        $lineItem = $this->lineItem($order, $this->lineItemUid);

        if ($this->lineItemStatusId !== null && !app(LineItemStatuses::class)->getLineItemStatusById($this->lineItemStatusId, $order->storeId)) {
            throw new OperationNotApplicableException(t('The line item status no longer exists.', category: 'commerce'));
        }

        $lineItem->lineItemStatusId = $this->lineItemStatusId;
    }

    public function absorb(OrderOperation $earlier): ?static
    {
        return $earlier instanceof self && $earlier->lineItemUid === $this->lineItemUid ? $this : null;
    }

    public function isUnchangedFor(Order $order): bool
    {
        return $this->lineItem($order, $this->lineItemUid)->lineItemStatusId === $this->lineItemStatusId;
    }
}
