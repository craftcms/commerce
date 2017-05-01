<?php
namespace Craft;


/**
 * Product service.
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.services
 * @since     1.0
 */
class Commerce_ProductsService extends BaseApplicationComponent
{
    /**
     * @param int $id
     * @param int $localeId
     *
     * @return Commerce_ProductModel
     */
    public function getProductById($id, $localeId = null)
    {
        return craft()->elements->getElementById($id, 'Commerce_Product', $localeId);
    }


    /**
     * @param Commerce_ProductModel $product
     *
     * @return bool
     * @throws Exception
     * @throws \Exception
     */
    public function saveProduct(Commerce_ProductModel $product)
    {

        $isNewProduct = !$product->id;

        if (!$product->id) {
            $record = new Commerce_ProductRecord();
        } else {
            $record = Commerce_ProductRecord::model()->findById($product->id);

            if (!$record) {
                throw new Exception(Craft::t('No product exists with the ID “{id}”',
                    ['id' => $product->id]));
            }
        }

        // Fire an 'onBeforeSaveProduct' event
        $event = new Event($this, [
            'product'      => $product,
            'isNewProduct' => $isNewProduct
        ]);

        $this->onBeforeSaveProduct($event);

        $record->postDate = $product->postDate;
        $record->expiryDate = $product->expiryDate;
        $record->typeId = $product->typeId;
        $record->promotable = $product->promotable;
        $record->freeShipping = $product->freeShipping;
        $record->taxCategoryId = $product->taxCategoryId;
        $record->shippingCategoryId = $product->shippingCategoryId;

        $record->validate();
        $product->addErrors($record->getErrors());

        $productType = craft()->commerce_productTypes->getProductTypeById($product->typeId);

        if(!$productType){
            throw new Exception(Craft::t('No product type exists with the ID “{id}”',
                ['id' => $product->typeId]));
        }

        $taxCategoryIds = array_keys($productType->getTaxCategories());
        if (!in_array($product->taxCategoryId, $taxCategoryIds))
        {
            $record->taxCategoryId = $product->taxCategoryId = $taxCategoryIds[0];
        }

        $shippingCategoryIds = array_keys($productType->getShippingCategories());
        if (!in_array($product->shippingCategoryId, $shippingCategoryIds))
        {
            $record->shippingCategoryId = $product->shippingCategoryId = $shippingCategoryIds[0];
        }

        // Final prep of variants and validation
        $variantsValid = true;
        $defaultVariant = null;
        foreach ($product->getVariants() as $variant) {

            // Use the product type's titleFormat if the title field is not shown
            if (!$productType->hasVariantTitleField && $productType->hasVariants)
            {
                try
                {
                    $variant->getContent()->title = craft()->templates->renderObjectTemplate($productType->titleFormat, $variant);
                }catch(\Exception $e){
                    $variant->getContent()->title = "";
                }
            }

            if(!$productType->hasVariants)
            {
                // Since VariantModel::getTitle() returns the parent products title when the product has
                // no variants, lets save the products title as the variant title anyway.
                $variant->getContent()->title = $product->getTitle();
            }

            // If we have a blank SKU, generate from product type's skuFormat
            if(!$variant->sku){
                try
                {
                    if (!$productType->hasVariants)
                    {
                        $variant->sku = craft()->templates->renderObjectTemplate($productType->skuFormat, $product);
                    }
                    else
                    {
                        $variant->sku = craft()->templates->renderObjectTemplate($productType->skuFormat, $variant);
                    }
                }catch(\Exception $e){
                    CommercePlugin::log("Could not generate SKU format: ".$e->getMessage(), LogLevel::Warning, true);
                    $variant->sku = "";
                }
            }

            // Make the first variant (or the last one that says it isDefault) the default.
            if ($defaultVariant === null || $variant->isDefault)
            {
                $defaultVariant = $variant;
            }

            if (!craft()->commerce_variants->validateVariant($variant)) {
                $variantsValid = false;
                // If we have a title error but hide the title field, put the error onto the sku.
                if($variant->getError('title') && !$productType->hasVariantTitleField && $productType->hasVariants){
                    $variant->addError('sku',Craft::t('Could not generate the variant title from product type’s title format.'));
                }

                if($variant->getError('title') && !$productType->hasVariants){
                    $product->addError('title',Craft::t('Title cannot be blank.'));
                }
            }
        }

        if ($product->hasErrors() || !$variantsValid)
        {
            return false;
        }


        $transaction = craft()->db->getCurrentTransaction() === null ? craft()->db->beginTransaction() : null;
        try {

             $record->defaultVariantId = $product->defaultVariantId = $defaultVariant->getPurchasableId();
             $record->defaultSku = $product->defaultSku = $defaultVariant->getSku();
             $record->defaultPrice = $product->defaultPrice = (float) $defaultVariant->price;
             $record->defaultHeight = $product->defaultHeight = (float) $defaultVariant->height;
             $record->defaultLength = $product->defaultLength = (float) $defaultVariant->length;
             $record->defaultWidth = $product->defaultWidth = (float) $defaultVariant->width;
             $record->defaultWeight = $product->defaultWeight = (float) $defaultVariant->weight;
            
            if ($event->performAction)
            {

                $success = craft()->elements->saveElement($product);

                if ($success)
                {
                    // Now that we have an element ID, save it on the other stuff
                    if ($isNewProduct)
                    {
                        $record->id = $product->id;
                    }

                    $record->save(false);

                    $keepVariantIds = [];
                    $oldVariantIds = craft()->db->createCommand()
                        ->select('id')
                        ->from('commerce_variants')
                        ->where('productId = :productId', [':productId' => $product->id])
                        ->queryColumn();

                    foreach ($product->getVariants() as $variant)
                    {
                        if ($defaultVariant === $variant)
                        {
                            $variant->isDefault = true;
                            $variant->enabled = true; // default must always be enabled.
                        }
                        else
                        {
                            $variant->isDefault = false;
                        }
                        $variant->setProduct($product);

                        craft()->commerce_variants->saveVariant($variant);

                        // Need to manually update the product's default variant ID now that we have a saved ID
                        if ($product->defaultVariantId === null && $defaultVariant === $variant)
                        {
                            $product->defaultVariantId = $variant->id;
                            craft()->db->createCommand()->update('commerce_products', ['defaultVariantId' => $variant->id], ['id' => $product->id]);
                        }

                        $keepVariantIds[] = $variant->id;
                    }

                    foreach (array_diff($oldVariantIds, $keepVariantIds) as $deleteId)
                    {
                        craft()->commerce_variants->deleteVariantById($deleteId);
                    }

                    if ($transaction !== null)
                    {
                        $transaction->commit();
                    }
                }

            }else{
                $success = false;
            }
        } catch (\Exception $e) {
            if ($transaction !== null)
            {
                $transaction->rollback();
            }
            throw $e;
        }

        if ($success)
        {
            // Fire an 'onSaveProduct' event
            $this->onSaveProduct(new Event($this, [
                'product'      => $product,
                'isNewProduct' => $isNewProduct
            ]));
        }

        return $success;
    }

