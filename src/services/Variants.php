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
use craft\db\Query;
use yii\base\Component;
use yii\db\Expression;

/**
 * Variant service.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since  2.0
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
     * Apply sales that are associated with the given product to all given variants.
     *
     * @param Variant[] $variants an array of variants to apply sales to
     * @param Product   $product  the product.
     *
     * @return void
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
     * Get all product's variants by it's id.
     *
     * @param int      $productId product id
     * @param int|null $siteId    Site id for which to return the variants. Defaults to `null` which is current site.
     *
     * @return Variant[]
     */
    public function getAllVariantsByProductId(int $productId, int $siteId = null): array
    {
        $variants = Variant::find()->productId($productId)->status(null)->limit(null)->siteId($siteId)->all();

        return $variants;
    }

    /**
     * Returns the first variant as returned by it's sortOrder.
     *
     * @param int      $variantId variant id.
     * @param int|null $siteId    Site id for which to return the variant. Defaults to `null` which is current site.
     *
     * @return Variant
     */
    public function getDefaultVariantByProductId(int $variantId, int $siteId = null): Variant
    {
        return $this->getAllVariantsByProductId($variantId, $siteId)[0];
    }

    /**
     * Get a variant by it's id.
     *
     * @param int      $variantId The variant’s ID.
     * @param int|null $siteId    The site id for which to fetch the variant. Defaults to `null` which is current site.
     *
     * @return ElementInterface|null
     */
    public function getVariantById(int $variantId, int $siteId = null)
    {
        return Craft::$app->getElements()->getElementById($variantId, Variant::class, $siteId);
    }

    /**
     * Update Stock count from completed order.
     *
     * @param Order $order the order which was completed.
     *
     * @return void
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
