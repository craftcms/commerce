<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Edit\Data;

use CraftCms\Commerce\Order\Edit\OrderEdit;
use DateTimeImmutable;
use DateTimeInterface;
use JsonSerializable;

/**
 * An admin's edit of a completed order, held until they commit or discard it.
 *
 * @since 6.0.0
 */
final readonly class OrderEditSession implements JsonSerializable
{
    public function __construct(
        public int $id,
        public int $orderId,
        public ?int $userId,
        public OrderEdit $edit,
        public DateTimeImmutable $dateCreated,
        public DateTimeImmutable $dateUpdated,
    ) {
    }

    public function hasChanges(): bool
    {
        return $this->edit->operations !== [];
    }

    public function belongsTo(?int $userId): bool
    {
        return $userId !== null && $this->userId === $userId;
    }

    /**
     * @return array{
     *     id: int,
     *     orderId: int,
     *     userId: int|null,
     *     edit: array<string, mixed>,
     *     dateCreated: string,
     *     dateUpdated: string,
     * }
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'orderId' => $this->orderId,
            'userId' => $this->userId,
            'edit' => $this->edit->jsonSerialize(),
            'dateCreated' => $this->dateCreated->format(DateTimeInterface::ATOM),
            'dateUpdated' => $this->dateUpdated->format(DateTimeInterface::ATOM),
        ];
    }
}
