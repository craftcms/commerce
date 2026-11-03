<?php

namespace craft\commerce\services;

use craft\commerce\base\LegacyElementEvents;
use craft\events\ElementCriteriaEvent;
use CraftCms\Commerce\Product\Elements\Product;
use CraftCms\Commerce\Product\Events\ProductParentSelectionCriteriaResolving;
use Illuminate\Support\Facades\Event;
use yii\base\Component;

/**
 * @deprecated 6.0.0 use `app(\CraftCms\Commerce\Product\Products::class)` instead.
 */
class Products extends Component
{
    /**
     * Bridges the product's Laravel events to the legacy `Product::EVENT_*` Yii events.
     */
    public static function registerEvents(): void
    {
        Event::listen(ProductParentSelectionCriteriaResolving::class, static function(ProductParentSelectionCriteriaResolving $event) {
            $legacyEvent = LegacyElementEvents::trigger($event->product, \craft\commerce\elements\Product::class, Product::EVENT_DEFINE_PARENT_SELECTION_CRITERIA, new ElementCriteriaEvent([
                'criteria' => $event->criteria,
            ]));
            $event->criteria = $legacyEvent->criteria;
        });
    }

    /**
     * @param array|int|string|null $siteId
     */
    public function getProductById(int $id, array|int|string|null $siteId = null, array $criteria = []): ?Product
    {
        return app(\CraftCms\Commerce\Product\Products::class)->getProductById($id, $siteId, $criteria);
    }
}
