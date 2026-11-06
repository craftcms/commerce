<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Edit\Operations;

use Closure;
use CraftCms\Commerce\Order\Adjuster\Discount;
use CraftCms\Commerce\Order\Adjuster\Shipping;
use CraftCms\Commerce\Order\Adjuster\Tax;
use CraftCms\Commerce\Order\Data\OrderAdjustment;
use CraftCms\Commerce\Order\Edit\OrderOperation;
use CraftCms\Commerce\Order\Elements\Order;
use Illuminate\Support\Fluent;
use Illuminate\Validation\Rule;

use function CraftCms\Cms\t;

/**
 * Adds a manual adjustment to a completed order, or to one of its line items.
 *
 * Carts can't take manual adjustments: every change to a cart recalculates its adjustments.
 *
 * @since 6.0.0
 */
final readonly class AddAdjustment extends OrderOperation
{
    public const array TYPES = [Discount::ADJUSTMENT_TYPE, Shipping::ADJUSTMENT_TYPE, Tax::ADJUSTMENT_TYPE];

    public function __construct(
        public string $adjustmentType,
        public string $name,
        public float $amount,
        public ?string $description = null,
        public bool $included = false,
        public ?string $lineItemUid = null,
        public ?string $uid = null,
    ) {
    }

    public static function type(): string
    {
        return 'addAdjustment';
    }

    public function label(): string
    {
        return t('Add Adjustment', category: 'commerce');
    }

    public static function rules(): array
    {
        return [
            'adjustmentType' => ['required', 'in:' . implode(',', self::TYPES)],
            'name' => ['required', 'string'],
            'amount' => ['required', 'numeric', Rule::when(
                fn(Fluent $input) => $input->get('adjustmentType') === Discount::ADJUSTMENT_TYPE,
                [self::negativeDiscount()],
            )],
            'description' => ['nullable', 'string'],
            'included' => ['boolean'],
            'lineItemUid' => ['nullable', 'string'],
            'uid' => ['nullable', 'string'],
        ];
    }

    /**
     * A rule that fails for a discount that isn't a negative amount.
     */
    public static function negativeDiscount(): Closure
    {
        return function(string $attribute, mixed $value, Closure $fail): void {
            if ((float)$value >= 0) {
                $fail(t('A discount must be a negative amount.', category: 'commerce'));
            }
        };
    }

    public static function fromArray(array $data): static
    {
        return new self(
            adjustmentType: $data['adjustmentType'],
            name: $data['name'],
            amount: (float)$data['amount'],
            description: $data['description'] ?? null,
            included: (bool)($data['included'] ?? false),
            lineItemUid: $data['lineItemUid'] ?? null,
            uid: $data['uid'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'adjustmentType' => $this->adjustmentType,
            'name' => $this->name,
            'amount' => $this->amount,
            'description' => $this->description,
            'included' => $this->included,
            'lineItemUid' => $this->lineItemUid,
            'uid' => $this->uid,
        ];
    }

    public function summary(Order $before): string
    {
        return t('{name} · {amount}', ['name' => $this->name, 'amount' => $this->currency($before, $this->amount)], category: 'commerce');
    }

    public function apply(Order $order): void
    {
        $this->requireCompletedOrder($order);

        $adjustment = new OrderAdjustment();
        $adjustment->setOrder($order);
        $adjustment->uid = $this->uid;
        $adjustment->type = $this->adjustmentType;
        $adjustment->name = $this->name;
        $adjustment->description = $this->description;
        $adjustment->amount = $this->amount;
        $adjustment->included = $this->included;
        $adjustment->setSourceSnapshot(['manual' => true]);

        if ($this->lineItemUid !== null) {
            $adjustment->setLineItem($this->lineItem($order, $this->lineItemUid));
        }

        $order->setAdjustments([...$order->getAdjustments() ?? [], $adjustment]);
    }
}
