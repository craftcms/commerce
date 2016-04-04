<?php
namespace Craft;

use Commerce\Helpers\CommerceDbHelper;

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

        $record->postDate = $product->postDate;
        $record->expiryDate = $product->expiryDate;
        $record->typeId = $product->typeId;
        $record->authorId = $product->authorId;
        $record->promotable = $product->promotable;
        $record->freeShipping = $product->freeShipping;
        $record->taxCategoryId = $product->taxCategoryId;

        $record->validate();
        $product->addErrors($record->getErrors());

        $productType = craft()->commerce_productTypes->getProductTypeById($product->typeId);

        if(!$productType){
            throw new Exception(Craft::t('No product type exists with the ID “{id}”',
                ['id' => $product->typeId]));
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


        CommerceDbHelper::beginStackedTransaction();
        try {

             $record->defaultVariantId = $defaultVariant->getPurchasableId();
             $record->defaultSku = $defaultVariant->getSku();
             $record->defaultPrice = $defaultVariant->price * 1;
             $record->defaultHeight = $defaultVariant->height * 1;
             $record->defaultLength = $defaultVariant->length * 1;
             $record->defaultWidth = $defaultVariant->width * 1;
             $record->defaultWeight = $defaultVariant->weight * 1;

	        // Fire an 'onBeforeSaveEntry' event
	        $event = new Event($this, array(
		        'product'      => $product,
		        'isNewProduct' => $isNewProduct
	        ));

	        $this->onBeforeSaveProduct($event);
	        
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
				        $variant->productId = $product->id;
				        craft()->commerce_variants->saveVariant($variant);
				        $keepVariantIds[] = $variant->id;
			        }

			        foreach (array_diff($oldVariantIds, $keepVariantIds) as $deleteId)
			        {
				        craft()->commerce_variants->deleteVariantById($deleteId);
			        }

			        CommerceDbHelper::commitStackedTransaction();
		        }

            }else{
		        $success = false;
	        }
        } catch (\Exception $e) {
            CommerceDbHelper::rollbackStackedTransaction();
            throw $e;
        }

	    if ($success)
	    {
		    // Fire an 'onSaveEntry' event
		    $this->onSaveProduct(new Event($this, [
			    'product'      => $product,
			    'isNewProduct' => $isNewProduct
		    ]));
	    }

        return $success;
    }


    /**
     * @param Commerce_ProductModel $product
     *
     * @return bool
     * @throws \CDbException
     */
    public function deleteProduct($product)
    {
        $product = Commerce_ProductRecord::model()->findById($product->id);
        if ($product) {
            $variants = craft()->commerce_variants->getAllVariantsByProductId($product->id);
            if (craft()->elements->deleteElementById($product->id)) {
                foreach ($variants as $v) {
                    craft()->elements->deleteElementById($v->id);
                }

                return true;
            } else {

                return false;
            }
        }
    }

    public function userDeleteHandler(Event $event)
    {
        /** @var UserModel $user */
        $user = $event->params['user'];

        /** @var UserModel|null $user */
        $transferContentTo = $event->params['transferContentTo'];

        // Should we transfer the product content to a new user?
        if ($transferContentTo)
        {
            // Get the entry IDs that belong to this user
            $productIds = craft()->db->createCommand()
                ->select('id')
                ->from('commerce_products')
                ->where(['authorId' => $user->id])
                ->queryColumn();

            // Delete the template caches for any products authored by this user
            craft()->templateCache->deleteCachesByElementId($productIds);

            // update all authorIds to the new author
            craft()->db->createCommand()->update('commerce_products', ['authorId' => $transferContentTo->id], ['authorId' => $user->id]);

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
}
