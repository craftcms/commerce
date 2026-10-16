<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Edit\Forms;

use CraftCms\Cms\Site\Data\Site;
use CraftCms\Cms\Ui\Controls\Choice;
use CraftCms\Cms\Ui\Nodes\Field;
use CraftCms\Cms\Ui\Ui;
use CraftCms\Commerce\Order\Edit\Operations\SetOrderSite;
use CraftCms\Commerce\Order\Elements\Order;

use function CraftCms\Cms\t;

/**
 * @since 6.0.0
 */
final readonly class SetOrderSiteForm extends OrderOperationForm
{
    public static function type(): string
    {
        return SetOrderSite::type();
    }

    public function title(Order $order, array $params): string
    {
        return t('Order Site', category: 'commerce');
    }

    public function ui(Order $order, array $params): Ui
    {
        return Ui::make([
            Field::make(t('Order Site', category: 'commerce'), Choice::make('orderSiteId')->options(
                $order->getStore()->getSites()
                    ->map(fn(Site $site) => ['label' => $site->getUiLabel(), 'value' => (string)$site->id])
                    ->values()
                    ->all(),
            )),
        ]);
    }

    public function values(Order $order, array $params): array
    {
        return ['orderSiteId' => (string)$order->orderSiteId];
    }

    public function operationData(Order $order, array $input): array
    {
        return ['orderSiteId' => $input['orderSiteId'] ?? null];
    }
}
