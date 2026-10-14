<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Edit;

use DateTimeImmutable;
use DateTimeInterface;
use JsonSerializable;

/**
 * A versioned list of operations to apply to an order, along with the `dateUpdated` of the order
 * they were made against.
 *
 * This is the stored shape of an edit session and the payload the order edit screen submits.
 * {@see VERSION} changes whenever that shape does, so stored edits from an older version can be
 * recognised.
 *
 * @since 6.0.0
 */
final readonly class OrderEdit implements JsonSerializable
{
    public const int VERSION = 1;

    /** @param list<OrderOperation> $operations */
    public function __construct(
        public array $operations = [],
        public ?DateTimeImmutable $baseDateUpdated = null,
    ) {
    }

    public function with(OrderOperation $operation): self
    {
        return new self([...$this->operations, $operation], $this->baseDateUpdated);
    }

    public function without(int $index): self
    {
        $operations = $this->operations;
        unset($operations[$index]);

        return new self(array_values($operations), $this->baseDateUpdated);
    }

    /**
     * @return array{
     *     version: int,
     *     baseDateUpdated: string|null,
     *     operations: list<array<string, mixed>>,
     * }
     */
    public function jsonSerialize(): array
    {
        return [
            'version' => self::VERSION,
            'baseDateUpdated' => $this->baseDateUpdated?->format(DateTimeInterface::ATOM),
            'operations' => array_map(fn(OrderOperation $operation) => $operation->jsonSerialize(), $this->operations),
        ];
    }
}
