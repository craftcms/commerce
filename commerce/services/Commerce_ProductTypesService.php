<?php
namespace Craft;

use Commerce\Helpers\CommerceDbHelper;

/**
 * Product type service.
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.services
 * @since     1.0
 */
class Commerce_ProductTypesService extends BaseApplicationComponent
{

    /**
     * @var bool
     */
    private $_fetchedAllProductTypes = false;

    /**
     * @var
     */
    private $_productTypesById;

    /**
     * @var
     */

    private $_allProductTypeIds;
    /**
     * @var
     */
    private $_editableProductTypeIds;

    /**
     * Returns all Product Types
     *
     * @param string|null $indexBy
     * @return Commerce_ProductTypeModel[]
     */
    public function getAllProductTypes($indexBy = null)
    {
        if (!$this->_fetchedAllProductTypes) {
            $results = Commerce_ProductTypeRecord::model()->findAll();

            if (!isset($this->_productTypesById))
            {
                $this->_productTypesById = [];
            }

            foreach($results as $result){
                $productType = Commerce_ProductTypeModel::populateModel($result);
                $this->_productTypesById[$productType->id] = $productType;
            }

            $this->_fetchedAllProductTypes = true;
        }

        if ($indexBy == 'id')
        {
            $productTypes = $this->_productTypesById;
        }
        else if (!$indexBy)
        {
            $productTypes = array_values($this->_productTypesById);
        }
        else
        {
            $productTypes = array();
            foreach ($this->_productTypesById as $productType)
            {
                $productTypes[$productType->$indexBy] = $productType;
            }
        }

        return $productTypes;
    }

    /**
     * Returns all of the product type IDs.
     *
     * @return array All the product types’ IDs.
     */
    public function getAllProductTypeIds()
    {
        if (!isset($this->_allProductTypeIds))
        {
            $this->_allProductTypeIds = array();

            foreach ($this->getAllProductTypes() as $productType)
            {
                $this->_allProductTypeIds[] = $productType->id;
            }
        }

        return $this->_allProductTypeIds;
    }

    /**
     * Returns all editable product types.
     *
     * @param string|null $indexBy
     *
     * @return Commerce_ProductTypeModel[] All the editable product types.
     */
    public function getEditableProductTypes($indexBy = null)
    {
        $editableProductTypeIds = $this->getEditableProductTypeIds();
        $editableProductTypes = array();

        foreach ($this->getAllProductTypes() as $productTypes)
        {
            if (in_array($productTypes->id, $editableProductTypeIds))
            {
                if ($indexBy)
                {
                    $editableProductTypes[$productTypes->$indexBy] = $productTypes;
                }
                else
                {
                    $editableProductTypes[] = $productTypes;
                }
            }
        }

        return $editableProductTypes;
    }

    /**
     * Returns all of the product type IDs that are editable by the current user.
     *
     * @return array All the editable product types’ IDs.
     */
    public function getEditableProductTypeIds()
    {
        if (!isset($this->_editableProductTypeIds))
        {
            $this->_editableProductTypeIds = array();

            foreach ($this->getAllProductTypeIds() as $productTypeId)
            {
                if (craft()->userSession->checkPermission('commerce-manageProductType:'.$productTypeId))
                {
                    $this->_editableProductTypeIds[] = $productTypeId;
                }
            }
        }

        return $this->_editableProductTypeIds;
    }

    /**
     * @param int $productTypeId
     *
     * @return Commerce_ProductTypeModel|null
     */
    public function getProductTypeById($productTypeId)
    {
        if(!$this->_fetchedAllProductTypes &&
            (!isset($this->_productTypesById) || !array_key_exists($productTypeId, $this->_productTypesById))
        )
        {
            $result = Commerce_ProductTypeRecord::model()->findById($productTypeId);

            if ($result) {
                $productType = Commerce_ProductTypeModel::populateModel($result);
            }
            else
            {
                $productType = null;
            }

            $this->_productTypesById[$productTypeId] = $productType;
        }

        if (isset($this->_productTypesById[$productTypeId]))
        {
            return $this->_productTypesById[$productTypeId];
        }
    }

    /**
     * @param string $handle
     *
     * @return Commerce_ProductTypeModel|null
     */
    public function getByHandle($handle)
    {
        $result = Commerce_ProductTypeRecord::model()->findByAttributes(['handle' => $handle]);

        if ($result)
        {
            $productType = Commerce_ProductTypeModel::populateModel($result);
            $this->_productTypesById[$productType->id] = $productType;

            return $productType;
        }

        return null;
    }

