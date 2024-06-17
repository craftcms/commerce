<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\services;

use Craft;
use craft\commerce\elements\Product;
use craft\commerce\Plugin;
use craft\events\SiteEvent;
use craft\helpers\Queue;
use craft\queue\jobs\ResaveElements;
use yii\base\Component;

/**
 * Product service.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class Products extends Component
{
    /**
     * Returns a product by its ID.
     *
     * @param int $id
     * @param int|null $siteId
     * @return Product|null
     */
    public function getProductById(int $id, int $siteId = null): ?Product
    {
        return Craft::$app->getElements()->getElementById($id, Product::class, $siteId);
    }

    /**
     * Handle a Site being saved.
     */
    public function afterSaveSiteHandler(SiteEvent $event): void
    {
        if (
            $event->isNew &&
            isset($event->oldPrimarySiteId) &&
            Craft::$app->getPlugins()->isPluginInstalled(Plugin::getInstance()->id)
        ) {
            Queue::push(new ResaveElements([
                'elementType' => Product::class,
                'criteria' => [
                    'siteId' => $event->oldPrimarySiteId,
                    'status' => null,
                ],
            ]));
        }
    }
}
