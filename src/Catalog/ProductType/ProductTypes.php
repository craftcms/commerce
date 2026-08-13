<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Catalog\ProductType;

use craft\enums\PropagationMethod;
use craft\events\ConfigEvent;
use craft\events\DeleteSiteEvent;
use craft\events\SiteEvent;
use craft\helpers\Cp;
use craft\helpers\Db as CraftDb;
use craft\helpers\StringHelper;
use CraftCms\Cms\Element\Jobs\ResaveElements;
use CraftCms\Cms\FieldLayout\FieldLayout;
use CraftCms\Cms\ProjectConfig\ProjectConfigHelper;
use CraftCms\Cms\Structure\Data\Structure;
use CraftCms\Cms\Structure\Enums\Mode;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Facades\Fields;
use CraftCms\Cms\Support\Facades\ProjectConfig;
use CraftCms\Cms\Support\Facades\Structures;
use CraftCms\Commerce\Catalog\Elements\Product;
use CraftCms\Commerce\Catalog\Elements\Variant;
use CraftCms\Commerce\Catalog\Events\ProductTypeEvent;
use CraftCms\Commerce\Catalog\Models\ProductTypeSite;
use CraftCms\Commerce\Catalog\ProductType\Data\ProductType;
use CraftCms\Commerce\Catalog\ProductType\Exceptions\ProductTypeNotFoundException;
use CraftCms\Commerce\Catalog\ProductType\Models\ProductType as ProductTypeRecord;
use CraftCms\Commerce\Catalog\ProductType\Models\ProductTypeSite as ProductTypeSiteRecord;
use CraftCms\Commerce\Database\Table;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Product type service.
 *
 * Bridges between the rich {@see ProductType} data object (which needs two independent field
 * layouts — see its class docblock) and the thin {@see ProductTypeRecord} Eloquent model used
 * purely for persistence.
 */
#[Singleton]
class ProductTypes
{
    /**
     * @event ProductTypeEvent The event that is triggered before a product type is saved.
     */
    public const string EVENT_BEFORE_SAVE_PRODUCTTYPE = 'beforeSaveProductType';

    /**
     * @event ProductTypeEvent The event that is triggered after a product type has been saved.
     */
    public const string EVENT_AFTER_SAVE_PRODUCTTYPE = 'afterSaveProductType';

    public const string CONFIG_PRODUCTTYPES_KEY = 'commerce.productTypes';

    /** @var ProductType[]|null */
    private ?array $_allProductTypes = null;

    /** @var array<int, ProductTypeSite[]> */
    private array $_siteSettingsByProductId = [];

    /** @var array<string, ProductType> interim storage for product types being saved via control panel */
    private array $_savingProductTypes = [];

    /**
     * @return ProductType[] An array of all the viewable product types.
     */
    public function getViewableProductTypes(): array
    {
        if (app()->runningInConsole()) {
            return $this->getAllProductTypes();
        }

        $user = request()->craftUser();

        if (!$user) {
            return [];
        }

        $viewableProductTypeIds = $this->getViewableProductTypeIds();

        return collect($this->getAllProductTypes())
            ->filter(fn(ProductType $productType) => in_array($productType->id, $viewableProductTypeIds))
            ->values()
            ->all();
    }

    /**
     * @return array An array of all the viewable product types' IDs.
     */
    public function getViewableProductTypeIds(bool $anySite = false): array
    {
        $viewableIds = [];
        $user = request()->craftUser();
        $allProductTypes = $this->getAllProductTypes();

        $cpSite = Cp::requestedSite();

        foreach ($allProductTypes as $productType) {
            if (!$user->can('commerce-viewProductType:' . $productType->uid)) {
                continue;
            }

            if (!$anySite && $cpSite && !isset($productType->getSiteSettings()[$cpSite->id])) {
                continue;
            }

            $viewableIds[] = $productType->id;
        }

        return $viewableIds;
    }

