<?php

namespace craft\commerce\services;

use Craft;
use craft\base\ElementInterface;
use craft\commerce\elements\Order;
use craft\commerce\elements\Product;
use craft\commerce\elements\Variant;
use craft\commerce\events\PurchaseVariantEvent;
use craft\commerce\helpers\Currency;
use craft\commerce\Plugin;
use yii\base\Component;

/**
 * Variant service.
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.services
 * @since     1.0
 */
class Variants extends Component
{
    // Constants
    // =========================================================================

    /**
     * @event PurchaseVariantEvent The event is raised when an order has been completed, and the variant is considered to be ordered
     */
    const EVENT_PURCHASE_VARIANT = 'purchaseVariant';

    // Public Methods
    // =========================================================================

    /**
     * @param int    $variantId The variant’s ID.
     * @param string $siteId    The locale to fetch the variant in. Defaults to {@link WebApp::language `craft()->language`}.
     *
     * @return ElementInterface|null
     */
    public function getVariantById($variantId, $siteId = null)
    {
        return Craft::$app->getElements()->getElementById($variantId, Variant::class, $siteId);
    }

    /**
     * Returns the first variant as returned by it's sortOrder.
     *
     * @param int         $variantId
     * @param string|null $siteId
     *
     * @return Variant
     */
    public function getDefaultVariantByProductId($variantId, $siteId = null): Variant
    {
        return $this->getAllVariantsByProductId($variantId, $siteId)[0];
    }

    /**
     * @param int         $productId
     * @param string|null $siteId
     *
     * @return Variant[]
     */
    public function getAllVariantsByProductId($productId, $siteId = null): array
    {
        $variants = Variant::find()->productId($productId)->status(null)->limit(null)->siteId($siteId)->all();
        return $variants;
    }

    /**
     * Apply sales, associated with the given product, to all given variants
     *
     * @param Variant[] $variants
     * @param Product   $product
     */
    public function applySales(array $variants, Product $product)
    {
        // reset the salePrice to be the same as price, and clear any sales applied.
        foreach ($variants as $variant) {
            $variant->setSales([]);
            $variant->setSalePrice(Currency::round($variant->price));
        }

        // Only bother calculating if the product is persisted and promotable.
        if ($product->id && $product->promotable) {
            $sales = Plugin::getInstance()->getSales()->getSalesForProduct($product);

            foreach ($sales as $sale) {
                foreach ($variants as $variant) {
                    $variant->setSales($sales);

                    $variant->setSalePrice(Currency::round($variant->getSalePrice() + $sale->calculateTakeoff($variant->price)));
                    if ($variant->getSalePrice() < 0) {
                        $variant->setSalePrice(0);
                    }
                }
            }
        }
    }

    /**
     * Update Stock count from completed order
     *
     * @param Order $order
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
                    ['stock' => new \CDbExpression('stock - :qty', [':qty' => $lineItem->qty])],
                    'id = :variantId',
                    [':variantId' => $purchasable->id])->execute();

                // Update the stock
                $purchasable->stock = Craft::$app->getDb()->createCommand()
                    ->select('stock')
                    ->from('{{%commerce_variants}}')
                    ->where('id = :variantId', [':variantId' => $purchasable->id])
                    ->queryScalar();

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
