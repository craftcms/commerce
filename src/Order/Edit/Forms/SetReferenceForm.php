<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Edit\Forms;

use CraftCms\Cms\Ui\Controls\Text;
use CraftCms\Cms\Ui\Nodes\Field;
use CraftCms\Cms\Ui\Ui;
use CraftCms\Commerce\Order\Edit\Operations\SetReference;
use CraftCms\Commerce\Order\Elements\Order;

use function CraftCms\Cms\t;

/**
 * @since 6.0.0
 */
final readonly class SetReferenceForm extends OrderOperationForm
{
    public static function type(): string
    {
        return SetReference::type();
    }

    public function title(Order $order, array $params): string
    {
        return t('Reference', category: 'commerce');
    }

    public function ui(Order $order, array $params): Ui
    {
        return Ui::make([
            Field::make(t('Reference', category: 'commerce'), Text::make('reference')),
        ]);
    }

    public function values(Order $order, array $params): array
    {
        return ['reference' => (string)$order->reference];
    }

    public function operationData(Order $order, array $input): array
    {
        $reference = trim((string)($input['reference'] ?? ''));

        return ['reference' => $reference === '' ? null : $reference];
    }
}
