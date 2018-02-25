<?php

namespace craft\commerce\services;

use Craft;
use craft\base\ElementInterface;
use craft\commerce\elements\Order;
use craft\commerce\elements\Variant;
use craft\commerce\events\PurchaseVariantEvent;
use craft\db\Query;
use yii\base\Component;
use yii\db\Expression;

/**
 * Variant service.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class Variants extends Component
{
    // Constants
    // =========================================================================

    /**
     * @event PurchaseVariantEvent The event is raised when an order has been completed, and the variant is considered to be ordered
     *
     * Plugins can get notified when a variant is part of an order that is being completed
     *
     * ```php
     * use craft\commerce\events\PurchaseVariantEvent;
     * use craft\commerce\services\Variants;
     * use yii\base\Event;
     *
     * Event::on(Variants::class, Variants::EVENT_PURCHASE_VARIANT, function(PurchaseVariantEvent $e) {
     *      // Perhaps alert the warehouse that this item is oficially purchased and should be set aside.
     * });
     * ```
     */
    const EVENT_PURCHASE_VARIANT = 'purchaseVariant';

    // Public Methods
    // =========================================================================

    /**
     * Returns a product's variants, per the product's ID.
     *
     * @param int $productId product ID
     * @param int|null $siteId Site ID for which to return the variants. Defaults to `null` which is current site.
     * @return Variant[]
     */
    public function getAllVariantsByProductId(int $productId, int $siteId = null): array
    {
        $variants = Variant::find()->productId($productId)->status(null)->limit(null)->siteId($siteId)->all();

        return $variants;
    }

    /**
     * Returns the first variant, per its product's ID.
     *
     * @param int $productId the product's ID
     * @param int|null $siteId Site ID for which to return the variant. Defaults to `null` which is current site.
     * @return Variant
     */
    public function getDefaultVariantByProductId(int $productId, int $siteId = null): Variant
    {
        return $this->getAllVariantsByProductId($productId, $siteId)[0];
    }

    /**
     * Returns a variant by its ID.
     *
     * @param int $variantId The variant’s ID.
     * @param int|null $siteId The site ID for which to fetch the variant. Defaults to `null` which is current site.
     * @return ElementInterface|null
     */
    public function getVariantById(int $variantId, int $siteId = null)
    {
        return Craft::$app->getElements()->getElementById($variantId, Variant::class, $siteId);
    }

    /**
     * Updates Stock count from completed order.
     *
     * @param Order $order the order which was completed.
     */
    public function orderCompleteHandler(Order $order)
    {
        $variants = [];

        foreach ($order->lineItems as $lineItem) {
            /** @var Variant $record */
            $purchasable = $lineItem->getPurchasable();

            // Only reduce variant stock if the variant exists in db
            if (!$purchasable) {
                continue;
            }

            $clearCacheOfElementIds = [];

            if ($purchasable instanceof Variant && !$purchasable->unlimitedStock) {
                // Update the qty in the db
                Craft::$app->getDb()->createCommand()->update('{{%commerce_variants}}',
                    ['stock' => new Expression('stock - :qty', [':qty' => $lineItem->qty])],
                    ['id' => $purchasable->id])->execute();

                // Update the stock
                $purchasable->stock = (new Query())
                    ->select(['stock'])
                    ->from('{{%commerce_variants}}')
                    ->where('id = :variantId', [':variantId' => $purchasable->id])
                    ->scalar();

                // Clear the cache since the stock changed
                $clearCacheOfElementIds[] = $purchasable->id;
                $clearCacheOfElementIds[] = $purchasable->product->id;
            }

            $clearCacheOfElementIds = array_unique($clearCacheOfElementIds);
            Craft::$app->getTemplateCaches()->deleteCachesByElementId($clearCacheOfElementIds);

            if ($purchasable instanceof Variant) {
                // make an array of each variant purchased
                $variants[$purchasable->id] = $purchasable;
            }
        }

        if ($this->hasEventHandlers(self::EVENT_PURCHASE_VARIANT)) {
            foreach ($variants as $variant) {
                // Raise 'purchaseVariant' event
                $this->trigger(self::EVENT_PURCHASE_VARIANT, new PurchaseVariantEvent([
                    'variant' => $variant
                ]));
            }
        }
    }
}
