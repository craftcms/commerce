<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order;

use Closure;
use CraftCms\Cms\Support\Str;
use CraftCms\Commerce\Order\Data\OrderAdjustment;
use CraftCms\Commerce\Order\Elements\Order;

use function CraftCms\Cms\t;

/**
 * The snapshots saved with an order's line items and adjustments, which are the data they had when
 * they were added to the order.
 *
 * @since 6.0.0
 */
final readonly class OrderSnapshots
{
    /**
     * What has a snapshot, without the snapshots themselves.
     *
     * @return list<array{key: string, name: string, context: string|null, type: string}>
     */
    public function list(Order $order): array
    {
        return array_map(
            fn(array $entry) => array_diff_key($entry, ['snapshot' => true]),
            iterator_to_array($this->entries($order), preserve_keys: false),
        );
    }

    /**
     * The snapshot of one thing, as pretty printed JSON.
     */
    public function json(Order $order, string $key): ?string
    {
        foreach ($this->entries($order) as $entry) {
            if ($entry['key'] === $key) {
                return (string)json_encode($entry['snapshot'](), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }
        }

        return null;
    }

    /**
     * @return \Generator<int, array{key: string, name: string, context: string|null, type: string, snapshot: Closure(): array<mixed>}>
     */
    private function entries(Order $order): \Generator
    {
        foreach ($order->getLineItems() as $lineItem) {
            if ($lineItem->id === null) {
                continue;
            }

            yield [
                'key' => "lineItem-{$lineItem->id}",
                'name' => $lineItem->getDescription(),
                'context' => null,
                'type' => t('Line Item', category: 'commerce'),
                'snapshot' => fn() => $lineItem->getSnapshot(),
            ];

            foreach ($lineItem->getAdjustments() as $adjustment) {
                if ($entry = $this->adjustmentEntry($adjustment, $lineItem->getDescription())) {
                    yield $entry;
                }
            }
        }

        foreach ($order->getOrderAdjustments() as $adjustment) {
            if ($entry = $this->adjustmentEntry($adjustment, null)) {
                yield $entry;
            }
        }
    }

    /**
     * @return array{key: string, name: string, context: string|null, type: string, snapshot: Closure(): array<mixed>}|null
     */
    private function adjustmentEntry(OrderAdjustment $adjustment, ?string $context): ?array
    {
        if ($adjustment->id === null) {
            return null;
        }

        return [
            'key' => "adjustment-{$adjustment->id}",
            'name' => $adjustment->name,
            'context' => $context,
            'type' => t(Str::title($adjustment->type), category: 'commerce'),
            'snapshot' => fn() => $adjustment->getSourceSnapshot(),
        ];
    }
}
