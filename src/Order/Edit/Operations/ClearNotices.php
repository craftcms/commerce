<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Edit\Operations;

use CraftCms\Commerce\Order\Edit\OrderOperation;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\Enums\OrderNoticeType;

/**
 * Clears the order's customer or admin notices: all of them, or just the given ones.
 *
 * @since 6.0.0
 */
final readonly class ClearNotices extends OrderOperation
{
    /** @param list<int>|null $noticeIds */
    public function __construct(
        public OrderNoticeType $noticeType,
        public ?array $noticeIds = null,
    ) {
    }

    public static function type(): string
    {
        return 'clearNotices';
    }

    public static function rules(): array
    {
        return [
            'noticeType' => ['required', 'in:' . implode(',', OrderNoticeType::values())],
            'noticeIds' => ['nullable', 'array'],
            'noticeIds.*' => ['integer'],
        ];
    }

    public static function fromArray(array $data): static
    {
        return new self(
            OrderNoticeType::from($data['noticeType']),
            isset($data['noticeIds']) ? array_values(array_map(intval(...), $data['noticeIds'])) : null,
        );
    }

    public function toArray(): array
    {
        return [
            'noticeType' => $this->noticeType->value,
            'noticeIds' => $this->noticeIds,
        ];
    }

    public function apply(Order $order): void
    {
        if ($this->noticeIds === null) {
            $order->clearNotices(noticeTypes: $this->noticeType);

            return;
        }

        $remaining = array_filter(
            [...$order->getNotices(), ...$order->getAdminNotices()],
            fn($notice) => $notice->getNoticeType() !== $this->noticeType || !in_array($notice->id, $this->noticeIds, true),
        );

        $order->clearNotices(noticeTypes: [OrderNoticeType::Customer, OrderNoticeType::Admin]);
        $order->addNotices(array_values($remaining));
    }
}
