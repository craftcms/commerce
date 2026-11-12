<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Edit\Forms;

use CraftCms\Cms\Ui\Controls\Table;
use CraftCms\Cms\Ui\Nodes\Field;
use CraftCms\Cms\Ui\Ui;
use CraftCms\Commerce\Order\Edit\Operations\SetLineItemOptions;
use CraftCms\Commerce\Order\Elements\Order;

use function CraftCms\Cms\t;

/**
 * Edits a line item's options as name and value rows. A value that is a structure is edited as JSON.
 *
 * @since 6.0.0
 */
final readonly class SetLineItemOptionsForm extends OrderOperationForm
{
    public static function type(): string
    {
        return SetLineItemOptions::type();
    }

    public function title(Order $order, array $params): string
    {
        return t('Options for {item}', ['item' => $this->lineItem($order, $params)->getDescription()], category: 'commerce');
    }

    public function ui(Order $order, array $params): Ui
    {
        /** @var array<string, array<string, array<string, mixed>>> $structured */
        $structured = [];

        foreach (array_values($this->lineItem($order, $params)->getOptions()) as $index => $value) {
            if (is_array($value)) {
                $structured[$index] = ['value' => ['type' => 'multiline', 'rows' => 6, 'code' => true]];
            }
        }

        return Ui::make([
            Field::make(t('Options', category: 'commerce'), Table::make('options')
                ->columns([
                    'key' => ['heading' => t('Name', category: 'commerce'), 'type' => 'singleline'],
                    'value' => ['heading' => t('Value', category: 'commerce'), 'type' => 'singleline'],
                ])
                ->cellOptions($structured)
                ->allowAdd()
                ->allowDelete()
                ->addRowLabel(t('Add an Option', category: 'commerce'))),
        ]);
    }

    public function values(Order $order, array $params): array
    {
        return ['options' => $this->rows($order, $params)];
    }

    public function operationData(Order $order, array $input): array
    {
        $options = [];

        foreach ($input['options'] ?? [] as $row) {
            $key = trim((string)($row['key'] ?? ''));

            if ($key !== '') {
                $options[$key] = $this->optionValue((string)($row['value'] ?? ''));
            }
        }

        return [
            'lineItemUid' => $this->lineItem($order, $input)->uid,
            'options' => $options,
        ];
    }

    /**
     * @param array<string, mixed> $params
     * @return list<array{key: string, value: string}>
     */
    private function rows(Order $order, array $params): array
    {
        $rows = [];

        foreach ($this->lineItem($order, $params)->getOptions() as $key => $value) {
            $rows[] = [
                'key' => (string)$key,
                'value' => is_array($value)
                    ? json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                    : (string)$value,
            ];
        }

        return $rows;
    }

    private function optionValue(string $value): mixed
    {
        $decoded = str_starts_with($value, '{') || str_starts_with($value, '[') ? json_decode($value, true) : null;

        return is_array($decoded) ? $decoded : $value;
    }
}
