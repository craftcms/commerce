<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\services;

use Craft;
use craft\commerce\elements\Product;
use craft\commerce\events\ProductTypeEvent;
use craft\commerce\models\ProductType;
use craft\commerce\models\ProductTypeSite;
use craft\commerce\records\Product as ProductRecord;
use craft\commerce\records\ProductType as ProductTypeRecord;
use craft\commerce\records\ProductTypeSite as ProductTypeSiteRecord;
use craft\db\Query;
use craft\errors\ProductTypeNotFoundException;
use craft\events\SiteEvent;
use craft\queue\jobs\ResaveElements;
use yii\base\Component;
use yii\base\Exception;

/**
 * Product type service.
 *
 * @property array|ProductType[] $allProductTypes all product types
 * @property array $allProductTypeIds all of the product type IDs
 * @property array|ProductType[] $editableProductTypes all editable product types
 * @property array $editableProductTypeIds all of the product type IDs that are editable by the current user
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class ProductTypes extends Component
{
    // Constants
    // =========================================================================

    /**
     * @event ProductTypeEvent The event that is triggered before a category group is saved.
     *
     * Plugins can get notified before a product type is being saved.
     *
     * ```php
     * use craft\commerce\events\ProductTypeEvent;
     * use craft\commerce\services\ProductTypes;
     * use yii\base\Event;
     *
     * Event::on(ProductTypes::class, ProductTypes::EVENT_BEFORE_SAVE_PRODUCTTYPE, function(ProductTypeEvent $e) {
     *      // Maybe create an audit trail of this action.
     * });
     * ```
     */
    const EVENT_BEFORE_SAVE_PRODUCTTYPE = 'beforeSaveProductType';

    /**
     * @event ProductTypeEvent The event that is triggered after a product type is saved.
     *
     * Plugins can get notified after a product type has been saved.
     *
     * ```php
     * use craft\commerce\events\ProductTypeEvent;
     * use craft\commerce\services\ProductTypes;
     * use yii\base\Event;
     *
     * Event::on(ProductTypes::class, ProductTypes::EVENT_AFTER_SAVE_PRODUCTTYPE, function(ProductTypeEvent $e) {
     *      // Maybe prepare some 3rd party system for a new product type
     * });
     * ```
     */
    const EVENT_AFTER_SAVE_PRODUCTTYPE = 'afterSaveProductType';

    // Properties
    // =========================================================================

    /**
     * @var bool
     */
    private $_fetchedAllProductTypes = false;

    /**
     * @var ProductType[]
     */
    private $_productTypesById;

    /**
     * @var ProductType[]
     */
    private $_productTypesByHandle;

    /**
     * @var int[]
     */
    private $_allProductTypeIds;

    /**
     * @var int[]
     */
    private $_editableProductTypeIds;

    /**
     * @var ProductTypeSite[][]
     */
    private $_siteSettingsByProductId = [];

    // Public Methods
    // =========================================================================

    /**
     * Returns all editable product types.
     *
     * @return ProductType[] An array of all the editable product types.
     */
    public function getEditableProductTypes(): array
    {
        $editableProductTypeIds = $this->getEditableProductTypeIds();
        $editableProductTypes = [];

        foreach ($this->getAllProductTypes() as $productTypes) {
            if (in_array($productTypes->id, $editableProductTypeIds, false)) {
                $editableProductTypes[] = $productTypes;
            }
        }

        return $editableProductTypes;
    }

    /**
     * Returns all of the product type IDs that are editable by the current user.
     *
     * @return array An array of all the editable product types’ IDs.
     */
    public function getEditableProductTypeIds(): array
    {
        if (null === $this->_editableProductTypeIds) {
            $this->_editableProductTypeIds = [];
            $allProductTypeIds = $this->getAllProductTypeIds();

            foreach ($allProductTypeIds as $productTypeId) {
                if (Craft::$app->getUser()->checkPermission('commerce-manageProductType:' . $productTypeId)) {
                    $this->_editableProductTypeIds[] = $productTypeId;
                }
            }
        }

        return $this->_editableProductTypeIds;
    }

    /**
     * Returns all of the product type IDs.
     *
     * @return array An array of all the product types’ IDs.
     */
    public function getAllProductTypeIds(): array
    {
        if (null === $this->_allProductTypeIds) {
            $this->_allProductTypeIds = [];
            $productTypes = $this->getAllProductTypes();

            foreach ($productTypes as $productType) {
                $this->_allProductTypeIds[] = $productType->id;
            }
        }

        return $this->_allProductTypeIds;
    }

    /**
     * Returns all product types.
     *
     * @return ProductType[] An array of all product types.
     */
    public function getAllProductTypes(): array
    {
        if (!$this->_fetchedAllProductTypes) {
            $results = $this->_createProductTypeQuery()->all();

            foreach ($results as $result) {
                $this->_memoizeProductType(new ProductType($result));
            }

            $this->_fetchedAllProductTypes = true;
        }

        return $this->_productTypesById ?: [];
    }

    /**
     * Returns a product type by its handle.
     *
     * @param string $handle The product type's handle.
     * @return ProductType|null The product type or `null`.
     */
    public function getProductTypeByHandle($handle)
    {
        if (isset($this->_productTypesByHandle[$handle])) {
            return $this->_productTypesByHandle[$handle];
        }

        if ($this->_fetchedAllProductTypes) {
            return null;
        }

        $result = $this->_createProductTypeQuery()
            ->where(['handle' => $handle])
            ->one();

        if (!$result) {
            return null;
        }

        $this->_memoizeProductType(new ProductType($result));

        return $this->_productTypesByHandle[$handle];
    }

    /**
     * Returns an array of product type site settings for a product type by its ID.
     *
     * @param int $productTypeId the product type ID
     * @return array The product type settings.
     */
    public function getProductTypeSites($productTypeId): array
    {
        if (!isset($this->_siteSettingsByProductId[$productTypeId])) {
            $rows = (new Query())
                ->select([
                    'id',
                    'productTypeId',
                    'siteId',
                    'uriFormat',
                    'hasUrls',
                    'template'
                ])
                ->from('{{%commerce_producttypes_sites}}')
                ->where(['productTypeId' => $productTypeId])
                ->all();

            $this->_siteSettingsByProductId[$productTypeId] = [];

            foreach ($rows as $row) {
                $this->_siteSettingsByProductId[$productTypeId][] = new ProductTypeSite($row);
            }
        }

        return $this->_siteSettingsByProductId[$productTypeId];
    }

    /**
     * Saves a product type.
     *
     * @param ProductType $productType The product type model.
     * @param bool $runValidation If validation should be ran.
     * @return bool Whether the product type was saved successfully.
     * @throws \Throwable if reasons
     */
    public function saveProductType(ProductType $productType, bool $runValidation = true): bool
    {
        $isNewProductType = !$productType->id;

        // Fire a 'beforeSaveProductType' event
        if ($this->hasEventHandlers(self::EVENT_BEFORE_SAVE_PRODUCTTYPE)) {
            $this->trigger(self::EVENT_BEFORE_SAVE_PRODUCTTYPE, new ProductTypeEvent([
                'productType' => $productType,
                'isNew' => $isNewProductType,
            ]));
        }

        if ($runValidation && !$productType->validate()) {
            Craft::info('Product type not saved due to validation error.', __METHOD__);

            return false;
        }

        if (!$isNewProductType) {
            $productTypeRecord = ProductTypeRecord::findOne($productType->id);

            if (!$productTypeRecord) {
                throw new ProductTypeNotFoundException("No product type exists with the ID '{$productType->id}'");
            }

            $oldProductTypeRow = $this->_createProductTypeQuery()
                ->where(['id' => $productType->id])
                ->one();
            $oldProductType = new ProductType($oldProductTypeRow);
        } else {
            $productTypeRecord = new ProductTypeRecord();
        }

        // If the product type does not have variants, default the title format.
        if (!$isNewProductType && !$productType->hasVariants) {
            $productType->hasVariantTitleField = false;
            $productType->titleFormat = '{product.title}';
        }

        $productTypeRecord->name = $productType->name;
        $productTypeRecord->handle = $productType->handle;

        $productTypeRecord->hasDimensions = $productType->hasDimensions;
        $productTypeRecord->hasVariants = $productType->hasVariants;
        $productTypeRecord->hasVariantTitleField = $productType->hasVariantTitleField;
        $productTypeRecord->titleFormat = $productType->titleFormat ?: '{product.title}';
        $productTypeRecord->skuFormat = $productType->skuFormat;
        $productTypeRecord->descriptionFormat = $productType->descriptionFormat;

        // Get the site settings
        $allSiteSettings = $productType->getSiteSettings();

        // Make sure they're all there
        foreach (Craft::$app->getSites()->getAllSiteIds() as $siteId) {
            if (!isset($allSiteSettings[$siteId])) {
                throw new Exception('Tried to save a product type that is missing site settings');
            }
        }

        $db = Craft::$app->getDb();
        $transaction = $db->beginTransaction();

        try {
            // Product Field Layout
            $fieldLayout = $productType->getProductFieldLayout();
            Craft::$app->getFields()->saveLayout($fieldLayout);
            $productType->fieldLayoutId = $fieldLayout->id;
            $productTypeRecord->fieldLayoutId = $fieldLayout->id;

            // Variant Field Layout
            $variantFieldLayout = $productType->getVariantFieldLayout();
            Craft::$app->getFields()->saveLayout($variantFieldLayout);
            $productType->variantFieldLayoutId = $variantFieldLayout->id;
            $productTypeRecord->variantFieldLayoutId = $variantFieldLayout->id;

            // Save the product type
            $productTypeRecord->save(false);

            // Now that we have a product type ID, save it on the model
            if (!$productType->id) {
                $productType->id = $productTypeRecord->id;
            }

            // Might as well update our cache of the product type while we have it.
            $this->_productTypesById[$productType->id] = $productType;

            // Have any of the product type categories changed?
            if (!$isNewProductType) {
                // Get all previous categories
                $oldShippingCategories = $oldProductType->getShippingCategories();
                $oldTaxCategories = $oldProductType->getTaxCategories();
            }

            // Remove all existing categories
            Craft::$app->getDb()->createCommand()->delete('{{%commerce_producttypes_shippingcategories}}', ['productTypeId' => $productType->id])->execute();
            Craft::$app->getDb()->createCommand()->delete('{{%commerce_producttypes_taxcategories}}', ['productTypeId' => $productType->id])->execute();

            // Add back the new categories
            foreach ($productType->getShippingCategories() as $shippingCategory) {
                $data = ['productTypeId' => $productType->id, 'shippingCategoryId' => $shippingCategory->id];
                Craft::$app->getDb()->createCommand()->insert('{{%commerce_producttypes_shippingcategories}}', $data)->execute();
            }

            foreach ($productType->getTaxCategories() as $taxCategory) {
                $data = ['productTypeId' => $productType->id, 'taxCategoryId' => $taxCategory->id];
                Craft::$app->getDb()->createCommand()->insert('{{%commerce_producttypes_taxcategories}}', $data)->execute();
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

            // Update the site settings
            // -----------------------------------------------------------------

            $sitesNowWithoutUrls = [];
            $sitesWithNewUriFormats = [];

            if (!$isNewProductType) {
                // Get the old product type site settings
                $allOldSiteSettingsRecords = ProductTypeSiteRecord::find()
                    ->where(['productTypeId' => $productType->id])
                    ->indexBy('siteId')
                    ->all();
            }

            /** @var ProductTypeSiteRecord $siteSettings */
            foreach ($allSiteSettings as $siteId => $siteSettings) {
                // Was this already selected?
                if (!$isNewProductType && isset($allOldSiteSettingsRecords[$siteId])) {
                    $siteSettingsRecord = $allOldSiteSettingsRecords[$siteId];
                } else {
                    $siteSettingsRecord = new ProductTypeSiteRecord();
                    $siteSettingsRecord->productTypeId = $productType->id;
                    $siteSettingsRecord->siteId = $siteId;
                }

                $siteSettingsRecord->hasUrls = $siteSettings->hasUrls;
                $siteSettingsRecord->uriFormat = $siteSettings->uriFormat;
                $siteSettingsRecord->template = $siteSettings->template;

                if (!$siteSettingsRecord->getIsNewRecord()) {
                    // Did it used to have URLs, but not anymore?
                    if ($siteSettingsRecord->isAttributeChanged('hasUrls', false) && !$siteSettings->hasUrls) {
                        $sitesNowWithoutUrls[] = $siteId;
                    }

                    // Does it have URLs, and has its URI format changed?
                    if ($siteSettings->hasUrls && $siteSettingsRecord->isAttributeChanged('uriFormat', false)) {
                        $sitesWithNewUriFormats[] = $siteId;
                    }
                }

                $siteSettingsRecord->save(false);

                // Set the ID on the model
                $siteSettings->id = $siteSettingsRecord->id;
            }

            if (!$isNewProductType) {
                // Drop any site settings that are no longer being used, as well as the associated product/element
                // site rows
                $siteIds = array_keys($allSiteSettings);

                /** @noinspection PhpUndefinedVariableInspection */
                foreach ($allOldSiteSettingsRecords as $siteId => $siteSettingsRecord) {
                    if (!in_array($siteId, $siteIds, false)) {
                        $siteSettingsRecord->delete();
                    }
                }
            }

            // Finally, deal with the existing products, updating their urls
            if (!$isNewProductType) {
                foreach ($allSiteSettings as $siteId => $siteSettings) {
                    Craft::$app->getQueue()->push(new ResaveElements([
                        'description' => Craft::t('commerce', 'Resaving {type} products ({site})', [
                            'type' => $productType->name,
                            'site' => $siteSettings->getSite()->name,
                        ]),
                        'elementType' => Product::class,
                        'criteria' => [
                            'siteId' => $siteId,
                            'typeId' => $productType->id,
                            'status' => null,
                            'enabledForSite' => false,
                        ]
                    ]));
                }
            }

            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();

            throw $e;
        }

        // Fire an 'afterSaveProductType' event
        if ($this->hasEventHandlers(self::EVENT_AFTER_SAVE_PRODUCTTYPE)) {
            $this->trigger(self::EVENT_AFTER_SAVE_PRODUCTTYPE, new ProductTypeEvent([
                'productType' => $productType,
                'isNew' => $isNewProductType,
            ]));
        }

        return true;
    }

    /**
     * Deletes a product type by its ID.
     *
     * @param int $id the product type's ID
     * @return bool Whether the product type was deleted successfully.
     * @throws \Throwable if reasons
     */
    public function deleteProductTypeById(int $id): bool
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
                Craft::$app->getElements()->deleteElement($product);
            }

            $fieldLayoutId = $productType->getProductFieldLayout()->id;
            Craft::$app->getFields()->deleteLayoutById($fieldLayoutId);
            if ($productType->hasVariants) {
                Craft::$app->getFields()->deleteLayoutById($productType->getVariantFieldLayout()->id);
            }

            $productTypeRecord = ProductTypeRecord::findOne($productType->id);
            $affectedRows = $productTypeRecord->delete();

            if ($affectedRows) {
                $transaction->commit();
            }

            return (bool)$affectedRows;
        } catch (\Throwable $e) {
            $transaction->rollBack();

            throw $e;
        }
    }

    /**
     * Returns a product type by its ID.
     *
     * @param int $productTypeId the product type's ID
     * @return ProductType|null either the product type or `null`
     */
    public function getProductTypeById(int $productTypeId)
    {
        if (isset($this->_productTypesById[$productTypeId])) {
            return $this->_productTypesById[$productTypeId];
        }

        if ($this->_fetchedAllProductTypes) {
            return null;
        }

        $result = $this->_createProductTypeQuery()
            ->where(['id' => $productTypeId])
            ->one();

        if (!$result) {
            return null;
        }

        $this->_memoizeProductType(new ProductType($result));

        return $this->_productTypesById[$productTypeId];
    }

    /**
     * Returns whether a product type’s products have URLs, and if the template path is valid.
     *
     * @param ProductType $productType The product for which to validate the template.
     * @param int $siteId The site for which to valid for
     * @return bool Whether the template is valid.
     * @throws Exception
     */
    public function isProductTypeTemplateValid(ProductType $productType, int $siteId): bool
    {
        $productTypeSiteSettings = $productType->getSiteSettings();

        if (isset($productTypeSiteSettings[$siteId]) && $productTypeSiteSettings[$siteId]->hasUrls) {
            // Set Craft to the site template mode
            $view = Craft::$app->getView();
            $oldTemplateMode = $view->getTemplateMode();
            $view->setTemplateMode($view::TEMPLATE_MODE_SITE);

            // Does the template exist?
            $templateExists = Craft::$app->getView()->doesTemplateExist((string)$productTypeSiteSettings[$siteId]->template);

            // Restore the original template mode
            $view->setTemplateMode($oldTemplateMode);

            if ($templateExists) {
                return true;
            }
        }

        return false;
    }

    /**
     * Adds a new product type setting row when a Site is added to Craft.
     *
     * @param SiteEvent $event The event that triggered this.
     */
    public function afterSaveSiteHandler(SiteEvent $event)
    {
        if ($event->isNew) {
            $primarySiteSettings = (new Query())
                ->select(['productTypeId', 'uriFormat', 'template', 'hasUrls'])
                ->from(['{{%commerce_producttypes_sites}}'])
                ->where(['siteId' => $event->oldPrimarySiteId])
                ->one();

            if ($primarySiteSettings) {
                $newSiteSettings = [];

                $newSiteSettings[] = [
                    $primarySiteSettings['productTypeId'],
                    $event->site->id,
                    $primarySiteSettings['uriFormat'],
                    $primarySiteSettings['template'],
                    $primarySiteSettings['hasUrls']
                ];

                Craft::$app->getDb()->createCommand()
                    ->batchInsert(
                        '{{%commerce_producttypes_sites}}',
                        ['productTypeId', 'siteId', 'uriFormat', 'template', 'hasUrls'],
                        $newSiteSettings)
                    ->execute();
            }
        }
    }

    // Private methods
    // =========================================================================

    /**
     * Memoize a product type
     *
     * @param ProductType $productType The product type to memoize.
     */
    private function _memoizeProductType(ProductType $productType)
    {
        $this->_productTypesById[$productType->id] = $productType;
        $this->_productTypesByHandle[$productType->handle] = $productType;
    }

    /**
     * Returns a Query object prepped for retrieving purchasables.
     *
     * @return Query The query object.
     */
    private function _createProductTypeQuery(): Query
    {
        return (new Query())
            ->select([
                'id',
                'fieldLayoutId',
                'variantFieldLayoutId',
                'name',
                'handle',
                'hasDimensions',
                'hasVariants',
                'hasVariantTitleField',
                'titleFormat',
                'skuFormat',
                'descriptionFormat',
            ])
            ->from(['{{%commerce_producttypes}}']);
    }
}
