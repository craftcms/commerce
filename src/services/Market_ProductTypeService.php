<?php
namespace Craft;

use Market\Helpers\MarketDbHelper;

/**
 * Class Market_ProductTypeService
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://buildwithcraft.com/license Craft License Agreement
 * @see       http://buildwithcraft.com/commerce
 * @package   craft.plugins.commerce.services
 * @since     1.0
 */
class Market_ProductTypeService extends BaseApplicationComponent
{
    /**
     * @return Market_ProductTypeModel[]
     */
    public function getAll ()
    {
        $productTypeRecords = Market_ProductTypeRecord::model()->findAll();

        return Market_ProductTypeModel::populateModels($productTypeRecords);
    }

    /**
     * @param int $id
     *
     * @return Market_ProductTypeModel
     */
    public function getById ($id)
    {
        $productTypeRecord = Market_ProductTypeRecord::model()->findById($id);

        return Market_ProductTypeModel::populateModel($productTypeRecord);
    }

    /**
     * @param string $handle
     *
     * @return Market_ProductTypeModel
     */
    public function getByHandle ($handle)
    {
        $productTypeRecord = Market_ProductTypeRecord::model()->findByAttributes(['handle' => $handle]);

        return Market_ProductTypeModel::populateModel($productTypeRecord);
    }

    /**
     * @param      $productTypeId
     * @param null $indexBy
     *
     * @return array
     */
    public function getProductTypeLocales ($productTypeId, $indexBy = null)
    {
        $records = Market_ProductTypeLocaleRecord::model()->findAllByAttributes([
            'productTypeId' => $productTypeId
        ]);

        return Market_ProductTypeLocaleModel::populateModels($records, $indexBy);
    }

