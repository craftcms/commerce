<?php
namespace Craft;

use Commerce\Helpers\CommerceDbHelper;

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
     * @param int    $variantId  The variant’s ID.
     * @param string $localeId The locale to fetch the variant in. Defaults to {@link WebApp::language `craft()->language`}.
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
     * @param int $variantId
     * @param string|null $localeId
     *
     * @return Commerce_VariantModel
     */
    public function getDefaultVariantByProductId($variantId, $localeId = null)
    {
        return ArrayHelper::getFirstValue($this->getAllVariantsByProductId($variantId, $localeId));
    }

    /**
     * @param int $productId
     * @param string|null $localeId
     *
     * @return Commerce_VariantModel[]
     */
    public function getAllVariantsByProductId($productId, $localeId = null)
    {
        $variants = craft()->elements->getCriteria('Commerce_Variant', ['productId' => $productId, 'status'=> null, 'locale' => $localeId])->find();

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

        foreach ($variants as $variant) {
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
     * @return bool
     */
    public function validateVariant(Commerce_VariantModel $variant)
    {
        $variant->clearErrors();

        $record = $this->_getVariantRecord($variant);
        $this->_populateVariantRecord($record, $variant);

        $record->validate();
        $variant->addErrors($record->getErrors());

        if (!craft()->content->validateContent($variant)) {
            $variant->addErrors($variant->getContent()->getErrors());
        }

        // If variant validation has not already found a clash check all purchasables
        if(!$variant->getError('sku')){
            $existing = craft()->commerce_purchasables->getPurchasableBySku($variant->sku);

            if($existing){
                if($existing->id != $variant->id){
                    $variant->addError('sku',Craft::t('SKU has already been taken by another purchasable.'));
                }
            }
        }

        return !$variant->hasErrors();
    }

    /**
     * Apply sales, associated with the given product, to all given variants
     *
     * @param Commerce_VariantModel[] $variants
     * @param Commerce_ProductModel $product
     */
    public function applySales(array $variants, Commerce_ProductModel $product)
    {

        // set salePrice to be price at default
        foreach ($variants as $variant) {
            $variant->salePrice = $variant->price;
        }

        // Only bother calculating if the product is persisted and promotable.
        if ($product->id && $product->promotable) {
            $sales = craft()->commerce_sales->getForProduct($product);

            foreach ($sales as $sale) {
                foreach ($variants as $variant) {
                    $variant->salePrice = $variant->salePrice + $sale->calculateTakeoff($variant->price);
                    if ($variant->salePrice < 0) {
                        $variant->salePrice = 0;
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

        CommerceDbHelper::beginStackedTransaction();
        try {
            if (!$model->hasErrors()) {
                if (craft()->commerce_purchasables->saveElement($model)) {
                    $record->id = $model->id;
                    $record->save(false);
                    CommerceDbHelper::commitStackedTransaction();

                    return true;
                }
            }
        } catch (\Exception $e) {
            CommerceDbHelper::rollbackStackedTransaction();
            throw $e;
        }

        CommerceDbHelper::rollbackStackedTransaction();

        return false;
    }

    /**
     * @param BaseElementModel $model
     * @return BaseRecord|Commerce_VariantRecord
     * @throws HttpException
     */
    private function _getVariantRecord(BaseElementModel $model)
    {
        if ($model->id) {
            $record = Commerce_VariantRecord::model()->findById($model->id);

            if (!$record) {
                throw new HttpException(404);
            }

        } else {
            $record = new Commerce_VariantRecord();

        }

        return $record;
    }

    /**
     * @param $record
     * @param Commerce_VariantModel $model
     */
    private function _populateVariantRecord($record, Commerce_VariantModel $model)
    {
        $record->productId = $model->productId;
        $record->sku = $model->sku;

        $record->price = $model->price;
        $record->width = $model->width;
        $record->height = $model->height;
        $record->length = $model->length;
        $record->weight = $model->weight;
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
     * Update Stock count from completed order
     *
     * @param Event $event
     */
    public function orderCompleteHandler(Event $event)
    {
        /** @var Commerce_OrderModel $order */
        $order = $event->params['order'];

        foreach ($order->lineItems as $lineItem) {
            /** @var Commerce_VariantRecord $record */
            $record = Commerce_VariantRecord::model()->findByAttributes(['id' => $lineItem->purchasableId]);

            // Don't assume that this is a Variant
            if ($record && !$record->unlimitedStock) {
                $record->stock = $record->stock - $lineItem->qty;
                $record->save(false);
            }
        }
    }

}
