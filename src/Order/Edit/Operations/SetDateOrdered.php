<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Edit\Operations;

use CraftCms\Commerce\Order\Edit\OrderOperation;
use CraftCms\Commerce\Order\Elements\Order;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;

use function CraftCms\Cms\t;

/**
 * Changes when a completed order was placed.
 *
 * @since 6.0.0
 */
final readonly class SetDateOrdered extends OrderOperation
{
    public function __construct(
        public DateTimeImmutable $dateOrdered,
    ) {
    }

    public static function type(): string
    {
        return 'setDateOrdered';
    }

    public function label(): string
    {
        return t('Change Date Ordered', category: 'commerce');
    }

    public static function rules(): array
    {
        return [
            'dateOrdered' => ['required', 'date'],
        ];
    }

    public static function fromArray(array $data): static
    {
        return new self(new DateTimeImmutable($data['dateOrdered']));
    }

    public function toArray(): array
    {
        return ['dateOrdered' => $this->dateOrdered->format(DateTimeInterface::ATOM)];
    }

    public function apply(Order $order): void
    {
        $this->requireCompletedOrder($order);

        $order->dateOrdered = DateTime::createFromImmutable($this->dateOrdered);
    }
}