    /**
     * @param      $productTypeId
     * @param null $indexBy
     *
     * @return array
     */
    public function getProductTypeLocales($productTypeId, $indexBy = null)
    {
        $records = Commerce_ProductTypeLocaleRecord::model()->findAllByAttributes([
            'productTypeId' => $productTypeId
        ]);

        return Commerce_ProductTypeLocaleModel::populateModels($records, $indexBy);
    }

    /**
     * @param Commerce_ProductTypeModel $productType
     *
     * @return bool
     * @throws Exception
     * @throws \CDbException
     * @throws \Exception
     */
    public function saveProductType(Commerce_ProductTypeModel $productType)
    {
        $titleFormatChanged = false;

        if ($productType->id) {
            $productTypeRecord = Commerce_ProductTypeRecord::model()->findById($productType->id);
            if (!$productTypeRecord) {
                throw new Exception(Craft::t('No product type exists with the ID “{id}”',
                    ['id' => $productType->id]));
            }

            /** @var Commerce_ProductTypeModel $oldProductType */
            $oldProductType = Commerce_ProductTypeModel::populateModel($productTypeRecord);
            $isNewProductType = false;
        } else {
            $productTypeRecord = new Commerce_ProductTypeRecord();
            $isNewProductType = true;
        }

        // If the product type does not have variants, default the title format.
        if(!$isNewProductType && !$productType->hasVariants){
            $productType->hasVariantTitleField = false;
            $productType->titleFormat = "{product.title}";
        }

        $productTypeRecord->name = $productType->name;
        $productTypeRecord->handle = $productType->handle;
        $productTypeRecord->hasDimensions = $productType->hasDimensions;
        $productTypeRecord->hasUrls = $productType->hasUrls;
        $productTypeRecord->hasVariants = $productType->hasVariants;
        $productTypeRecord->hasVariantTitleField = $productType->hasVariantTitleField;
        $productTypeRecord->titleFormat = $productType->titleFormat ? $productType->titleFormat : "{product.title}";
        $productTypeRecord->skuFormat = $productType->skuFormat;
        $productTypeRecord->template = $productType->template;

        if(!$isNewProductType && !$productType->hasVariantTitleField){
            if ($productTypeRecord->titleFormat != $oldProductType->titleFormat) {
                $titleFormatChanged = true;
            }
        }

        // Make sure that all of the URL formats are set properly
        $productTypeLocales = $productType->getLocales();

        foreach ($productTypeLocales as $localeId => $productTypeLocale) {
            if ($productType->hasUrls) {
                $urlFormatAttributes = ['urlFormat'];
                $productTypeLocale->urlFormatIsRequired = true;

                foreach ($urlFormatAttributes as $attribute) {
                    if (!$productTypeLocale->validate([$attribute])) {
                        $productType->addError($attribute . '-' . $localeId, $productTypeLocale->getError($attribute));
                    }
                }
            } else {
                $productTypeLocale->urlFormat = null;
            }
        }

        $productTypeRecord->validate();
        $productType->addErrors($productTypeRecord->getErrors());

        if (!$productType->hasErrors()) {
            CommerceDbHelper::beginStackedTransaction();
            try {

                if (!$isNewProductType) {
                    // If we previously had variants but now don't, delete all non-default variants.
                    if ($oldProductType->hasVariants && !$productType->hasVariants) {
                        $criteria = craft()->elements->getCriteria('Commerce_Product');
                        $criteria->typeId = $productType->id;
                        $products = $criteria->find();
                        /** @var Commerce_ProductModel $product */
                        foreach ($products as $key => $product) {
                            if ($product && $product->getContent()->id) {
                                $defaultVariant = null;
                                // find out default variant
                                foreach ($product->getVariants() as $variant) {
                                    if ($defaultVariant === null || $variant->isDefault)
                                    {
                                        $defaultVariant = $variant;
                                    }
                                }
                                // delete all non-default variants
                                foreach ($product->getVariants() as $variant) {
                                    if ($defaultVariant !== $variant)
                                    {
                                        craft()->commerce_variants->deleteVariantById($variant->id);
                                    }
                                }
                            }
                        }
                    }
                }

                // Product Field Layout
                if (!$isNewProductType && $oldProductType->fieldLayoutId) {
                    // Drop the old field layout
                    craft()->fields->deleteLayoutById($oldProductType->fieldLayoutId);
                }
                // Save the new one
                $fieldLayout = $productType->asa('productFieldLayout')->getFieldLayout();
                craft()->fields->saveLayout($fieldLayout);
                $productType->fieldLayoutId = $fieldLayout->id;
                $productTypeRecord->fieldLayoutId = $fieldLayout->id;

                if (!$isNewProductType && $oldProductType->variantFieldLayoutId) {
                    // Drop the old field layout
                    craft()->fields->deleteLayoutById($oldProductType->variantFieldLayoutId);
                }
                // Save the new one
                $variantFieldLayout = $productType->asa('variantFieldLayout')->getFieldLayout();
                craft()->fields->saveLayout($variantFieldLayout);
                $productType->variantFieldLayoutId = $variantFieldLayout->id;
                $productTypeRecord->variantFieldLayoutId = $variantFieldLayout->id;

                // Save it!
                $productTypeRecord->save(false);

                // Now that we have a product type ID, save it on the model
                if (!$productType->id) {
                    $productType->id = $productTypeRecord->id;
                }

                // Update the service level cache
                $this->_productTypesById[$productType->id] = $productType;

                $newLocaleData = [];

                //Refresh all titles for variants of same product type if titleFormat changed.
                if ($productType->hasVariants && !$productType->hasVariantTitleField) {
                    if ($titleFormatChanged) {
                        $criteria = craft()->elements->getCriteria('Commerce_Product');
                        $criteria->typeId = $productType->id;
                        $products = $criteria->find();
                        foreach ($products as $product) {
                            foreach($product->getVariants() as $variant){
                                $title = craft()->templates->renderObjectTemplate($productType->titleFormat, $variant);
                                // updates to the same title in all locales
                                craft()->db->createCommand()->update('content',
                                    ['title' => $title],
                                    ['elementId' => $variant->id]
                                );
                            }
                        }
                    }
                }

                if (!$isNewProductType) {
                    // Get the old product type locales
                    $oldLocaleRecords = Commerce_ProductTypeLocaleRecord::model()->findAllByAttributes([
                        'productTypeId' => $productType->id
                    ]);
                    $oldLocales = Commerce_ProductTypeLocaleModel::populateModels($oldLocaleRecords, 'locale');

                    $changedLocaleIds = [];
                }


                foreach ($productTypeLocales as $localeId => $locale) {
                    // Was this already selected?
                    if (!$isNewProductType && isset($oldLocales[$localeId])) {
                        $oldLocale = $oldLocales[$localeId];

                        // Has the URL format changed?
                        if ($locale->urlFormat != $oldLocale->urlFormat) {
                            craft()->db->createCommand()->update('commerce_producttypes_i18n', [
                                'urlFormat' => $locale->urlFormat
                            ], [
                                'id' => $oldLocale->id
                            ]);

                            $changedLocaleIds[] = $localeId;
                        }
                    } else {
                        $newLocaleData[] = [$productType->id, $localeId, $locale->urlFormat];
                    }
                }

                // Insert the new locales
                craft()->db->createCommand()->insertAll('commerce_producttypes_i18n',
                    ['productTypeId', 'locale', 'urlFormat'],
                    $newLocaleData
                );

                if (!$isNewProductType) {
                    // Drop any locales that are no longer being used, as well as the associated element
                    // locale rows

                    $droppedLocaleIds = array_diff(array_keys($oldLocales), array_keys($productTypeLocales));

                    if ($droppedLocaleIds) {
                        craft()->db->createCommand()->delete('commerce_producttypes_i18n', ['in', 'locale', $droppedLocaleIds]);
                    }
                }


                if (!$isNewProductType) {
                    // Get all of the product IDs in this group
                    $criteria = craft()->elements->getCriteria('Commerce_Product');
                    $criteria->typeId = $productType->id;
                    $criteria->status = null;
                    $criteria->limit = null;
                    $productIds = $criteria->ids();

                    // Should we be deleting
                    if ($productIds && $droppedLocaleIds) {
                        craft()->db->createCommand()->delete('elements_i18n', ['and', ['in', 'elementId', $productIds], ['in', 'locale', $droppedLocaleIds]]);
                        craft()->db->createCommand()->delete('content', ['and', ['in', 'elementId', $productIds], ['in', 'locale', $droppedLocaleIds]]);
                    }
                    // Are there any locales left?
                    if ($productTypeLocales) {
                        // Drop the old productType URIs if the product type no longer has URLs
                        if (!$productType->hasUrls && $oldProductType->hasUrls) {
                            craft()->db->createCommand()->update('elements_i18n',
                                ['uri' => null],
                                ['in', 'elementId', $productIds]
                            );
                        } else if ($changedLocaleIds) {
                            foreach ($productIds as $productId) {
                                craft()->config->maxPowerCaptain();

                                // Loop through each of the changed locales and update all of the products’ slugs and
                                // URIs
                                foreach ($changedLocaleIds as $localeId) {
                                    $criteria = craft()->elements->getCriteria('Commerce_Product');
                                    $criteria->id = $productId;
                                    $criteria->locale = $localeId;
                                    $criteria->status = null;
                                    $updateProduct = $criteria->first();

                                    // @todo replace the getContent()->id check with 'strictLocale' param once it's added
                                    if ($updateProduct && $updateProduct->getContent()->id) {
                                        craft()->elements->updateElementSlugAndUri($updateProduct, false, false);
                                    }
                                }
                            }
                        }
                    }
                }

                CommerceDbHelper::commitStackedTransaction();
            } catch (\Exception $e) {
                CommerceDbHelper::rollbackStackedTransaction();

                throw $e;
            }

            return true;
        } else {
            return false;
        }
    }

