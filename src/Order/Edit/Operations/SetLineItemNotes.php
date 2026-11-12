<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Edit\Operations;

use CraftCms\Commerce\Order\Edit\OrderOperation;
use CraftCms\Commerce\Order\Elements\Order;

use function CraftCms\Cms\t;

/**
 * Sets a line item's customer-facing note and its private note.
 *
 * @since 6.0.0
 */
final readonly class SetLineItemNotes extends OrderOperation
{
    public function __construct(
        public string $lineItemUid,
        public string $note,
        public string $privateNote,
    ) {
    }

    public static function type(): string
    {
        return 'setLineItemNotes';
    }

    public function label(): string
    {
        return t('Edit Line Item Notes', category: 'commerce');
    }

    public static function rules(): array
    {
        return [
            'lineItemUid' => ['required', 'string'],
            'note' => ['present', 'nullable', 'string'],
            'privateNote' => ['present', 'nullable', 'string'],
        ];
    }

    public static function fromArray(array $data): static
    {
        return new self($data['lineItemUid'], $data['note'] ?? '', $data['privateNote'] ?? '');
    }

    public function toArray(): array
    {
        return [
            'lineItemUid' => $this->lineItemUid,
            'note' => $this->note,
            'privateNote' => $this->privateNote,
        ];
    }

    public function summary(Order $before): ?string
    {
        $lineItem = $this->lineItem($before, $this->lineItemUid);
        $changes = [];

        if ($lineItem->note !== $this->note) {
            $changes[] = t('Note', category: 'commerce');
        }

        if ($lineItem->privateNote !== $this->privateNote) {
            $changes[] = t('Private Note', category: 'commerce');
        }

        return $changes ? $this->itemSummary($before, $this->lineItemUid, implode(', ', $changes)) : null;
    }

    public function apply(Order $order): void
    {
        $lineItem = $this->lineItem($order, $this->lineItemUid);
        $lineItem->note = $this->note;
        $lineItem->privateNote = $this->privateNote;
    }

    public function absorb(OrderOperation $earlier): ?static
    {
        return $earlier instanceof self && $earlier->lineItemUid === $this->lineItemUid ? $this : null;
    }
}
