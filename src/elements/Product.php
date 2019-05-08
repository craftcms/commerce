<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\elements;

use Craft;
use craft\base\Element;
use craft\base\Model;
use craft\commerce\elements\actions\CreateDiscount;
use craft\commerce\elements\actions\CreateSale;
use craft\commerce\elements\actions\DeleteProduct;
use craft\commerce\elements\db\ProductQuery;
use craft\commerce\helpers\Product as ProductHelper;
use craft\commerce\helpers\VariantMatrix;
use craft\commerce\models\ProductType;
use craft\commerce\models\ShippingCategory;
use craft\commerce\models\TaxCategory;
use craft\commerce\Plugin;
use craft\commerce\records\Product as ProductRecord;
use craft\db\Query;
use craft\elements\actions\CopyReferenceTag;
use craft\elements\actions\Restore;
use craft\elements\actions\SetStatus;
use craft\elements\db\ElementQuery;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\ArrayHelper;
use craft\helpers\DateTimeHelper;
use craft\helpers\ElementHelper;
use craft\helpers\UrlHelper;
use craft\validators\DateTimeValidator;
use yii\base\Exception;
use yii\base\InvalidConfigException;

/**
 * Product model.
 *
 * @property Variant $defaultVariant the default variant
 * @property string $eagerLoadedElements some eager-loaded elements on a given handle
 * @property string $editorHtml the HTML for the element’s editor HUD
 * @property null|ShippingCategory $shippingCategory the shipping category
 * @property string $snapshot allow the variant to ask the product what data to snapshot
 * @property int $totalStock
 * @property bool $hasUnlimitedStock whether at least one variant has unlimited stock
 * @property \craft\commerce\elements\Variant $cheapestVariant
 * @property \craft\commerce\models\ProductType $type
 * @property Variant[]|array $variants an array of the product's variants
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class Product extends Element
{
    // Constants
    // =========================================================================

    const STATUS_LIVE = 'live';
    const STATUS_PENDING = 'pending';
    const STATUS_EXPIRED = 'expired';

    // Properties
    // =========================================================================

    /**
     * @var \DateTime Post date
     */
    public $postDate;

    /**
     * @var \DateTime Expiry date
     */
    public $expiryDate;

    /**
     * @var int Product type ID
     */
    public $typeId;

    /**
     * @var int Tax category ID
     */
    public $taxCategoryId;

    /**
     * @var int Shipping category ID
     */
    public $shippingCategoryId;

    /**
     * @var bool Whether the product is promotable
     */
    public $promotable;

    /**
     * @var bool Whether the product has free shipping
     */
    public $freeShipping;

    /**
     * @inheritdoc
     */
    public $enabled;

    /**
     * @var bool Is this product available to be purchased
     */
    public $availableForPurchase = true;

    /**
     * @var int defaultVariantId
     */
    public $defaultVariantId;

    /**
     * @var string Default SKU
     */
    public $defaultSku;

    /**
     * @var float Default price
     */
    public $defaultPrice;

    /**
     * @var float Default height
     */
    public $defaultHeight;

    /**
     * @var float Default length
     */
    public $defaultLength;

    /**
     * @var float Default width
     */
    public $defaultWidth;

    /**
     * @var float Default weight
     */
    public $defaultWeight;

    /**
     * @var TaxCategory Tax category
     */
    public $taxCategory;

    /**
     * @var string Name
     */
    public $name;

    /**
     * @var Variant[] This product’s variants
     */
    private $_variants;

    /**
     * @var Variant This product's default variant
     */
    private $_defaultVariant;

    /**
     * @var Variant This product's cheapest variant
     */
    private $_cheapestVariant;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('commerce', 'Product');
    }


    /**
     * @inheritdoc
     */
    public static function refHandle()
    {
        return 'product';
    }

    /**
     * @inheritdoc
     */
    public static function hasContent(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public static function hasTitles(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public static function hasUris(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public static function isLocalized(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     * @return ProductQuery The newly created [[ProductQuery]] instance.
     */
    public static function find(): ElementQueryInterface
    {
        return new ProductQuery(static::class);
    }

    /**
     * @inheritdoc
     */
    public function __toString(): string
    {
        return (string)$this->title;
    }

    /**
     * @inheritdoc
     */
    public function getIsEditable(): bool
    {
        if ($this->getType()) {
            $uid = $this->getType()->uid;

            return Craft::$app->getUser()->checkPermission('commerce-manageProductType:' . $uid);
        }

        return false;
    }

    /**
     * Returns the product's product type.
     *
     * @return ProductType
     * @throws InvalidConfigException
     */
    public function getType(): ProductType
    {
        if ($this->typeId === null) {
            throw new InvalidConfigException('Product is missing its product type ID');
        }

        $productType = Plugin::getInstance()->getProductTypes()->getProductTypeById($this->typeId);

        if (null === $productType) {
            throw new InvalidConfigException('Invalid product type ID: ' . $this->typeId);
        }

        return $productType;
    }

    /**
     * Allows the variant to ask the product what data to snapshot.
     *
     * @return array
     */
    public function getSnapshot(): array
    {
        $data = [
            'title' => $this->title
        ];

        return array_merge($this->toArray(), $data);
    }

    /**
     * @return string|null
     */
    public function getName()
    {
        return $this->title;
    }

    /**
     * @inheritdoc
     */
    public function getUriFormat()
    {
        $productTypeSiteSettings = $this->getType()->getSiteSettings();

        if (!isset($productTypeSiteSettings[$this->siteId])) {
            throw new InvalidConfigException('The „' . $this->getType()->name . '” product group is not enabled for the „' . $this->getSite()->name . '” site.');
        }

        return $productTypeSiteSettings[$this->siteId]->uriFormat;
    }

    /**
     * Returns the tax category.
     *
     * @return TaxCategory|null
     */
    public function getTaxCategory()
    {
        if ($this->taxCategoryId) {
            return Plugin::getInstance()->getTaxCategories()->getTaxCategoryById($this->taxCategoryId);
        }

        return null;
    }

    /**
     * Returns the shipping category.
     *
     * @return ShippingCategory|null
     */
    public function getShippingCategory()
    {
        if ($this->shippingCategoryId) {
            return Plugin::getInstance()->getShippingCategories()->getShippingCategoryById($this->shippingCategoryId);
        }

        return null;
    }

    /**
     * @inheritdoc
     */
    public function getCpEditUrl()
    {
        $productType = $this->getType();

        // The slug *might* not be set if this is a Draft and they've deleted it for whatever reason
        $url = UrlHelper::cpUrl('commerce/products/' . $productType->handle . '/' . $this->id . ($this->slug ? '-' . $this->slug : ''));

        if (Craft::$app->getIsMultiSite()) {
            $url .= '/' . $this->getSite()->handle;
        }

        return $url;
    }

    /**
     * Returns the default variant.
     *
     * @return Variant
     */
    public function getDefaultVariant(): Variant
    {
        if ($this->_defaultVariant) {
            return $this->_defaultVariant;
        }

        foreach ($this->getVariants() as $variant) {
            if (null === $this->_defaultVariant || $variant->isDefault) {
                $this->_defaultVariant = $variant;
            }
        }

        return $this->_defaultVariant;
    }

    /**
     * Return the cheapest variant.
     *
     * @return Variant
     */
    public function getCheapestVariant(): Variant
    {
        if ($this->_cheapestVariant) {
            return $this->_cheapestVariant;
        }

        foreach ($this->getVariants() as $variant) {
            if (
                !$this->_cheapestVariant
                || $variant->getSalePrice() < $this->_cheapestVariant->getSalePrice()
            ) {
                $this->_cheapestVariant = $variant;
            }
        }

        return $this->_cheapestVariant;
    }

    /**
     * Returns an array of the product's variants.
     *
     * @return Variant[]
     * @throws InvalidConfigException
     */
    public function getVariants(): array
    {
        if (null === $this->_variants) {
            if ($this->id) {
                if ($this->getType()->hasVariants) {
                    $this->setVariants(Plugin::getInstance()->getVariants()->getAllVariantsByProductId($this->id, $this->siteId));
                } else {
                    $variants = Plugin::getInstance()->getVariants()->getAllVariantsByProductId($this->id, $this->siteId);
                    if ($variants) {
                        $variants[0]->isDefault = true;
                        $this->setVariants([$variants[0]]);
                    }
                }
            }

            // Must have at least one
            if (null === $this->_variants) {
                $variant = new Variant();
                $variant->isDefault = true;
                $this->setVariants([$variant]);
            }
        }

        return $this->_variants;
    }

    /**
     * Sets the variants on the product. Accepts an array of variant data keyed by variant ID or the string 'new'.
     *
     * @param Variant[]|array $variants
     */
    public function setVariants(array $variants)
    {
        $this->_variants = [];
        $count = 1;
        $this->_defaultVariant = null;

        if (empty($variants)) {
            return;
        }

        foreach ($variants as $key => $variant) {
            if (!$variant instanceof Variant) {
                $variant = ProductHelper::populateProductVariantModel($this, $variant, $key);
            }
            $variant->sortOrder = $count++;
            $variant->setProduct($this);

            if ($variant->isDefault) {
                $this->_defaultVariant = $variant;
            }

            $this->_variants[] = $variant;
        }

        if ($this->_defaultVariant === null) {
            $this->_variants[0]->isDefault = true;
        }
    }

    /**
     * @inheritdoc
     */
    public static function hasStatuses(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function getStatus()
    {
        $status = parent::getStatus();

        if ($status == self::STATUS_ENABLED && $this->postDate) {
            $currentTime = DateTimeHelper::currentTimeStamp();
            $postDate = $this->postDate->getTimestamp();
            $expiryDate = ($this->expiryDate ? $this->expiryDate->getTimestamp() : null);

            if ($postDate <= $currentTime && ($expiryDate === null || $expiryDate > $currentTime)) {
                return self::STATUS_LIVE;
            }

            if ($postDate > $currentTime) {
                return self::STATUS_PENDING;
            }

            return self::STATUS_EXPIRED;
        }

        return $status;
    }

    /**
     * @return int
     */
    public function getTotalStock(): int
    {
        $stock = 0;
        foreach ($this->getVariants() as $variant) {
            if (!$variant->hasUnlimitedStock) {
                $stock += $variant->stock;
            }
        }

        return $stock;
    }

    /**
     * Returns whether at least one variant has unlimited stock.
     *
     * @return bool
     * @deprecated as of 2.0
     */
    public function getUnlimitedStock()
    {
        Craft::$app->getDeprecator()->log('Product::getUnlimitedStock()', 'Product::getUnlimitedStock() has been deprecated. Use Product::getHasUnlimitedStock() instead');

        return $this->getHasUnlimitedStock();
    }

    /**
     * Returns whether at least one variant has unlimited stock.
     *
     * @return bool
     */
    public function getHasUnlimitedStock(): bool
    {
        foreach ($this->getVariants() as $variant) {
            if ($variant->hasUnlimitedStock) {
                return true;
            }
        }

        return false;
    }

    /**
     * @inheritdoc
     */
    public function setEagerLoadedElements(string $handle, array $elements)
    {
        if ($handle == 'variants') {
            $this->setVariants($elements);
        } else {
            parent::setEagerLoadedElements($handle, $elements);
        }
    }

    /**
     * @inheritdoc
     */
    public static function eagerLoadingMap(array $sourceElements, string $handle)
    {
        if ($handle == 'variants') {
            $sourceElementIds = ArrayHelper::getColumn($sourceElements, 'id');

            $map = (new Query())
                ->select('productId as source, id as target')
                ->from(['{{%commerce_variants}}'])
                ->where(['in', 'productId', $sourceElementIds])
                ->orderBy('sortOrder asc')
                ->all();

            return [
                'elementType' => Variant::class,
                'map' => $map
            ];
        }

        return parent::eagerLoadingMap($sourceElements, $handle);
    }

    /**
     * @inheritdoc
     */
    public static function prepElementQueryForTableAttribute(ElementQueryInterface $elementQuery, string $attribute)
    {
        /** @var ElementQuery $elementQuery */
        if ($attribute === 'variants') {
            $with = $elementQuery->with ?: [];
            $with[] = 'variants';
            $elementQuery->with = $with;
        } else {
            parent::prepElementQueryForTableAttribute($elementQuery, $attribute);
        }
    }

    /**
     * @inheritdoc
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_LIVE => Craft::t('commerce', 'Live'),
            self::STATUS_PENDING => Craft::t('commerce', 'Pending'),
            self::STATUS_EXPIRED => Craft::t('commerce', 'Expired'),
            self::STATUS_DISABLED => Craft::t('commerce', 'Disabled')
        ];
    }

    /**
     * @inheritdoc
     */
    public function getEditorHtml(): string
    {
        $viewService = Craft::$app->getView();
        $html = $viewService->renderTemplateMacro('commerce/products/_fields', 'titleField', [$this]);
        $html .= $viewService->renderTemplateMacro('commerce/products/_fields', 'generalMetaFields', [$this]);
        $html .= $viewService->renderTemplateMacro('commerce/products/_fields', 'behavioralMetaFields', [$this]);
        $html .= parent::getEditorHtml();

        $productType = $this->getType();

        if ($productType->hasVariants) {
            $html .= $viewService->renderTemplateMacro('_includes/forms', 'field', [
                [
                    'label' => Craft::t('commerce', 'Variants'),
                ],
                VariantMatrix::getVariantMatrixHtml($this)
            ]);
        } else {
            /** @var Variant $variant */
            $variant = ArrayHelper::firstValue($this->getVariants());
            $namespace = $viewService->getNamespace();
            $newNamespace = 'variants[' . ($variant->id ?: 'new1') . ']';
            $viewService->setNamespace($newNamespace);
            $html .= $viewService->namespaceInputs($viewService->renderTemplateMacro('commerce/products/_fields', 'generalVariantFields', [$variant]));

            if ($productType->hasDimensions) {
                $html .= $viewService->namespaceInputs($viewService->renderTemplateMacro('commerce/products/_fields', 'dimensionVariantFields', [$variant]));
            }

            $viewService->setNamespace($namespace);
            $viewService->registerJs('Craft.Commerce.initUnlimitedStockCheckbox($(".elementeditor").find(".meta"));');
        }

        return $html;
    }

    /**
     * @inheritdoc
     */
    public function getSearchKeywords(string $attribute): string
    {
        if ($attribute === 'sku') {
            return implode(' ', ArrayHelper::getColumn($this->getVariants(), 'sku'));
        }

        return parent::getSearchKeywords($attribute);
    }

    /**
     * @inheritdoc
     */
    public function afterSave(bool $isNew)
    {
        if (!$isNew) {
            $record = ProductRecord::findOne($this->id);

            if (!$record) {
                throw new Exception('Invalid product ID: ' . $this->id);
            }
        } else {
            $record = new ProductRecord();
            $record->id = $this->id;
        }

        $record->postDate = $this->postDate;
        $record->expiryDate = $this->expiryDate;
        $record->typeId = $this->typeId;
        $record->promotable = $this->promotable;
        $record->availableForPurchase = $this->availableForPurchase;
        $record->freeShipping = $this->freeShipping;
        $record->taxCategoryId = $this->taxCategoryId;
        $record->shippingCategoryId = $this->shippingCategoryId;

        $record->defaultSku = $this->getDefaultVariant()->sku;
        $record->defaultPrice = (float)$this->getDefaultVariant()->price;
        $record->defaultHeight = (float)$this->getDefaultVariant()->height;
        $record->defaultLength = (float)$this->getDefaultVariant()->length;
        $record->defaultWidth = (float)$this->getDefaultVariant()->width;
        $record->defaultWeight = (float)$this->getDefaultVariant()->weight;

        $record->save(false);

        $this->id = $record->id;

        // Only save variants once (since they will propagate themselves the first time.
        if (!$this->propagating) {

            $keepVariantIds = [];
            $oldVariantIds = (new Query())
                ->select('id')
                ->from('{{%commerce_variants}}')
                ->where(['productId' => $this->id])
                ->column();

            /** @var Variant $variant */
            foreach ($this->getVariants() as $variant) {

                if ($isNew) {
                    $variant->productId = $this->id;
                    $variant->siteId = $this->siteId;
                }

                $keepVariantIds[] = $variant->id;

                Craft::$app->getElements()->saveElement($variant, false);

                // We already have set the default to the correct variant in beforeSave()
                if ($variant->isDefault) {
                    $this->defaultVariantId = $variant->id;
                    Craft::$app->getDb()->createCommand()->update('{{%commerce_products}}', ['defaultVariantId' => $variant->id], ['id' => $this->id])->execute();
                }
            }

            foreach (array_diff($oldVariantIds, $keepVariantIds) as $deleteId) {
                Craft::$app->getElements()->deleteElementById($deleteId);
            }
        }

        return parent::afterSave($isNew);
    }

    /**
     * @inheritdoc
     */
    public function beforeRestore(): bool
    {
        $variants = Variant::find()->trashed(null)->productId($this->id)->status(null)->all();
        Craft::$app->getElements()->restoreElements($variants);
        $this->setVariants($variants);

        return parent::beforeRestore();
    }

    /**
     * @inheritdoc
     */
    public function beforeValidate()
    {
        // We need to generate all variant sku formats before validating the product,
        // since the product validates the uniqueness of all variants in memory.
        $type = $this->getType();
        foreach ($this->getVariants() as $variant) {
            if (!$variant->sku && $type->skuFormat) {
                try {
                    $variant->sku = Craft::$app->getView()->renderObjectTemplate($type->skuFormat, $variant);
                } catch (\Exception $e) {
                    Craft::error('Craft Commerce could not generate the supplied SKU format: ' . $e->getMessage(), __METHOD__);
                    $variant->sku = '';
                }
            }
        }

        return parent::beforeValidate();
    }

    /**
     * @inheritdoc
     */
    public function afterDelete()
    {
        $variants = Variant::find()
            ->productId($this->id)
            ->all();

        $elementsService = Craft::$app->getElements();

        foreach ($variants as $variant) {
            $variant->deletedWithProduct = true;
            $elementsService->deleteElement($variant);
        }

        parent::afterDelete();
    }

    /**
     * @inheritdoc
     */
    public function afterRestore()
    {
        // Also restore any variants for this element
        $elementsService = Craft::$app->getElements();
        foreach (ElementHelper::supportedSitesForElement($this) as $siteInfo) {
            $variants = Variant::find()
                ->anyStatus()
                ->siteId($siteInfo['siteId'])
                ->productId($this->id)
                ->trashed()
                ->andWhere(['commerce_variants.deletedWithProduct' => true])
                ->all();

            foreach ($variants as $variant) {
                $elementsService->restoreElement($variant);
            }
        }

        $this->setVariants($variants);

        parent::afterRestore();
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        $rules = parent::rules();

        $rules[] = [['typeId', 'shippingCategoryId', 'taxCategoryId'], 'number', 'integerOnly' => true];
        $rules[] = [['postDate', 'expiryDate'], DateTimeValidator::class];

        $rules[] = [
            ['variants'], function($model) {
                /** @var Product $model */
                $skus = [];
                foreach ($this->getVariants() as $variant) {
                    $skus[] = $variant->getSku();
                }

                if (count(array_unique($skus)) < count($skus)) {
                    $this->addError('variants', Craft::t('commerce', 'Not all SKUs are unique.'));
                }
            }
        ];

        return $rules;
    }

    /**
     * @inheritdoc
     */
    public function afterValidate()
    {
        if (!Model::validateMultiple($this->getVariants())) {
            $this->addError(null); // add an empty error to prevent saving
        }
        parent::afterValidate();
    }

    /**
     * @inheritdoc
     */
    public function datetimeAttributes(): array
    {
        $attributes = parent::datetimeAttributes();
        $attributes[] = 'postDate';
        $attributes[] = 'expiryDate';
        return $attributes;
    }

    /**
     * @inheritdoc
     */
    public function getFieldLayout()
    {
        return $this->getType()->getFieldLayout();
    }

    /**
     * @inheritdoc
     */
    public function beforeSave(bool $isNew): bool
    {
        $taxCategoryIds = array_keys($this->getType()->getTaxCategories());
        if (!in_array($this->taxCategoryId, $taxCategoryIds, false)) {
            $this->taxCategoryId = $taxCategoryIds[0];
        }

        $shippingCategoryIds = array_keys($this->getType()->getShippingCategories());
        if (!in_array($this->shippingCategoryId, $shippingCategoryIds, false)) {
            $this->shippingCategoryId = $shippingCategoryIds[0];
        }

        $defaultVariant = null;
        foreach ($this->getVariants() as $variant) {
            // Make the first variant (or the last one that isDefault) the default.
            if ($defaultVariant === null || $variant->isDefault) {
                $this->_defaultVariant = $defaultVariant = $variant;
            }
        }

        // Make sure the field layout is set correctly
        $this->fieldLayoutId = $this->getType()->fieldLayoutId;

        if ($this->enabled && !$this->postDate) {
            // Default the post date to the current date/time
            $this->postDate = new \DateTime();
            // ...without the seconds
            $this->postDate->setTimestamp($this->postDate->getTimestamp() - ($this->postDate->getTimestamp() % 60));
        }

        return parent::beforeSave($isNew);
    }

    // Protected Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    protected static function defineSources(string $context = null): array
    {
        if ($context == 'index') {
            $productTypes = Plugin::getInstance()->getProductTypes()->getEditableProductTypes();
            $editable = true;
        } else {
            $productTypes = Plugin::getInstance()->getProductTypes()->getAllProductTypes();
            $editable = false;
        }

        $productTypeIds = [];

        foreach ($productTypes as $productType) {
            $productTypeIds[] = $productType->id;
        }

        $sources = [
            [
                'key' => '*',
                'label' => Craft::t('commerce', 'All products'),
                'criteria' => [
                    'typeId' => $productTypeIds,
                    'editable' => $editable
                ],
                'defaultSort' => ['postDate', 'desc']
            ]
        ];

        $sources[] = ['heading' => Craft::t('commerce', 'Product Types')];

        foreach ($productTypes as $productType) {
            $key = 'productType:' . $productType->uid;
            $canEditProducts = Craft::$app->getUser()->checkPermission('commerce-manageProductType:' . $productType->uid);

            $sources[$key] = [
                'key' => $key,
                'label' => $productType->name,
                'data' => [
                    'handle' => $productType->handle,
                    'editable' => $canEditProducts
                ],
                'criteria' => ['typeId' => $productType->id, 'editable' => $editable]
            ];
        }

        return $sources;
    }

    /**
     * @inheritdoc
     */
    protected static function defineActions(string $source = null): array
    {
        // Get the section(s) we need to check permissions on
        switch ($source) {
            case '*':
                {
                    $productTypes = Plugin::getInstance()->getProductTypes()->getEditableProductTypes();
                    break;
                }
            default:
                {
                    if (preg_match('/^productType:(\d+)$/', $source, $matches)) {
                        $productType = Plugin::getInstance()->getProductTypes()->getProductTypeById($matches[1]);

                        if ($productType) {
                            $productTypes = [$productType];
                        }
                    } else if (preg_match('/^productType:(.+)$/', $source, $matches)) {
                        $productType = Plugin::getInstance()->getProductTypes()->getProductTypeByUid($matches[1]);

                        if ($productType) {
                            $productTypes = [$productType];
                        }
                    }
                }
        }

        $actions = [];

        // Copy Reference Tag
        $actions[] = Craft::$app->getElements()->createAction([
            'type' => CopyReferenceTag::class
        ]);

        // Restore
        $actions[] = Craft::$app->getElements()->createAction([
            'type' => Restore::class,
            'successMessage' => Craft::t('commerce', 'Products restored.'),
            'partialSuccessMessage' => Craft::t('commerce', 'Some products restored.'),
            'failMessage' => Craft::t('commerce', 'Products not restored.'),
        ]);

        if (!empty($productTypes)) {
            $userSession = Craft::$app->getUser();
            $canManage = false;

            foreach ($productTypes as $productType) {
                $canManage = $userSession->checkPermission('commerce-manageProductType:' . $productType->uid);
            }

            if ($canManage) {
                // Allow deletion
                $deleteAction = Craft::$app->getElements()->createAction([
                    'type' => DeleteProduct::class,
                    'confirmationMessage' => Craft::t('commerce', 'Are you sure you want to delete the selected product and its variants?'),
                    'successMessage' => Craft::t('commerce', 'Products and Variants deleted.'),
                ]);
                $actions[] = $deleteAction;
                $actions[] = SetStatus::class;
            }

            if ($userSession->checkPermission('commerce-managePromotions')) {
                $actions[] = CreateSale::class;
                $actions[] = CreateDiscount::class;
            }
        }

        return $actions;
    }

    /**
     * @inheritdoc
     */
    protected static function defineTableAttributes(): array
    {
        return [
            'title' => ['label' => Craft::t('commerce', 'Title')],
            'type' => ['label' => Craft::t('commerce', 'Type')],
            'slug' => ['label' => Craft::t('commerce', 'Slug')],
            'uri' => ['label' => Craft::t('commerce', 'URI')],
            'postDate' => ['label' => Craft::t('commerce', 'Post Date')],
            'expiryDate' => ['label' => Craft::t('commerce', 'Expiry Date')],
            'taxCategory' => ['label' => Craft::t('commerce', 'Tax Category')],
            'shippingCategory' => ['label' => Craft::t('commerce', 'Shipping Category')],
            'freeShipping' => ['label' => Craft::t('commerce', 'Free Shipping?')],
            'promotable' => ['label' => Craft::t('commerce', 'Promotable?')],
            'availableForPurchase' => ['label' => Craft::t('commerce', 'Available for purchase?')],
            'stock' => ['label' => Craft::t('commerce', 'Stock')],
            'link' => ['label' => Craft::t('commerce', 'Link'), 'icon' => 'world'],
            'dateCreated' => ['label' => Craft::t('commerce', 'Date Created')],
            'dateUpdated' => ['label' => Craft::t('commerce', 'Date Updated')],
            'defaultPrice' => ['label' => Craft::t('commerce', 'Price')],
            'defaultSku' => ['label' => Craft::t('commerce', 'SKU')],
            'defaultWeight' => ['label' => Craft::t('commerce', 'Weight')],
            'defaultLength' => ['label' => Craft::t('commerce', 'Length')],
            'defaultWidth' => ['label' => Craft::t('commerce', 'Width')],
            'defaultHeight' => ['label' => Craft::t('commerce', 'Height')],
        ];
    }

    /**
     * @inheritdoc
     */
    protected static function defineDefaultTableAttributes(string $source): array
    {
        $attributes = [];

        if ($source == '*') {
            $attributes[] = 'type';
        }

        $attributes[] = 'postDate';
        $attributes[] = 'expiryDate';
        $attributes[] = 'defaultPrice';
        $attributes[] = 'defaultSku';
        $attributes[] = 'link';

        return $attributes;
    }

    /**
     * @inheritdoc
     */
    protected static function defineSearchableAttributes(): array
    {
        return ['title', 'defaultSku', 'sku'];
    }

    /**
     * @inheritdoc
     */
    protected static function defineSortOptions(): array
    {
        return [
            'title' => Craft::t('commerce', 'Title'),
            'postDate' => Craft::t('commerce', 'Post Date'),
            'expiryDate' => Craft::t('commerce', 'Expiry Date'),
            'defaultPrice' => Craft::t('commerce', 'Price')
        ];
    }

    /**
     * @inheritdoc
     */
    protected function route()
    {
        // Make sure the product type is set to have URLs for this site
        $siteId = Craft::$app->getSites()->currentSite->id;
        $productTypeSiteSettings = $this->getType()->getSiteSettings();

        if (!isset($productTypeSiteSettings[$siteId]) || !$productTypeSiteSettings[$siteId]->hasUrls) {
            return null;
        }

        return [
            'templates/render', [
                'template' => $productTypeSiteSettings[$siteId]->template,
                'variables' => [
                    'product' => $this,
                ]
            ]
        ];
    }

    /**
     * @inheritdoc
     */
    protected function tableAttributeHtml(string $attribute): string
    {
        /* @var $productType ProductType */
        $productType = $this->getType();

        switch ($attribute) {
            case 'type':
                {
                    return ($productType ? Craft::t('site', $productType->name) : '');
                }

            case 'taxCategory':
                {
                    $taxCategory = $this->getTaxCategory();

                    return ($taxCategory ? Craft::t('site', $taxCategory->name) : '');
                }
            case 'shippingCategory':
                {
                    $shippingCategory = $this->getShippingCategory();

                    return ($shippingCategory ? Craft::t('site', $shippingCategory->name) : '');
                }
            case 'defaultPrice':
                {
                    $code = Plugin::getInstance()->getPaymentCurrencies()->getPrimaryPaymentCurrencyIso();

                    return Craft::$app->getLocale()->getFormatter()->asCurrency($this->$attribute, strtoupper($code));
                }
            case 'stock':
                {
                    $stock = 0;
                    $hasUnlimited = false;
                    /** @var Variant $variant */
                    foreach ($this->getVariants() as $variant) {
                        $stock += $variant->stock;
                        if ($variant->hasUnlimitedStock) {
                            $hasUnlimited = true;
                        }
                    }
                    return $hasUnlimited ? '∞' . ($stock ? ' & ' . $stock : '') : ($stock ?: '');
                }
            case 'defaultWeight':
                {
                    if ($productType->hasDimensions) {
                        return Craft::$app->getLocale()->getFormatter()->asDecimal($this->$attribute) . ' ' . Plugin::getInstance()->getSettings()->weightUnits;
                    }

                    return '';
                }
            case 'defaultLength':
            case 'defaultWidth':
            case 'defaultHeight':
                {
                    if ($productType->hasDimensions) {
                        return Craft::$app->getLocale()->getFormatter()->asDecimal($this->$attribute) . ' ' . Plugin::getInstance()->getSettings()->dimensionUnits;
                    }

                    return '';
                }
            case 'availableForPurchase':
            case 'promotable':
            case 'freeShipping':
                {
                    return ($this->$attribute ? '<span data-icon="check" title="' . Craft::t('commerce', 'Yes') . '"></span>' : '');
                }

            default:
                {
                    return parent::tableAttributeHtml($attribute);
                }
        }
    }
}