    /**
     * @param Market_ProductTypeModel $productType
     *
     * @return bool
     * @throws Exception
     * @throws \CDbException
     * @throws \Exception
     */
    public function save (Market_ProductTypeModel $productType)
    {
        $titleFormatChanged = false;

        if ($productType->id)
        {
            $productTypeRecord = Market_ProductTypeRecord::model()->findById($productType->id);
            if (!$productTypeRecord)
            {
                throw new Exception(Craft::t('No product type exists with the ID “{id}”',
                    ['id' => $productType->id]));
            }

            $oldProductType = Market_ProductTypeModel::populateModel($productTypeRecord);
            $isNewProductType = false;
        }
        else
        {
            $productTypeRecord = new Market_ProductTypeRecord();
            $isNewProductType = true;
        }

        $productTypeRecord->name = $productType->name;
        $productTypeRecord->handle = $productType->handle;
        $productTypeRecord->hasUrls = $productType->hasUrls;
        $productTypeRecord->hasVariants = $productType->hasVariants;
        $productTypeRecord->template = $productType->template;

        if ($productTypeRecord->titleFormat != $productType->titleFormat && $productType->hasVariants)
        {
            $titleFormatChanged = true;
        }
        $productTypeRecord->titleFormat = $productType->titleFormat;

        // Make sure that all of the URL formats are set properly
        $productTypeLocales = $productType->getLocales();

        foreach ($productTypeLocales as $localeId => $productTypeLocale)
        {
            if ($productType->hasUrls)
            {
                $urlFormatAttributes = ['urlFormat'];
                $productTypeLocale->urlFormatIsRequired = true;

                foreach ($urlFormatAttributes as $attribute)
                {
                    if (!$productTypeLocale->validate([$attribute]))
                    {
                        $productType->addError($attribute.'-'.$localeId, $productTypeLocale->getError($attribute));
                    }
                }
            }
            else
            {
                $productTypeLocale->urlFormat = null;
            }
        }

        $productTypeRecord->validate();
        $productType->addErrors($productTypeRecord->getErrors());

        if (!$productType->hasErrors())
        {
            MarketDbHelper::beginStackedTransaction();
            try
            {

                // Product Field Layout
                if (!$isNewProductType && $oldProductType->fieldLayoutId)
                {
                    // Drop the old field layout
                    craft()->fields->deleteLayoutById($oldProductType->fieldLayoutId);
                }
                // Save the new one
                $fieldLayout = $productType->asa('productFieldLayout')->getFieldLayout();
                craft()->fields->saveLayout($fieldLayout);
                $productType->fieldLayoutId = $fieldLayout->id;
                $productTypeRecord->fieldLayoutId = $fieldLayout->id;

                if (!$isNewProductType && $oldProductType->variantFieldLayoutId)
                {
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
                if (!$productType->id)
                {
                    $productType->id = $productTypeRecord->id;
                }

                $newLocaleData = [];

                if ($productType->hasVariants)
                {
                    //Refresh all urls for products of same type if urlFormat changed.
                    if ($titleFormatChanged)
                    {
                        $criteria = craft()->elements->getCriteria('Market_Product');
                        $criteria->typeId = $productType->id;
                        $products = $criteria->find();
                        /** @var Market_ProductModel $product */
                        foreach ($products as $key => $product)
                        {
                            if ($product && $product->getContent()->id)
                            {
                                foreach ($product->getVariants() as $variant)
                                {
                                    craft()->market_variant->save($variant);
                                }
                            }
                        }
                    }
                }

                if (!$isNewProductType)
                {
                    // Get the old product type locales
                    $oldLocaleRecords = Market_ProductTypeLocaleRecord::model()->findAllByAttributes([
                        'productTypeId' => $productType->id
                    ]);
                    $oldLocales = Market_ProductTypeLocaleModel::populateModels($oldLocaleRecords, 'locale');

                    $changedLocaleIds = [];
                }


                foreach ($productTypeLocales as $localeId => $locale)
                {
                    // Was this already selected?
                    if (!$isNewProductType && isset($oldLocales[$localeId]))
                    {
                        $oldLocale = $oldLocales[$localeId];

                        // Has the URL format changed?
                        if ($locale->urlFormat != $oldLocale->urlFormat)
                        {
                            craft()->db->createCommand()->update('market_producttypes_i18n', [
                                'urlFormat' => $locale->urlFormat
                            ], [
                                'id' => $oldLocale->id
                            ]);

                            $changedLocaleIds[] = $localeId;
                        }
                    }
                    else
                    {
                        $newLocaleData[] = [$productType->id, $localeId, $locale->urlFormat];
                    }
                }

                // Insert the new locales
                craft()->db->createCommand()->insertAll('market_producttypes_i18n',
                    array('productTypeId', 'locale', 'urlFormat'),
                    $newLocaleData
                );

                if (!$isNewProductType)
                {
                    // Drop any locales that are no longer being used, as well as the associated element
                    // locale rows

                    $droppedLocaleIds = array_diff(array_keys($oldLocales), array_keys($productTypeLocales));

                    if ($droppedLocaleIds)
                    {
                        craft()->db->createCommand()->delete('market_producttypes_i18n', ['in', 'locale', $droppedLocaleIds]);
                    }
                }


                if (!$isNewProductType)
                {
                    // Get all of the product IDs in this group
                    $criteria = craft()->elements->getCriteria('Market_Product');
                    $criteria->typeId = $productType->id;
                    $criteria->status = null;
                    $criteria->limit = null;
                    $productTypeIds = $criteria->ids();

                    // Should we be deleting
                    if ($productTypeIds && $droppedLocaleIds)
                    {
                        craft()->db->createCommand()->delete('elements_i18n', ['and', ['in', 'elementId', $productTypeIds], ['in', 'locale', $droppedLocaleIds]]);
                        craft()->db->createCommand()->delete('content', ['and', ['in', 'elementId', $productTypeIds], ['in', 'locale', $droppedLocaleIds]]);
                    }
                    // Are there any locales left?
                    if ($productTypeLocales)
                    {
                        // Drop the old productType URIs if the product type no longer has URLs
                        if (!$productType->hasUrls && $oldProductType->hasUrls)
                        {
                            craft()->db->createCommand()->update('elements_i18n',
                                ['uri' => null],
                                ['in', 'elementId', $productTypeIds]
                            );
                        }
                        else if ($changedLocaleIds)
                        {
                            foreach ($productTypeIds as $productTypeId)
                            {
                                craft()->config->maxPowerCaptain();

                                // Loop through each of the changed locales and update all of the products’ slugs and
                                // URIs
                                foreach ($changedLocaleIds as $localeId)
                                {
                                    $criteria = craft()->elements->getCriteria('Market_Product');
                                    $criteria->id = $productTypeId;
                                    $criteria->locale = $localeId;
                                    $criteria->status = null;
                                    $updateProduct = $criteria->first();

                                    // todo: replace the getContent()->id check with 'strictLocale' param once it's added
                                    if ($updateProduct && $updateProduct->getContent()->id)
                                    {
                                        craft()->elements->updateElementSlugAndUri($updateProduct, false, false);
                                    }
                                }
                            }
                        }
                    }
                }

                MarketDbHelper::commitStackedTransaction();
            }
            catch (\Exception $e)
            {
                MarketDbHelper::rollbackStackedTransaction();

                throw $e;
            }

            return true;
        }
        else
        {
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
    public function deleteById ($id)
    {
        MarketDbHelper::beginStackedTransaction();
        try {
            $productType = $this->getById($id);

            $query = craft()->db->createCommand()
                ->select('id')
                ->from('market_products')
                ->where(['typeId' => $productType->id]);
            $productIds = $query->queryColumn();

            foreach($productIds as $id){
                craft()->elements->deleteElementById($id);
            }

            $fieldLayoutId = $productType->asa('productFieldLayout')->getFieldLayout()->id;
            craft()->fields->deleteLayoutById($fieldLayoutId);
            if($productType->hasVariants){
                craft()->fields->deleteLayoutById($productType->asa('variantFieldLayout')->getFieldLayout()->id);
            }

            $productTypeRecord = Market_ProductTypeRecord::model()->findById($productType->id);
            $affectedRows = $productTypeRecord->delete();

            if($affectedRows){
                MarketDbHelper::commitStackedTransaction();
            }

            return (bool)$affectedRows;
        }
        catch (\Exception $e)
        {
            MarketDbHelper::rollbackStackedTransaction();

            throw $e;
        }
    }

    // Need to have a separate controller action and service method
    // since you cant have 2 field layout editors on one POST.
    public function saveVariantFieldLayout ($productType)
    {
        $productTypeRecord = Market_ProductTypeRecord::model()->findById($productType->id);

        $productTypeRecord->save(false);

        return true;
    }

    public function handleDeleteSiteLocale ()
    {
        // TODO...
    }
}