    /**
     * @return array An array of all the product type IDs that are creatable by the current user.
     */
    public function getCreatableProductTypeIds(): array
    {
        $creatableIds = [];
        $user = request()->craftUser();

        foreach ($this->getAllProductTypes() as $productType) {
            if ($user->can('commerce-createProductType:' . $productType->uid)) {
                $creatableIds[] = $productType->id;
            }
        }

        return $creatableIds;
    }

    /**
     * @return ProductType[]
     */
    public function getCreatableProductTypes(): array
    {
        $creatableProductTypeIds = $this->getCreatableProductTypeIds();

        return collect($this->getAllProductTypes())
            ->filter(fn(ProductType $productType) => in_array($productType->id, $creatableProductTypeIds))
            ->values()
            ->all();
    }

    /**
     * @return int[] An array of all the product types' IDs.
     */
    public function getAllProductTypeIds(): array
    {
        return collect($this->getAllProductTypes())->pluck('id')->all();
    }

    /**
     * @return ProductType[] An array of all product types.
     */
    public function getAllProductTypes(): array
    {
        if ($this->_allProductTypes !== null) {
            return $this->_allProductTypes;
        }

        return $this->_allProductTypes = ProductTypeRecord::query()
            ->get()
            ->map(fn(ProductTypeRecord $record) => $this->_toData($record))
            ->all();
    }

    public function getProductTypeByHandle(string $handle): ?ProductType
    {
        return collect($this->getAllProductTypes())->where('handle', $handle)->first();
    }

    public function getProductTypeById(int $productTypeId): ?ProductType
    {
        return collect($this->getAllProductTypes())->where('id', $productTypeId)->first();
    }

    public function getProductTypeByUid(string $uid): ?ProductType
    {
        return collect($this->getAllProductTypes())->where('uid', $uid)->first();
    }

    /**
     * @return ProductType[]
     */
    public function getProductTypesByTaxCategoryId(int $taxCategoryId): array
    {
        $ids = DB::table(Table::PRODUCTTYPES_TAXCATEGORIES)
            ->where('taxCategoryId', $taxCategoryId)
            ->pluck('productTypeId');

        return collect($this->getAllProductTypes())
            ->filter(fn(ProductType $productType) => $ids->contains($productType->id))
            ->keyBy('id')
            ->all();
    }

    /**
     * @return ProductType[]
     */
    public function getProductTypesByShippingCategoryId(int $shippingCategoryId): array
    {
        $ids = DB::table(Table::PRODUCTTYPES_SHIPPINGCATEGORIES)
            ->where('shippingCategoryId', $shippingCategoryId)
            ->pluck('productTypeId');

        return collect($this->getAllProductTypes())
            ->filter(fn(ProductType $productType) => $ids->contains($productType->id))
            ->keyBy('id')
            ->all();
    }

    /**
     * @return ProductTypeSite[] The product type's site-specific settings.
     */
    public function getProductTypeSites(int $productTypeId): array
    {
        if (!isset($this->_siteSettingsByProductId[$productTypeId])) {
            $this->_siteSettingsByProductId[$productTypeId] = ProductTypeSiteRecord::query()
                ->where('productTypeId', $productTypeId)
                ->get()
                ->map(fn(ProductTypeSiteRecord $record) => $this->_toSiteData($record))
                ->all();
        }

        return $this->_siteSettingsByProductId[$productTypeId];
    }

