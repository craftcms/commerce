<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Edit;

use CraftCms\Commerce\Order\Elements\Order;
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

    /**
     * Adds an operation, folding in earlier ones it makes redundant.
     *
     * @param Order|null $base the order as the edit starts from. An operation that wouldn't change it
     *                         is dropped, along with the earlier ones it undoes. One that cancels
     *                         earlier ones is dropped with them.
     */
    public function with(OrderOperation $operation, ?Order $base = null): self
    {
        $operations = $this->operations;
        $cancelled = false;

        foreach ($operations as $index => $earlier) {
            if ($operation->cancels($earlier)) {
                unset($operations[$index]);
                $cancelled = true;

                continue;
            }

            $combined = $operation->absorb($earlier);

            if ($combined !== null) {
                unset($operations[$index]);
                $operation = $combined;
            }
        }

        if (!$cancelled && ($base === null || !$operation->isUnchangedFor($base))) {
            $operations[] = $operation;
        }

        return new self(array_values($operations), $this->baseDateUpdated);
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
