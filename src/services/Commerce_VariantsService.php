<?php
namespace Craft;

use Commerce\Helpers\CommerceCurrencyHelper;


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
class Commerce_VariantsService extends BaseApplicationComponent
{
    /**
     * @param int    $variantId The variant’s ID.
     * @param string $localeId  The locale to fetch the variant in. Defaults to {@link WebApp::language `craft()->language`}.
     *
     * @return Commerce_VariantModel
     */
    public function getVariantById($variantId, $localeId = null)
    {
        return craft()->elements->getElementById($variantId, 'Commerce_Variant', $localeId);
    }

    /**
     * Returns the first variant as returned by it's sortOrder.
     *
     * @param int         $variantId
     * @param string|null $localeId
     *
     * @return Commerce_VariantModel
     */
    public function getDefaultVariantByProductId($variantId, $localeId = null)
    {
        return ArrayHelper::getFirstValue($this->getAllVariantsByProductId($variantId, $localeId));
    }

    /**
     * @param int         $productId
     * @param string|null $localeId
     *
     * @return Commerce_VariantModel[]
     */
    public function getAllVariantsByProductId($productId, $localeId = null)
    {
        $variants = craft()->elements->getCriteria('Commerce_Variant', ['productId' => $productId, 'status' => null, 'limit' => null, 'locale' => $localeId])->find();

        return $variants;
    }

    /**
     * @param int $id
     */
    public function deleteVariantById($id)
    {
        craft()->elements->deleteElementById($id);
    }

    /**
     * @param int $productId
     */
    public function deleteAllVariantsByProductId($productId)
    {
        $variants = $this->getAllVariantsByProductId($productId);

        foreach ($variants as $variant)
        {
            $this->deleteVariant($variant);
        }
    }

    /**
     * @param $variant
     */
    public function deleteVariant($variant)
    {
        $this->deleteVariantById($variant->id);
    }

    /**
     * @param Commerce_VariantModel $variant
     *
     * @return bool
     */
    public function validateVariant(Commerce_VariantModel $variant)
    {
        $variant->clearErrors();

        $record = $this->_getVariantRecord($variant);
        $this->_populateVariantRecord($record, $variant);

        $record->validate();
        $variant->addErrors($record->getErrors());

        if (!craft()->content->validateContent($variant))
        {
            $variant->addErrors($variant->getContent()->getErrors());
        }

        // If variant validation has not already found a clash check all purchasables
        if (!$variant->getError('sku'))
        {
            $existing = craft()->commerce_purchasables->getPurchasableBySku($variant->sku);

            if ($existing)
            {
                if ($existing->id != $variant->id)
                {
                    $variant->addError('sku', Craft::t('SKU has already been taken by another purchasable.'));
                }
            }
        }

        return !$variant->hasErrors();
    }