    /**
     * Deleted a
     *
     * @param $id
     *
     * @return bool
     * @throws \CDbException
     * @throws \Exception
     */
    public function deleteProductTypeById($id)
    {
        CommerceDbHelper::beginStackedTransaction();
        try {
            $productType = $this->getProductTypeById($id);

            $criteria = craft()->elements->getCriteria('Commerce_Product');
            $criteria->typeId = $productType->id;
            $criteria->status = null;
            $criteria->limit = null;
            $products = $criteria->find();

            foreach ($products as $product) {
                craft()->commerce_products->deleteProduct($product);
            }

            $fieldLayoutId = $productType->asa('productFieldLayout')->getFieldLayout()->id;
            craft()->fields->deleteLayoutById($fieldLayoutId);
            if ($productType->hasVariants) {
                craft()->fields->deleteLayoutById($productType->asa('variantFieldLayout')->getFieldLayout()->id);
            }

            $productTypeRecord = Commerce_ProductTypeRecord::model()->findById($productType->id);
            $affectedRows = $productTypeRecord->delete();

            if ($affectedRows) {
                CommerceDbHelper::commitStackedTransaction();
            }

            return (bool)$affectedRows;
        } catch (\Exception $e) {
            CommerceDbHelper::rollbackStackedTransaction();

            throw $e;
        }
    }

