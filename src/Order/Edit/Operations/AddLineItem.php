<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Edit\Operations;

use CraftCms\Commerce\Order\Edit\Exceptions\OperationNotApplicableException;
use CraftCms\Commerce\Order\Edit\OrderOperation;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\LineItem\Data\LineItem;
use CraftCms\Commerce\Order\LineItem\LineItems;
use CraftCms\Commerce\Purchasable\Purchasables;
use Throwable;

use function CraftCms\Cms\t;

/**
 * Adds a purchasable to the order.
 *
 * The client chooses the new line item's `uid`, so later operations in the same edit can refer to it.
 *
 * @since 6.0.0
 */
final readonly class AddLineItem extends OrderOperation
{
    /** @param array<string, mixed> $options */
    public function __construct(
        public string $uid,
        public int $purchasableId,
        public int $qty = 1,
        public array $options = [],
        public string $note = '',
    ) {
    }

    public static function type(): string
    {
        return 'addLineItem';
    }

    public function label(): string
    {
        return t('Add Line Item', category: 'commerce');
    }

    public static function rules(): array
    {
        return [
            'uid' => ['required', 'uuid'],
            'purchasableId' => ['required', 'integer'],
            'qty' => ['integer', 'min:1'],
            'options' => ['array'],
            'note' => ['nullable', 'string'],
        ];
    }

    public static function fromArray(array $data): static
    {
        return new self(
            uid: $data['uid'],
            purchasableId: (int)$data['purchasableId'],
            qty: (int)($data['qty'] ?? 1),
            options: $data['options'] ?? [],
            note: $data['note'] ?? '',
        );
    }

    public function toArray(): array
    {
        return [
            'uid' => $this->uid,
            'purchasableId' => $this->purchasableId,
            'qty' => $this->qty,
            'options' => $this->options,
            'note' => $this->note,
        ];
    }

    public function summary(Order $before): ?string
    {
        $purchasable = app(Purchasables::class)->getPurchasableById($this->purchasableId, $before->orderSiteId, $before->getCustomer()?->id);

        return $purchasable
            ? t('{description} × {qty}', ['description' => $purchasable->getDescription(), 'qty' => $this->qty], category: 'commerce')
            : null;
    }

    public function apply(Order $order): void
    {
        try {
            $lineItem = app(LineItems::class)->create($order, [
                'purchasableId' => $this->purchasableId,
                'qty' => $this->qty,
                'options' => $this->options,
                'note' => $this->note,
                'uid' => $this->uid,
            ]);
        } catch (Throwable $exception) {
            throw new OperationNotApplicableException($exception->getMessage(), previous: $exception);
        }

        $purchasable = $lineItem->getPurchasable();

        if (!$purchasable || !app(Purchasables::class)->isPurchasableAvailable($purchasable, $order)) {
            throw new OperationNotApplicableException(t('{description} is not available.', ['description' => $lineItem->getDescription()], category: 'commerce'));
        }

        $order->addLineItem($lineItem);

        if (!collect($order->getLineItems())->contains(fn(LineItem $item) => $item->uid === $this->uid)) {
            throw new OperationNotApplicableException(t('{description} could not be added to the order.', ['description' => $lineItem->getDescription()], category: 'commerce'));
        }
    }
}
