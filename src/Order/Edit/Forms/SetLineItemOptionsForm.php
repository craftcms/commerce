<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Edit\Forms;

use CraftCms\Cms\Ui\Controls\Textarea;
use CraftCms\Cms\Ui\Nodes\Field;
use CraftCms\Cms\Ui\Ui;
use CraftCms\Commerce\Order\Edit\Operations\SetLineItemOptions;
use CraftCms\Commerce\Order\Elements\Order;
use Illuminate\Validation\ValidationException;

use function CraftCms\Cms\t;

/**
 * Edits a line item's options as JSON, since they can hold any structure.
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
        return Ui::make([
            Field::make(t('Options', category: 'commerce'), Textarea::make('options')->rows(8)->monospace())
                ->instructions(t('A JSON object of option names and values.', category: 'commerce')),
        ]);
    }

    public function values(Order $order, array $params): array
    {
        $options = $this->lineItem($order, $params)->getOptions();

        return [
            'options' => $options ? json_encode($options, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '',
        ];
    }

    public function operationData(Order $order, array $input): array
    {
        $json = trim((string)($input['options'] ?? ''));
        $options = $json === '' ? [] : json_decode($json, true);

        if (!is_array($options)) {
            throw ValidationException::withMessages([
                'options' => t('Options must be a JSON object.', category: 'commerce'),
            ]);
        }

        return [
            'lineItemUid' => $this->lineItem($order, $input)->uid,
            'options' => $options,
        ];
    }
}