    /**
     * Returns whether a product type’s products have URLs, and if the template path is valid.
     *
     * @param Commerce_ProductTypeModel $productType
     *
     * @return bool
     */
    public function isProductTypeTemplateValid(Commerce_ProductTypeModel $productType)
    {
        if ($productType->hasUrls)
        {
            // Set Craft to the site template path
            $oldTemplatesPath = craft()->path->getTemplatesPath();
            craft()->path->setTemplatesPath(craft()->path->getSiteTemplatesPath());

            // Does the template exist?
            $templateExists = craft()->templates->doesTemplateExist($productType->template);

            // Restore the original template path
            craft()->path->setTemplatesPath($oldTemplatesPath);

            if ($templateExists)
            {
                return true;
            }
        }

        return false;
    }

    /**
     * @param Event $event
     *
     * @return bool
     */
    public function addLocaleHandler(Event $event)
    {
        /** @var Commerce_OrderModel $order */
        $localeId = $event->params['localeId'];

        // Add this locale to each of the category groups
        $productTypeLocales = craft()->db->createCommand()
            ->select('productTypeId, urlFormat')
            ->from('commerce_producttypes_i18n')
            ->where('locale = :locale', [':locale' => craft()->i18n->getPrimarySiteLocaleId()])
            ->queryAll();

        if ($productTypeLocales) {
            $newProductTypeLocales = [];

            foreach ($productTypeLocales as $productTypeLocale) {
                $newProductTypeLocales[] = [$productTypeLocale['productTypeId'], $localeId, $productTypeLocale['urlFormat']];
            }

            craft()->db->createCommand()->insertAll('commerce_producttypes_i18n', ['productTypeId', 'locale', 'urlFormat'], $newProductTypeLocales);
        }

        return true;
    }

}