    /**
     * Apply sales, associated with the given product, to all given variants
     *
     * @param Commerce_VariantModel[] $variants
     * @param Commerce_ProductModel   $product
     */
    public function applySales(array $variants, Commerce_ProductModel $product)
    {
        // reset the salePrice to be the same as price, and clear any sales applied.
        foreach ($variants as $variant)
        {
            $variant->setSalesApplied([]);
            $variant->setSalePrice(CommerceCurrencyHelper::round($variant->price));
        }

        // Only bother calculating if the product is persisted and promotable.
        if ($product->id && $product->promotable)
        {
            $sales = craft()->commerce_sales->getSalesForProduct($product);

            foreach ($sales as $sale)
            {
                foreach ($variants as $variant)
                {
                    $variant->setSalesApplied($sales);

                    $variant->setSalePrice(CommerceCurrencyHelper::round($variant->getSalePrice() + $sale->calculateTakeoff($variant->price)));
                    if ($variant->getSalePrice() < 0)
                    {
                        $variant->setSalePrice(0);
                    }
                }
            }
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

        $transaction = craft()->db->getCurrentTransaction() === null ? craft()->db->beginTransaction() : null;
        try
        {
            if (!$model->hasErrors())
            {
                if (craft()->commerce_purchasables->saveElement($model))
                {
                    $record->id = $model->id;
                    $record->save(false);
                    if ($transaction !== null)
                    {
                        $transaction->commit();
                    }

                    return true;
                }
            }
        }
        catch (\Exception $e)
        {
            if ($transaction !== null)
            {
                $transaction->rollback();
            }
            throw $e;
        }

        if ($transaction !== null)
        {
            $transaction->rollback();
        }

        return false;
    }

    /**
     * Sets a product on the given variants, and applies any applicable sales.
     *
     * @param Commerce_ProductModel   $product
     * @param Commerce_VariantModel[] $variants
     */
    public function setProductOnVariants($product, $variants)
    {
        foreach ($variants as $variant)
        {
            $variant->setProduct($product);
        }

        // apply all sales applicable
        $this->applySales($variants, $product);
    }

    /**
     * @param BaseElementModel $model
     *
     * @return BaseRecord|Commerce_VariantRecord
     * @throws HttpException
     */
    private function _getVariantRecord(BaseElementModel $model)
    {
        if ($model->id)
        {
            $record = Commerce_VariantRecord::model()->findById($model->id);

            if (!$record)
            {
                throw new HttpException(404);
            }
        }
        else
        {
            $record = new Commerce_VariantRecord();
        }

        return $record;
    }

    /**
     * @param                       $record
     * @param Commerce_VariantModel $model
     */
    private function _populateVariantRecord($record, Commerce_VariantModel $model)
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

        if (!$model->getProduct()->getType()->hasDimensions)
        {
            $record->width = $model->width = 0;
            $record->height = $model->height = 0;
            $record->length = $model->length = 0;
            $record->weight = $model->weight = 0;
        }

        if ($model->unlimitedStock && $record->stock == "")
        {
            $model->stock = 0;
            $record->stock = 0;
        }
    }

    /**
     * Update Stock count from completed order
     *
     * @param Commerce_OrderModel $order
     */
    public function orderCompleteHandler($order)
    {
        $variants = [];

        foreach ($order->lineItems as $lineItem)
        {
            /** @var Commerce_VariantRecord $record */
            $purchasable = $lineItem->getPurchasable();

            // Only reduce variant stock if the variant exists in db
            if (!$purchasable)
            {
                continue;
            }

            $clearCacheOfElementIds = [];
            if ($purchasable instanceof Commerce_VariantModel && !$purchasable->unlimitedStock)
            {

                // Update the qty in the db
                craft()->db->createCommand()->update('commerce_variants',
                    ['stock' => new \CDbExpression('stock - :qty', [':qty' => $lineItem->qty])],
                    'id = :variantId',
                    [':variantId' => $purchasable->id]);

                // Update the stock
                $purchasable->stock = craft()->db->createCommand()
                    ->select('stock')
                    ->from('commerce_variants')
                    ->where('id = :variantId', [':variantId' => $purchasable->id])
                    ->queryScalar();

                // Clear the cache since the stock changed
                $clearCacheOfElementIds[] = $purchasable->id;
                $clearCacheOfElementIds[] = $purchasable->product->id;
            }

            $clearCacheOfElementIds = array_unique($clearCacheOfElementIds);
            craft()->templateCache->deleteCachesByElementId($clearCacheOfElementIds);

            if ($purchasable instanceof Commerce_VariantModel)
            {
                // make an array of each variant purchased
                $variants[$purchasable->id] = $purchasable;
            }

        }

        foreach ($variants as $variant)
        {
            //raising event
            $event = new Event($this, [
                'variant' => $variant
            ]);
            $this->onOrderVariant($event);
        }
    }

    /**
     * This event is raise when an order has been completed, and the variant
     * is considered ordered.
     *
     * @param \CEvent $event
     *
     * @throws \CException
     */
    public function onOrderVariant(\CEvent $event)
    {
        $params = $event->params;
        if (empty($params['variant']) || !($params['variant'] instanceof Commerce_VariantModel))
        {
            throw new Exception('onOrderVariant event requires "variant" param with VariantModel instance that was ordered.');
        }
        $this->raiseEvent('onOrderVariant', $event);
    }

}