    /**
     * @throws Throwable
     */
    public function saveProductType(ProductType $productType, bool $runValidation = true): bool
    {
        $isNewProductType = !$productType->id;

        // TODO: migrate event firing to Laravel once event system is bridged
        /** @phpstan-ignore-next-line */
        $legacyService = \craft\commerce\Plugin::getInstance()->getProductTypes();

        if ($legacyService->hasEventHandlers(self::EVENT_BEFORE_SAVE_PRODUCTTYPE)) {
            $event = new ProductTypeEvent(
                productType: $productType,
                isNew: $isNewProductType,
            );
            $legacyService->trigger(self::EVENT_BEFORE_SAVE_PRODUCTTYPE, $event);
        }

        if ($runValidation && !$productType->validate()) {
            Log::info('Product type not saved due to validation error.');

            return false;
        }

        if ($isNewProductType) {
            $productType->uid = StringHelper::UUID();
        } else {
            $existingRecord = ProductTypeRecord::query()->find($productType->id);

            if (!$existingRecord) {
                throw new ProductTypeNotFoundException("No product type exists with the ID '$productType->id'");
            }

            $productType->uid = $existingRecord->uid;
        }

        $this->_savingProductTypes[$productType->uid] = $productType;

        $configData = $productType->getConfig();
        $configPath = self::CONFIG_PRODUCTTYPES_KEY . '.' . $productType->uid;
        ProjectConfig::set($configPath, $configData);

        if ($isNewProductType) {
            $productType->id = CraftDb::idByUid(Table::PRODUCTTYPES, $productType->uid);
        }

        return true;
    }

