<?php

namespace craft\commerce\services;

use Craft;
use craft\commerce\elements\Product;
use craft\commerce\models\ProductType;
use craft\commerce\models\ProductTypeSite;
use craft\commerce\models\ShippingCategory;
use craft\commerce\models\TaxCategory;
use craft\commerce\records\Product as ProductRecord;
use craft\commerce\records\ProductType as ProductTypeRecord;
use craft\commerce\records\ProductTypeSite as ProductTypeSiteRecord;
use craft\db\Query;
use craft\tasks\ResaveElements;
use yii\base\Component;

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
class ProductTypes extends Component
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
     * Returns all editable product types.
     *
     * @param string|null $indexBy
     *
     * @return ProductType[] All the editable product types.
     */
    public function getEditableProductTypes($indexBy = null)
    {
        $editableProductTypeIds = $this->getEditableProductTypeIds();
        $editableProductTypes = [];

        foreach ($this->getAllProductTypes() as $productTypes) {
            if (in_array($productTypes->id, $editableProductTypeIds)) {
                if ($indexBy) {
                    $editableProductTypes[$productTypes->$indexBy] = $productTypes;
                } else {
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
        if (null === $this->_editableProductTypeIds) {
            $this->_editableProductTypeIds = [];

            foreach ($this->getAllProductTypeIds() as $productTypeId) {
                if (Craft::$app->getUser()->checkPermission('commerce-manageProductType:'.$productTypeId)) {
                    $this->_editableProductTypeIds[] = $productTypeId;
                }
            }
        }

        return $this->_editableProductTypeIds;
    }

    /**
     * Returns all of the product type IDs.
     *
     * @return array All the product types’ IDs.
     */
    public function getAllProductTypeIds()
    {
        if (null === $this->_allProductTypeIds) {
            $this->_allProductTypeIds = [];

            foreach ($this->getAllProductTypes() as $productType) {
                $this->_allProductTypeIds[] = $productType->id;
            }
        }

        return $this->_allProductTypeIds;
    }

    /**
     * Returns all Product Types
     *
     * @param string|null $indexBy
     *
     * @return ProductType[]
     */
    public function getAllProductTypes($indexBy = null)
    {
        if (!$this->_fetchedAllProductTypes) {
            $results = $this->_createProductTypeQuery()->all();

            if (null === $this->_productTypesById) {
                $this->_productTypesById = [];
            }

            foreach ($results as $result) {
                $productType = new ProductType($result);
                $this->_productTypesById[$productType->id] = $productType;
            }

            $this->_fetchedAllProductTypes = true;
        }

        if ($indexBy == 'id') {
            $productTypes = $this->_productTypesById;
        } else if (!$indexBy) {
            $productTypes = array_values($this->_productTypesById);
        } else {
            $productTypes = [];
            foreach ($this->_productTypesById as $productType) {
                $productTypes[$productType->$indexBy] = $productType;
            }
        }

        return $productTypes;
    }

    /**
     * Returns a DbCommand object prepped for retrieving product types.
     *
     * @return Query
     */
    private function _createProductTypeQuery(): Query
    {
        return (new Query())
            ->select([
                'id',
                'name',
                'handle',
                'hasUrls',
                'hasDimensions',
                'hasVariants',
                'hasVariantTitleField',
                'titleFormat',
                'skuFormat',
                'descriptionFormat',
                'template',
                'fieldLayoutId',
                'variantFieldLayoutId',
            ])
            ->from(['{{%commerce_producttypes}}']);
    }

    /**
     * @param string $handle
     *
     * @return ProductType|null
     */
    public function getProductTypeByHandle($handle)
    {
        $result = $this->_createProductTypeQuery()
            ->where('handle = :handle', [':handle' => $handle])
            ->one();

        if ($result) {
            $productType = new ProductType($result);
            $this->_productTypesById[$productType->id] = $productType;

            return $productType;
        }

        return null;
    }

    /**
     * @param      $productTypeId
     *
     * @return array
     */
    public function getProductTypeSites($productTypeId): array
    {
        $records = ProductTypeSiteRecord::find()->where([
            'productTypeId' => $productTypeId
        ])->all();

        return ProductTypeSite::populateModels($records);
    }

    /**
     * @param      $productTypeId
     * @param null $indexBy
     *
     * @return array
     */
    public function getProductTypeShippingCategories($productTypeId, $indexBy = null)
    {
        $productType = ProductTypeRecord::find()->with('shippingCategories')->where(['id' => $productTypeId])->all();
        if ($productType && $productType->shippingCategories) {
            $shippingCategories = $productType->shippingCategories;
        } else {
            $shippingCategories = [Plugin::getInstance()->getShippingCategories()->getDefaultShippingCategory()];
        }

        return ShippingCategory::populateModels($shippingCategories, $indexBy);
    }

    /**
     * @param      $productTypeId
     * @param null $indexBy
     *
     * @return array
     */
    public function getProductTypeTaxCategories($productTypeId, $indexBy = null): array
    {
        $productType = ProductTypeRecord::find()->where(['id' => $productTypeId])->one();

        if ($productType && $productType->taxCategories) {
            $taxCategories = $productType->taxCategories;
        } else {
            $taxCategories = [Plugin::getInstance()->getTaxCategories()->getDefaultTaxCategory()];
        }

        return TaxCategory::populateModels($taxCategories, $indexBy);
    }


    /**
     * @param ProductType $productType
     *
     * @return bool
     * @throws Exception
     * @throws \CDbException
     * @throws \Exception
     */
    public function saveProductType(ProductType $productType): bool
    {
        $titleFormatChanged = false;

        if ($productType->id) {
            $productTypeRecord = ProductTypeRecord::findOne($productType->id);
            if (!$productTypeRecord) {
                throw new Exception(Craft::t('commerce', 'commerce', 'No product type exists with the ID “{id}”',
                    ['id' => $productType->id]));
            }

            /** @var ProductType[] $oldProductType */
            $oldProductType = ProductType::populateModel($productTypeRecord);
            $isNewProductType = false;
        } else {
            $productTypeRecord = new ProductTypeRecord();
            $isNewProductType = true;
        }

        // If the product type does not have variants, default the title format.
        if (!$isNewProductType && !$productType->hasVariants) {
            $productType->hasVariantTitleField = false;
            $productType->titleFormat = "{product.title}";
        }

        $productTypeRecord->name = $productType->name;
        $productTypeRecord->handle = $productType->handle;
        $productTypeRecord->hasDimensions = $productType->hasDimensions;
        $productTypeRecord->hasUrls = $productType->hasUrls;
        $productTypeRecord->hasVariants = $productType->hasVariants;
        $productTypeRecord->hasVariantTitleField = $productType->hasVariantTitleField;
        $productTypeRecord->titleFormat = $productType->titleFormat ?: "{product.title}";
        $productTypeRecord->skuFormat = $productType->skuFormat;
        $productTypeRecord->descriptionFormat = $productType->descriptionFormat;
        $productTypeRecord->template = $productType->template;

        if (!$isNewProductType && !$productType->hasVariantTitleField) {
            if ($productTypeRecord->titleFormat != $oldProductType->titleFormat) {
                $titleFormatChanged = true;
            }
        }

        // Make sure that all of the URL formats are set properly
        $productTypeSites = $productType->getSites();

        foreach ($productTypeSites as $siteId => $productTypeSite) {
            if ($productType->hasUrls) {
                $urlFormatAttributes = ['urlFormat'];
                $productTypeSite->urlFormatIsRequired = true;

                foreach ($urlFormatAttributes as $attribute) {
                    if (!$productTypeSite->validate([$attribute])) {
                        $productType->addError($attribute.'-'.$siteId, $productTypeSite->getError($attribute));
                    }
                }
            } else {
                $productTypeSite->urlFormat = null;
            }
        }

        $productTypeRecord->validate();
        $productType->addErrors($productTypeRecord->getErrors());

        if (!$productType->hasErrors()) {

            $db = Craft::$app->getDb();
            $transaction = $db->beginTransaction();

            try {

                if (!$isNewProductType) {
                    // If we previously had variants but now don't, delete all non-default variants.
                    if ($oldProductType->hasVariants && !$productType->hasVariants) {
                        $criteria = Product::find();
                        $criteria->typeId = $productType->id;
                        $products = $criteria->all();
                        /** @var Product $product */
                        foreach ($products as $key => $product) {
                            if ($product && $product->getContent()->id) {
                                $defaultVariant = null;
                                // find out default variant
                                foreach ($product->getVariants() as $variant) {
                                    if ($defaultVariant === null || $variant->isDefault) {
                                        $defaultVariant = $variant;
                                    }
                                }
                                // delete all non-default variants
                                foreach ($product->getVariants() as $variant) {
                                    if ($defaultVariant !== $variant) {
                                        Plugin::getInstance()->getVariants()->deleteVariantById($variant->id);
                                    } else {
                                        // The default variant must always be enabled.
                                        $variant->enabled = true;
                                        Plugin::getInstance()->getVariants()->saveVariant($variant);
                                    }
                                }
                            }
                        }
                    }
                }

                // Product Field Layout
                if (!$isNewProductType && $oldProductType->fieldLayoutId) {
                    // Drop the old field layout
                    Craft::$app->getFields()->deleteLayoutById($oldProductType->fieldLayoutId);
                }
                // Save the new one
                $fieldLayout = $productType->getProductFieldLayout();
                Craft::$app->getFields()->saveLayout($fieldLayout);
                $productType->fieldLayoutId = $fieldLayout->id;
                $productTypeRecord->fieldLayoutId = $fieldLayout->id;

                if (!$isNewProductType && $oldProductType->variantFieldLayoutId) {
                    // Drop the old field layout
                    Craft::$app->getFields()->deleteLayoutById($oldProductType->variantFieldLayoutId);
                }
                // Save the new one
                $variantFieldLayout = $productType->getVariantFieldLayout();
                Craft::$app->getFields()->saveLayout($variantFieldLayout);
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


                // Have any of the product type categories changed?
                if (!$isNewProductType) {
                    // Get all previous categories
                    $oldShippingCategories = $oldProductType->getShippingCategories();
                    $oldTaxCategories = $oldProductType->getTaxCategories();
                }

                // Remove all existing categories
                Craft::$app->getDb()->createCommand()->delete('commerce_producttypes_shippingcategories', 'productTypeId = :xid', [':xid' => $productType->id]);
                Craft::$app->getDb()->createCommand()->delete('commerce_producttypes_taxcategories', 'productTypeId = :xid', [':xid' => $productType->id]);

                // Add back the new categories
                foreach ($productType->getShippingCategories() as $category) {
                    $data = ['productTypeId' => $productType->id, 'shippingCategoryId' => $category->id];
                    Craft::$app->getDb()->createCommand()->insert('commerce_producttypes_shippingcategories', $data);
                }

                foreach ($productType->getTaxCategories() as $category) {
                    $data = ['productTypeId' => $productType->id, 'taxCategoryId' => $category->id];
                    Craft::$app->getDb()->createCommand()->insert('commerce_producttypes_taxcategories', $data);
                }

                // Update all products that used the removed tax & shipping categories
                if (!$isNewProductType) {
                    // Grab the new categories
                    $newShippingCategories = $productType->getShippingCategories();
                    $newTaxCategories = $productType->getTaxCategories();

                    // Were any categories removed?
                    $removedShippingCategoryIds = array_diff(array_keys($oldShippingCategories), array_keys($newShippingCategories));
                    $removedTaxCategoryIds = array_diff(array_keys($oldTaxCategories), array_keys($newTaxCategories));

                    // Update all products that used the removed product type shipping categories
                    if ($removedShippingCategoryIds) {
                        $defaultShippingCategory = array_values($newShippingCategories)[0];
                        if ($defaultShippingCategory) {
                            $data = ['shippingCategoryId' => $defaultShippingCategory->id];
                            ProductRecord::updateAll($data, [
                                'shippingCategoryId' => $removedShippingCategoryIds,
                                'typeId' => $productType->id
                            ]);
                        }
                    }

                    // Update all products that used the removed product type tax categories
                    if ($removedTaxCategoryIds) {
                        $defaultTaxCategory = array_values($newTaxCategories)[0];
                        if ($defaultTaxCategory) {
                            $data = ['taxCategoryId' => $defaultTaxCategory->id];
                            ProductRecord::updateAll($data, [
                                'taxCategoryId' => $removedTaxCategoryIds,
                                'typeId' => $productType->id
                            ]);
                        }
                    }
                }

                $newSiteData = [];

                //Refresh all titles for variants of same product type if titleFormat changed.
                if ($productType->hasVariants && !$productType->hasVariantTitleField) {
                    if ($titleFormatChanged) {
                        $criteria = Product::find();
                        $criteria->typeId = $productType->id;
                        $products = $criteria->all();
                        foreach ($products as $product) {
                            foreach ($product->getVariants() as $variant) {
                                $title = Craft::$app->getView()->renderObjectTemplate($productType->titleFormat, $variant);
                                // updates to the same title in all sites
                                Craft::$app->getDb()->createCommand()->update('content',
                                    ['title' => $title],
                                    ['elementId' => $variant->id]
                                );
                            }
                        }
                    }
                }

                if (!$isNewProductType) {
                    // Get the old product type sites
                    $oldSiteRecords = ProductTypeSiteRecord::find()->where([
                        'productTypeId' => $productType->id
                    ])->all();
                    $oldSites = ProductTypeSite::populateModels($oldSiteRecords, 'site');

                    $changedSiteIds = [];
                }

                foreach ($productTypeSites as $siteId => $site) {
                    // Was this already selected?
                    if (!$isNewProductType && isset($oldSites[$siteId])) {
                        $oldSite = $oldSites[$siteId];

                        // Has the URL format changed?
                        if ($site->urlFormat != $oldSite->urlFormat) {
                            Craft::$app->getDb()->createCommand()->update('commerce_producttypes_i18n', [
                                'urlFormat' => $site->urlFormat
                            ], [
                                'id' => $oldSite->id
                            ]);

                            $changedSiteIds[] = $siteId;
                        }
                    } else {
                        $newSiteData[] = [$productType->id, $siteId, $site->urlFormat];
                    }
                }

                // Insert the new sites
                Craft::$app->getDb()->createCommand()->insertAll('commerce_producttypes_i18n',
                    ['productTypeId', 'site', 'urlFormat'],
                    $newSiteData
                );

                if (!$isNewProductType) {
                    // Drop any sites that are no longer being used, as well as the associated element
                    // site rows

                    $droppedSiteIds = array_diff(array_keys($oldSites), array_keys($productTypeSites));

                    if ($droppedSiteIds) {
                        Craft::$app->getDb()->createCommand()->delete('commerce_producttypes_i18n', ['in', 'site', $droppedSiteIds]);
                    }
                }


                if (!$isNewProductType) {
                    // Get all of the product IDs in this group
                    $criteria = Product::find();
                    $criteria->typeId = $productType->id;
                    $criteria->status = null;
                    $criteria->limit = null;
                    $productIds = $criteria->ids();

                    // Should we be deleting
                    if ($productIds && $droppedSiteIds) {
                        Craft::$app->getDb()->createCommand()->delete('elements_i18n', ['and', ['in', 'elementId', $productIds], ['in', 'site', $droppedSiteIds]]);
                        Craft::$app->getDb()->createCommand()->delete('content', ['and', ['in', 'elementId', $productIds], ['in', 'site', $droppedSiteIds]]);
                    }
                    // Are there any sites left?
                    if ($productTypeSites) {
                        // Drop the old productType URIs if the product type no longer has URLs
                        if (!$productType->hasUrls && $oldProductType->hasUrls) {
                            Craft::$app->getDb()->createCommand()->update('elements_i18n',
                                ['uri' => null],
                                ['in', 'elementId', $productIds]
                            );
                        } else if ($changedSiteIds) {
                            foreach ($productIds as $productId) {
                                Craft::$app->getConfig()->maxPowerCaptain();

                                // Loop through each of the changed sites and update all of the products’ slugs and
                                // URIs
                                foreach ($changedSiteIds as $siteId) {
                                    $criteria = Product::find();
                                    $criteria->id = $productId;
                                    $criteria->site = $siteId;
                                    $criteria->status = null;
                                    $updateProduct = $criteria->first();

                                    // @todo replace the getContent()->id check with 'strictSite' param once it's added
                                    if ($updateProduct && $updateProduct->getContent()->id) {
                                        Craft::$app->getElements()->updateElementSlugAndUri($updateProduct, false, false);
                                    }
                                }
                            }
                        }
                    }

                    if (!$isNewProductType) {
                    }

                    if (!$isNewProductType) {

                        // Get the most-primary site that this section was already enabled in
                        $sites = array_values(Craft::$app->getI18n()->getSiteSiteIds());

                        if ($sites) {
                            Craft::$app->getTasks()->queueTask([
                                'type' => ResaveElements::class,
                                'description' => Craft::t('app', 'Resaving {productType} products', ['productType' => $productType->name]),
                                'elementType' => Product::class,
                                'criteria' => [
                                    'siteId' => $sites[0],
                                    'typeId' => $productType->id,
                                    'status' => null,
                                    'enabledForSite' => false,
                                    'limit' => null,
                                ]
                            ]);
                        }
                    }
                }

                $transaction->commit();
            } catch (\Exception $e) {
                $transaction->rollBack();

                throw $e;
            }

            return true;
        }

        return false;
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
        $db = Craft::$app->getDb();
        $transaction = $db->beginTransaction();

        try {
            $productType = $this->getProductTypeById($id);

            $criteria = Product::find();
            $criteria->typeId = $productType->id;
            $criteria->status = null;
            $criteria->limit = null;
            $products = $criteria->all();

            foreach ($products as $product) {
                Plugin::getInstance()->getProducts()->deleteProduct($product);
            }

            $fieldLayoutId = $productType->getProductFieldLayout()->id;
            Craft::$app->getFields()->deleteLayoutById($fieldLayoutId);
            if ($productType->hasVariants) {
                Craft::$app->getFields()->deleteLayoutById($productType->getVariantFieldLayout());
            }

            $productTypeRecord = ProductType::findOne($productType->id);
            $affectedRows = $productTypeRecord->delete();

            if ($affectedRows) {
                $transaction->commit();
            }

            return (bool)$affectedRows;
        } catch (\Exception $e) {
            $transaction->rollBack();

            throw $e;
        }
    }

    /**
     * @param int $productTypeId
     *
     * @return ProductType|null
     */
    public function getProductTypeById($productTypeId)
    {

        if (!$this->_fetchedAllProductTypes && ((null === $this->_productTypesById) || !array_key_exists($productTypeId, $this->_productTypesById))) {
            $result = $this->_createProductTypeQuery()
                ->where('id = :id', [':id' => $productTypeId])
                ->one();

            if ($result) {
                $productType = new ProductType($result);
            } else {
                $productType = null;
            }

            $this->_productTypesById[$productTypeId] = $productType;
        }

        if (!isset($this->_productTypesById[$productTypeId])) {
            return null;
        }

        return $this->_productTypesById[$productTypeId];
    }

    /**
     * Returns whether a product type’s products have URLs, and if the template path is valid.
     *
     * @param ProductType $productType
     *
     * @return bool
     */
    public function isProductTypeTemplateValid(ProductType $productType)
    {
        if ($productType->hasUrls) {
            // Set Craft to the site template mode
            $templatesService = Craft::$app->getView();
            $oldTemplateMode = $templatesService->getTemplateMode();
            $templatesService->setTemplateMode($templatesService::TEMPLATE_MODE_SITE);

            // Does the template exist?
            $templateExists = $templatesService->doesTemplateExist($productType->template);

            // Restore the original template mode
            $templatesService->setTemplateMode($oldTemplateMode);

            if ($templateExists) {
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
    public function addSiteHandler(Event $event)
    {
        /** @var Order $order */
        $siteId = $event->params['siteId'];

        // Add this site to each of the category groups
        $productTypeSites = Craft::$app->getDb()->createCommand()
            ->select('productTypeId, urlFormat')
            ->from('commerce_producttypes_i18n')
            ->where('site = :site', [':site' => Craft::$app->getI18n()->getPrimarySiteSiteId()])
            ->queryAll();

        if ($productTypeSites) {
            $newProductTypeSites = [];

            foreach ($productTypeSites as $productTypeSite) {
                $newProductTypeSites[] = [$productTypeSite['productTypeId'], $siteId, $productTypeSite['urlFormat']];
            }

            Craft::$app->getDb()->createCommand()->insertAll('commerce_producttypes_i18n', ['productTypeId', 'site', 'urlFormat'], $newProductTypeSites);
        }

        return true;
    }
}
