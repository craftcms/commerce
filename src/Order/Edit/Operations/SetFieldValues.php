<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Edit\Operations;

use CraftCms\Cms\Field\Exceptions\InvalidFieldException;
use CraftCms\Cms\Support\Facades\Fields;
use CraftCms\Commerce\Order\Edit\Exceptions\OperationNotApplicableException;
use CraftCms\Commerce\Order\Edit\OrderOperation;
use CraftCms\Commerce\Order\Elements\Order;

use function CraftCms\Cms\t;

/**
 * Sets custom field values on the order, keyed by field handle, as a field takes them from a request.
 *
 * @since 6.0.0
 */
final readonly class SetFieldValues extends OrderOperation
{
    /** @param array<string, mixed> $values */
    public function __construct(
        public array $values,
    ) {
    }

    public static function type(): string
    {
        return 'setFieldValues';
    }

    public function label(): string
    {
        return t('Edit Fields', category: 'commerce');
    }

    public static function rules(): array
    {
        return [
            'values' => ['required', 'array'],
        ];
    }

    public static function fromArray(array $data): static
    {
        return new self($data['values']);
    }

    public function toArray(): array
    {
        return ['values' => $this->values];
    }

    public function summary(Order $before): ?string
    {
        $names = [];

        foreach ($this->values as $handle => $value) {
            $handle = (string)$handle;
            $current = json_encode($before->getSerializedFieldValues([$handle])[$handle] ?? null);

            try {
                $before->setFieldValueFromRequest($handle, $value);
            } catch (InvalidFieldException) {
                continue;
            }

            if ($current !== json_encode($before->getSerializedFieldValues([$handle])[$handle] ?? null)) {
                $names[] = Fields::getFieldByHandle($handle)->name;
            }
        }

        return $names ? implode(', ', $names) : null;
    }

    public function apply(Order $order): void
    {
        foreach ($this->values as $handle => $value) {
            try {
                $order->setFieldValueFromRequest((string)$handle, $value);
            } catch (InvalidFieldException $exception) {
                throw new OperationNotApplicableException($exception->getMessage(), previous: $exception);
            }
        }
    }

    public function absorb(OrderOperation $earlier): ?static
    {
        return $earlier instanceof self ? new self([...$earlier->values, ...$this->values]) : null;
    }
}
