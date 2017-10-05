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
use craft\commerce\records\Variant as VariantRecord;
use yii\base\Component;
use yii\web\HttpException;

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
     * @param string $siteId  The locale to fetch the variant in. Defaults to {@link WebApp::language `craft()->language`}.
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
     * @param Variant $variant
     *
     * @return bool
     */
    public function validateVariant(Variant $variant): bool
    {
        $variant->clearErrors();

        $record = $this->_getVariantRecord($variant);
        $this->_populateVariantRecord($record, $variant);

        $record->validate();
        $variant->addErrors($record->getErrors());

        if (!craft()->content->validateContent($variant)) {
            $variant->addErrors($variant->getC());
        }

        // If variant validation has not already found a clash check all purchasables
        if (!$variant->getFirstError('sku')) {
            $existing = Plugin::getInstance()->getPurchasables()->getPurchasableBySku($variant->sku);

            if ($existing) {
                if ($existing->id != $variant->id) {
                    $variant->addError('sku', Craft::t('commerce', 'SKU has already been taken by another purchasable.'));
                }
            }
        }

        return !$variant->hasErrors();
    }

    /**
     * @param Variant $model
     *
     * @return VariantRecord
     */
    private function _getVariantRecord(Variant $model)
    {
        if ($model->id) {
            $record = VariantRecord::findOne($model->id);

            if (!$record) {
                throw new HttpException(404);
            }
        } else {
            $record = new VariantRecord();
        }

        return $record;
    }

    /**
     * @param                       $record
     * @param Variant               $model
     */
    private function _populateVariantRecord($record, Variant $model)
    {
        $record->productId = $model->productId;
        $record->sku = $model->sku;

        $record->price = $model->price;
        $record->width = (float) $model->width;
        $record->height = (float) $model->height;
        $record->length = (float) $model->length;
        $record->weight = (float) $model->weight;
        $record->minQty = $model->minQty;
        $record->maxQty = $model->maxQty;
        $record->stock = $model->stock;
        $record->isDefault = $model->isDefault;
        $record->sortOrder = $model->sortOrder;
        $record->unlimitedStock = $model->unlimitedStock;

        if (!$model->getProduct()->getType()->hasDimensions) {
            $record->width = $model->width = 0;
            $record->height = $model->height = 0;
            $record->length = $model->length = 0;
            $record->weight = $model->weight = 0;
        }

        if ($model->unlimitedStock && $record->stock == "") {
            $model->stock = 0;
            $record->stock = 0;
        }
    }

    /**
     * Persists a variant.
     *
     * @param BaseElementModel $model
     *
     * @return bool
     * @throws \CDbException
     * @throws \Exception
     */
    public function saveVariant(BaseElementModel $model)
    {
        $record = $this->_getVariantRecord($model);
        $this->_populateVariantRecord($record, $model);

        $record->validate();
        $model->addErrors($record->getErrors());

        $db = Craft::$app->getDb();
        $transaction = $db->beginTransaction();

        try {
            if (!$model->hasErrors() && Plugin::getInstance()->getPurchasables()->saveElement($model)) {
                $record->id = $model->id;
                $record->save(false);
                $transaction->commit();

                return true;
            }
        } catch (\Exception $e) {
            $transaction->rollBack();
            throw $e;
        }

        $transaction->rollBack();

        return false;
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

        if ($this->hasEventHandlers(self::EVENT_PURCHASE_VARIANT))
        {
            foreach ($variants as $variant) {
                // Raise 'purchaseVariant' event
                $this->trigger(self::EVENT_PURCHASE_VARIANT, new PurchaseVariantEvent([
                    'variant' => $variant
                ]));
            }
        }
    }
}
