<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Activity;

use CraftCms\Cms\Activity\Models\ActivityEvent;
use CraftCms\Commerce\Order\Elements\Order;

use function CraftCms\Cms\t;

/**
 * @since 6.0.0
 */
class OrderFulfilled extends OrderActivityEventType
{
    protected const string LABEL = 'Stock fulfilled';

    protected const string ICON = 'truck';

    /**
     * @param list<array{item: string, location: string, quantity: int}> $movements A negative quantity returns fulfilled stock to committed.
     */
    public function __construct(
        Order $subject,
        private readonly array $movements,
    ) {
        parent::__construct(subject: $subject);
    }

    public function data(): array
    {
        return ['movements' => $this->movements];
    }

    public static function format(ActivityEvent $event): string
    {
        $parts = array_map(
            fn(array $movement) => $movement['quantity'] > 0
                ? t('{quantity} × {item}', ['quantity' => $movement['quantity'], 'item' => $movement['item']], category: 'commerce')
                : t('returned {quantity} × {item}', ['quantity' => abs($movement['quantity']), 'item' => $movement['item']], category: 'commerce'),
            $event->data['movements'],
        );

        return t('Fulfilled {items}', ['items' => implode(', ', $parts)], category: 'commerce');
    }
}