    /**
     * @throws Throwable
     */
    public function handleChangedProductType(ConfigEvent $event): void
    {
        $productTypeUid = $event->tokenMatches[0];
        $data = $event->newValue;
        $shouldResaveProducts = false;

        ProjectConfigHelper::ensureAllSitesProcessed();
        ProjectConfigHelper::ensureAllFieldsProcessed();

        DB::beginTransaction();

        try {
            $siteData = $data['siteSettings'];

            $record = $this->_getRecord($productTypeUid);
            $isNewProductType = !$record->exists;

            $record->uid = $productTypeUid;
            $record->name = $data['name'];
            $record->handle = $data['handle'];
            $record->enableVersioning = $data['enableVersioning'] ?? false;
            $record->hasDimensions = $data['hasDimensions'];

            $record->productTitleTranslationMethod = $data['productTitleTranslationMethod'] ?? 'site';
            $record->productTitleTranslationKeyFormat = $data['productTitleTranslationKeyFormat'] ?? '';

            $record->propagationMethod = $data['propagationMethod'] ?? PropagationMethod::All->value;

            if ($record->propagationMethod !== $record->getOriginal('propagationMethod')) {
                $shouldResaveProducts = true;
            }

            $record->variantTitleTranslationMethod = $data['variantTitleTranslationMethod'] ?? 'site';
            $record->variantTitleTranslationKeyFormat = $data['variantTitleTranslationKeyFormat'] ?? '';

            $hasVariantTitleField = $data['hasVariantTitleField'];
            $variantTitleFormat = $data['variantTitleFormat'] ?? '{product.title}';
            if ($record->variantTitleFormat != $variantTitleFormat || $record->hasVariantTitleField != $hasVariantTitleField) {
                $shouldResaveProducts = true;
            }
            $record->variantTitleFormat = $variantTitleFormat;
            $record->hasVariantTitleField = $hasVariantTitleField;
            $record->variantUiLabelFormat = $data['variantUiLabelFormat'] ?? '{title}';

            $hasProductTitleField = $data['hasProductTitleField'];
            $productTitleFormat = $data['productTitleFormat'] ?? 'Title';
            if ($record->productTitleFormat != $productTitleFormat || $record->hasProductTitleField != $hasProductTitleField) {
                $shouldResaveProducts = true;
            }
            $record->productTitleFormat = $productTitleFormat;
            $record->hasProductTitleField = $hasProductTitleField;
            $record->productUiLabelFormat = $data['productUiLabelFormat'] ?? '{title}';

            $record->showSlugField = $data['showSlugField'] ?? true;
            $record->slugTranslationMethod = $data['slugTranslationMethod'] ?? 'site';
            $record->slugTranslationKeyFormat = $data['slugTranslationKeyFormat'] ?? null;

            if ($record->maxVariants != $data['maxVariants']) {
                $shouldResaveProducts = true;
            }
            $record->maxVariants = $data['maxVariants'];

            $skuFormat = $data['skuFormat'] ?? '';
            if ($record->skuFormat != $skuFormat) {
                $shouldResaveProducts = true;
            }
            $record->skuFormat = $skuFormat;

            $descriptionFormat = $data['descriptionFormat'] ?? '';
            if ($record->descriptionFormat != $descriptionFormat) {
                $shouldResaveProducts = true;
            }
            $record->descriptionFormat = $descriptionFormat;

            $wasStructure = (bool)$record->isStructure;
            $record->isStructure = $data['isStructure'] ?? false;
            $record->maxLevels = $data['maxLevels'] ?? null;
            $record->defaultPlacement = $data['defaultPlacement'] ?? ProductType::DEFAULT_PLACEMENT_BEGINNING;
            if ($record->isStructure != $wasStructure) {
                $shouldResaveProducts = true;
            }

            if (!empty($data['previewTargets'])) {
                $record->previewTargets = ProjectConfigHelper::unpackAssociativeArray($data['previewTargets']);
            } else {
                $record->previewTargets = null;
            }

            if (!empty($data['productFieldLayouts']) && !empty($config = reset($data['productFieldLayouts']))) {
                $layout = FieldLayout::createFromConfig($config);
                $layout->id = $record->fieldLayoutId;
                $layout->type = Product::class;
                $layout->uid = key($data['productFieldLayouts']);
                Fields::saveLayout($layout, false);
                $record->fieldLayoutId = $layout->id;
            } elseif ($record->fieldLayoutId) {
                Fields::deleteLayoutById($record->fieldLayoutId);
                $record->fieldLayoutId = null;
            }

            if (!empty($data['variantFieldLayouts']) && !empty($config = reset($data['variantFieldLayouts']))) {
                $layout = FieldLayout::createFromConfig($config);
                $layout->id = $record->variantFieldLayoutId;
                $layout->type = Variant::class;
                $layout->uid = key($data['variantFieldLayouts']);
                Fields::saveLayout($layout, false);
                $record->variantFieldLayoutId = $layout->id;
            } elseif ($record->variantFieldLayoutId) {
                Fields::deleteLayoutById($record->variantFieldLayoutId);
                $record->variantFieldLayoutId = null;
            }

            $isNewStructure = false;
            if ($record->isStructure) {
                $structureUid = $data['structure']['uid'];
                $structure = Structures::getStructureByUid($structureUid, true) ?? new Structure(['uid' => $structureUid]);
                $isNewStructure = empty($structure->id);
                $structure->maxLevels = $data['maxLevels'] ?? null;
                Structures::saveStructure($structure);
                $record->structureId = $structure->id;
            } else {
                if ($record->structureId) {
                    Structures::deleteStructureById($record->structureId);
                }

                $record->structureId = null;
            }

            $record->dateUpdated = now()->toDateTimeString();
            if ($isNewProductType) {
                $record->dateCreated = $record->dateUpdated;
            }

            $record->save();

            // Update the site settings
            $sitesNowWithoutUrls = [];
            $sitesWithNewUriFormats = [];
            $allOldSiteSettingsRecords = [];

            if (!$isNewProductType) {
                $allOldSiteSettingsRecords = ProductTypeSiteRecord::query()
                    ->where('productTypeId', $record->id)
                    ->get()
                    ->keyBy('siteId')
                    ->all();
            }

            $siteIdMap = CraftDb::idsByUids('{{%sites}}', array_keys($siteData));

            foreach ($siteData as $siteUid => $siteSettings) {
                $siteId = $siteIdMap[$siteUid];

                if (!$isNewProductType && isset($allOldSiteSettingsRecords[$siteId])) {
                    $siteSettingsRecord = $allOldSiteSettingsRecords[$siteId];
                    $wasNew = false;
                    $hadUrls = (bool)$siteSettingsRecord->hasUrls;
                    $oldUriFormat = $siteSettingsRecord->uriFormat;
                } else {
                    $siteSettingsRecord = new ProductTypeSiteRecord();
                    $siteSettingsRecord->productTypeId = $record->id;
                    $siteSettingsRecord->siteId = $siteId;
                    $wasNew = true;
                    $hadUrls = false;
                    $oldUriFormat = null;
                }

                $siteSettingsRecord->enabledByDefault = (bool)($siteSettings['enabledByDefault'] ?? true);

                if ($siteSettingsRecord->hasUrls = $siteSettings['hasUrls']) {
                    $siteSettingsRecord->uriFormat = $siteSettings['uriFormat'];
                    $siteSettingsRecord->template = $siteSettings['template'];
                } else {
                    $siteSettingsRecord->uriFormat = null;
                    $siteSettingsRecord->template = null;
                }

                if (!$wasNew) {
                    if ($hadUrls && !$siteSettings['hasUrls']) {
                        $sitesNowWithoutUrls[] = $siteId;
                    }

                    if ($siteSettings['hasUrls'] && $oldUriFormat !== $siteSettingsRecord->uriFormat) {
                        $sitesWithNewUriFormats[] = $siteId;
                    }
                }

                $siteSettingsRecord->dateUpdated = now()->toDateTimeString();
                if ($wasNew) {
                    $siteSettingsRecord->dateCreated = $siteSettingsRecord->dateUpdated;
                }

                $siteSettingsRecord->save();
            }

            if (!$isNewProductType) {
                $affectedSiteUids = array_keys($siteData);

                foreach ($allOldSiteSettingsRecords as $siteId => $siteSettingsRecord) {
                    $siteUid = array_search($siteId, $siteIdMap, false);
                    if (!in_array($siteUid, $affectedSiteUids, false)) {
                        $siteSettingsRecord->delete();
                        $shouldResaveProducts = true;
                    }
                }
            }

            if ($record->isStructure && !$isNewProductType && $isNewStructure) {
                $this->_populateNewStructure($record);
            }

            if (!$isNewProductType) {
                $productIds = Product::find()
                    ->typeId($record->id)
                    ->status(null)
                    ->limit(null)
                    ->ids();

                if (!empty($siteData)) {
                    if (!empty($sitesNowWithoutUrls)) {
                        DB::table('elements_sites')
                            ->whereIn('elementId', $productIds)
                            ->whereIn('siteId', $sitesNowWithoutUrls)
                            ->update(['uri' => null]);
                    } elseif (!empty($sitesWithNewUriFormats)) {
                        foreach ($productIds as $productId) {
                            foreach ($sitesWithNewUriFormats as $siteId) {
                                $product = Product::find()
                                    ->id($productId)
                                    ->siteId($siteId)
                                    ->status(null)
                                    ->one();

                                if ($product) {
                                    Elements::updateElementSlugAndUri($product, false, false);
                                }
                            }
                        }
                    }
                }
            }

            DB::commit();

            if ($shouldResaveProducts) {
                dispatch(new ResaveElements(
                    elementType: Product::class,
                    criteria: [
                        'siteId' => '*',
                        'status' => null,
                        'typeId' => $record->id,
                    ],
                ));
            }
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $this->_allProductTypes = null;
        unset($this->_siteSettingsByProductId[$record->id]);

        // TODO: migrate event firing to Laravel once event system is bridged
        /** @phpstan-ignore-next-line */
        $legacyService = \craft\commerce\Plugin::getInstance()->getProductTypes();
        if ($legacyService->hasEventHandlers(self::EVENT_AFTER_SAVE_PRODUCTTYPE)) {
            $event = new ProductTypeEvent(
                productType: $this->getProductTypeById($record->id),
                isNew: empty($this->_savingProductTypes[$productTypeUid]),
            );
            $legacyService->trigger(self::EVENT_AFTER_SAVE_PRODUCTTYPE, $event);
        }
    }

    public function deleteProductTypeById(int $id): bool
    {
        $productType = $this->getProductTypeById($id);
        ProjectConfig::remove(self::CONFIG_PRODUCTTYPES_KEY . '.' . $productType->uid);
        return true;
    }

    /**
     * @throws Throwable
     */
    public function handleDeletedProductType(ConfigEvent $event): void
    {
        $uid = $event->tokenMatches[0];
        $record = $this->_getRecord($uid);

        if (!$record->id) {
            return;
        }

        DB::beginTransaction();

        try {
            $products = Product::find()
                ->typeId($record->id)
                ->status(null)
                ->limit(null)
                ->all();

            foreach ($products as $product) {
                Elements::deleteElement($product);
            }

            $fieldLayoutId = $record->fieldLayoutId;
            $variantFieldLayoutId = $record->variantFieldLayoutId;
            Fields::deleteLayoutById($fieldLayoutId);

            if ($variantFieldLayoutId) {
                Fields::deleteLayoutById($variantFieldLayoutId);
            }

            $record->delete();
            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $this->_allProductTypes = null;
        unset($this->_siteSettingsByProductId[$record->id]);
    }

    /**
     * Prune a deleted site from product type site settings.
     */
    public function pruneDeletedSite(DeleteSiteEvent $event): void
    {
        $siteUid = $event->site->uid;
        $productTypes = ProjectConfig::get(self::CONFIG_PRODUCTTYPES_KEY);

        if (is_array($productTypes)) {
            foreach ($productTypes as $productTypeUid => $productType) {
                ProjectConfig::remove(self::CONFIG_PRODUCTTYPES_KEY . '.' . $productTypeUid . '.siteSettings.' . $siteUid);
            }
        }
    }

    public function isProductTypeTemplateValid(ProductType $productType, int $siteId): bool
    {
        $productTypeSiteSettings = $productType->getSiteSettings();

        if (isset($productTypeSiteSettings[$siteId]) && $productTypeSiteSettings[$siteId]->hasUrls && $productTypeSiteSettings[$siteId]->template) {
            $view = \Craft::$app->getView();
            $oldTemplateMode = $view->getTemplateMode();
            $view->setTemplateMode($view::TEMPLATE_MODE_SITE);

            $templateExists = $view->doesTemplateExist($productTypeSiteSettings[$siteId]->template);

            $view->setTemplateMode($oldTemplateMode);

            return $templateExists;
        }

        return false;
    }

    /**
     * Adds a new product type setting row when a Site is added to Craft.
     */
    public function afterSaveSiteHandler(SiteEvent $event): void
    {
        if ($event->isNew && isset($event->oldPrimarySiteId)) {
            $oldPrimarySiteUid = CraftDb::uidById('{{%sites}}', $event->oldPrimarySiteId);
            $existingProductTypeSettings = ProjectConfig::get(self::CONFIG_PRODUCTTYPES_KEY);

            if (!ProjectConfig::isApplyingExternalChanges() && is_array($existingProductTypeSettings)) {
                foreach ($existingProductTypeSettings as $productTypeUid => $settings) {
                    $primarySiteSettings = $settings['siteSettings'][$oldPrimarySiteUid] ?? null;
                    if ($primarySiteSettings === null) {
                        continue;
                    }

                    $configPath = self::CONFIG_PRODUCTTYPES_KEY . '.' . $productTypeUid . '.siteSettings.' . $event->site->uid;
                    ProjectConfig::set($configPath, $primarySiteSettings);
                }
            }
        }
    }

    /**
     * Adds existing products to a newly-created structure, when a product type is converted to Orderable.
     */
    private function _populateNewStructure(ProductTypeRecord $record): void
    {
        $query = Product::find()
            ->typeId($record->id)
            ->drafts(null)
            ->draftOf(false)
            ->site('*')
            ->unique()
            ->status(null)
            ->orderBy(['id' => SORT_ASC])
            ->withStructure(false);

        $query->cursor()->each(
            fn(Product $product) => Structures::appendToRoot($record->structureId, $product, Mode::Insert)
        );
    }

    private function _getRecord(string $uid): ProductTypeRecord
    {
        return ProductTypeRecord::query()->where('uid', $uid)->first() ?? new ProductTypeRecord();
    }

    /**
     * Hydrates a rich {@see ProductType} data object from a persisted {@see ProductTypeRecord} row.
     */
    private function _toData(ProductTypeRecord $record): ProductType
    {
        $productType = new ProductType();
        $productType->id = $record->id;
        $productType->name = $record->name;
        $productType->handle = $record->handle;
        $productType->enableVersioning = (bool)$record->enableVersioning;
        $productType->hasDimensions = (bool)$record->hasDimensions;
        $productType->maxVariants = $record->maxVariants;
        $productType->hasVariantTitleField = (bool)$record->hasVariantTitleField;
        $productType->variantTitleFormat = $record->variantTitleFormat;
        $productType->variantUiLabelFormat = $record->variantUiLabelFormat ?? '{title}';
        $productType->variantTitleTranslationMethod = $record->variantTitleTranslationMethod ?? 'site';
        $productType->variantTitleTranslationKeyFormat = $record->variantTitleTranslationKeyFormat;
        $productType->hasProductTitleField = (bool)$record->hasProductTitleField;
        $productType->productTitleFormat = $record->productTitleFormat ?? '';
        $productType->productUiLabelFormat = $record->productUiLabelFormat ?? '{title}';
        $productType->productTitleTranslationMethod = $record->productTitleTranslationMethod ?? 'site';
        $productType->productTitleTranslationKeyFormat = $record->productTitleTranslationKeyFormat;
        $productType->showSlugField = (bool)$record->showSlugField;
        $productType->slugTranslationMethod = $record->slugTranslationMethod ?? 'site';
        $productType->slugTranslationKeyFormat = $record->slugTranslationKeyFormat;
        $productType->skuFormat = $record->skuFormat;
        $productType->descriptionFormat = $record->descriptionFormat;
        $productType->isStructure = (bool)$record->isStructure;
        $productType->maxLevels = $record->maxLevels;
        $productType->defaultPlacement = $record->defaultPlacement;
        $productType->structureId = $record->structureId;
        $productType->fieldLayoutId = $record->fieldLayoutId;
        $productType->variantFieldLayoutId = $record->variantFieldLayoutId;
        $productType->uid = $record->uid;
        $productType->previewTargets = $record->previewTargets;
        $productType->propagationMethod = PropagationMethod::from($record->propagationMethod);

        return $productType;
    }

    /**
     * Hydrates a {@see ProductTypeSite} data object from a persisted {@see ProductTypeSiteRecord} row.
     *
     * Built via explicit property assignment rather than passing `$record->getAttributes()` into the
     * constructor's config array — the Eloquent record's attributes include `dateCreated`/`dateUpdated`/
     * `uid`, none of which `ProductTypeSite` declares, and `Component::__set()` throws
     * `UnknownPropertyException` for genuinely undeclared properties rather than silently ignoring them.
     */
    private function _toSiteData(ProductTypeSiteRecord $record): ProductTypeSite
    {
        $site = new ProductTypeSite();
        $site->id = $record->id;
        $site->productTypeId = $record->productTypeId;
        $site->siteId = $record->siteId;
        $site->hasUrls = (bool)$record->hasUrls;
        $site->uriFormat = $record->uriFormat;
        $site->template = $record->template;
        $site->enabledByDefault = (bool)$record->enabledByDefault;

        return $site;
    }
}
