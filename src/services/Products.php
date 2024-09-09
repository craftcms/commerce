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
use craft\db\Query;
use craft\events\SiteEvent;
use craft\helpers\Queue;
use craft\queue\jobs\PropagateElements;
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
     * @param array|int|string|null $siteId
     * @return Product|null
     */
    public function getProductById(int $id, array|int|string $siteId = null, array $criteria = []): ?Product
    {
        if (!$id) {
            return null;
        }

        // Get the structure ID
        if (!isset($criteria['structureId'])) {
            $criteria['structureId'] = (new Query())
                ->select(['productTypes.structureId'])
                ->from(['products' => \craft\commerce\db\Table::PRODUCTS])
                ->innerJoin(['productTypes' => \craft\commerce\db\Table::PRODUCTTYPES], '[[productTypes.id]] = [[products.sectionId]]')
                ->where(['products.id' => $id])
                ->scalar();
        }

        return Craft::$app->getElements()->getElementById($id, Product::class, $siteId, $criteria);
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
            Queue::push(new PropagateElements([
                'elementType' => Product::class,
                'criteria' => [
                    'siteId' => $event->oldPrimarySiteId,
                    'status' => null,
                ],
                'siteId' => $event->site->id,
            ]));
        }
    }
}