    /**
     * @param Commerce_ProductModel|Commerce_ProductModel[] $products
     *
     * @return bool
     * @throws \CDbException
     * @throws \Exception
     */
    public function deleteProduct($products)
    {
        if (!$products)
        {
            return false;
        }

        $transaction = craft()->db->getCurrentTransaction() === null ? craft()->db->beginTransaction() : null;

        try
        {
            if (!is_array($products))
            {
                $products = [$products];
            }

            $productIds = [];
            $variantsByProductId = [];

            foreach ($products as $product)
            {
                // Fire an 'onBeforeDeleteProduct' event
                $event = new Event($this, [
                    'product' => $product
                ]);

                $this->onBeforeDeleteProduct($event);

                if ($event->performAction)
                {
                    $productIds[] = $product->id;
                    $variantsByProductId[$product->id] = craft()->commerce_variants->getAllVariantsByProductId($product->id);
                }
            }

            if ($productIds)
            {
                // Delete 'em
                $success = craft()->elements->deleteElementById($productIds);
            }
            else
            {
                $success = false;
            }

            if ($transaction !== null)
            {
                $transaction->commit();
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

        if ($success)
        {
            foreach ($products as $product)
            {

                // Delete all child variants.
                $variants = $variantsByProductId[$product->id];
                $ids = [];
                foreach ($variants as $v)
                {
                    $ids[] = $v->id;
                }
                craft()->elements->deleteElementById($ids);
                
                // Fire an 'onDeleteProduct' event
                $this->onDeleteProduct(new Event($this, [
                    'product' => $product
                ]));
            }

            return true;
        }
        else
        {
            return false;
        }
    }

    /**
     * This event is raised before a product is saved
     *
     * @param \CEvent $event
     *
     * @throws \CException
     */
    public function onBeforeSaveProduct(\CEvent $event)
    {
        $params = $event->params;
        if (empty($params['product']) || !($params['product'] instanceof Commerce_ProductModel))
        {
            throw new Exception('onBeforeSaveProduct event requires "product" param with Commerce_ProductModel instance that is being saved.');
        }

        if (!isset($params['isNewProduct']))
        {
            throw new Exception('onBeforeSaveProduct event requires "isNewProduct" param with a boolean to determine if the product is new.');
        }

        $this->raiseEvent('onBeforeSaveProduct', $event);
    }

    /**
     * This event is raised after a product has been successfully saved
     *
     * @param \CEvent $event
     *
     * @throws \CException
     */
    public function onSaveProduct(\CEvent $event)
    {
        $params = $event->params;
        if (empty($params['product']) || !($params['product'] instanceof Commerce_ProductModel))
        {
            throw new Exception('onSaveProduct event requires "product" param with Commerce_ProductModel instance that is being saved.');
        }

        if (!isset($params['isNewProduct']))
        {
            throw new Exception('onSaveProduct event requires "isNewProduct" param with a boolean to determine if the product is new.');
        }

        $this->raiseEvent('onSaveProduct', $event);
    }


    /**
     * This event is raised before a product is saved
     *
     * @param \CEvent $event
     *
     * @throws \CException
     */
    public function onBeforeDeleteProduct(\CEvent $event)
    {
        $params = $event->params;
        if (empty($params['product']) || !($params['product'] instanceof Commerce_ProductModel))
        {
            throw new Exception('onBeforeDeleteProduct event requires "product" param with Commerce_ProductModel instance that is being deleted.');
        }

        $this->raiseEvent('onBeforeDeleteProduct', $event);
    }

    /**
     * This event is raised after a product has been successfully saved
     *
     * @param \CEvent $event
     *
     * @throws \CException
     */
    public function onDeleteProduct(\CEvent $event)
    {
        $params = $event->params;
        if (empty($params['product']) || !($params['product'] instanceof Commerce_ProductModel))
        {
            throw new Exception('onDeleteProduct event requires "product" param with Commerce_ProductModel instance that is being deleted.');
        }

        $this->raiseEvent('onDeleteProduct', $event);
    }

    /**
     * Event: The product loaded for editing
     * Event params: product(Commerce_ProductModel)
     *
     * @param \CEvent $event
     *
     * @throws \CException
     */
    public function onBeforeEditProduct(\CEvent $event)
    {
        $params = $event->params;
        if (empty($params['product']) || !($params['product'] instanceof Commerce_ProductModel))
        {
            throw new Exception('onBeforeEditProduct event requires "product" param with ProductModel instance');
        }
        $this->raiseEvent('onBeforeEditProduct', $event);
    }
}
