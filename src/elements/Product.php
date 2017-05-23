<?php
namespace craft\commerce\elements;

use Craft;
use craft\commerce\base\Element;
use craft\commerce\elements\db\ProductQuery;
use craft\commerce\helpers\VariantMatrix;
use craft\commerce\models\ProductType;
use craft\commerce\models\TaxCategory;
use craft\commerce\Plugin;
use craft\helpers\UrlHelper;
use yii\base\InvalidConfigException;

/**
 * Product model.
 *
 * @property int         $id
 * @property \DateTime   $postDate
 * @property \DateTime   $expiryDate
 * @property int         $typeId
 * @property int         $taxCategoryId
 * @property int         $shippingCategoryId
 * @property bool        $promotable
 * @property bool        $freeShipping
 * @property bool        $enabled
 *
 * @property int         defaultVariantId
 * @property string      defaultSku
 * @property float       defaultPrice
 * @property float       defaultHeight
 * @property float       defaultLength
 * @property float       defaultWidth
 * @property float       defaultWeight
 *
 * @property ProductType $type
 * @property TaxCategory $taxCategory
 * @property Variant[]   $variants
 *
 * @property string      $name
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.models
 * @since     1.0
 */
class Product extends Element
{

    const LIVE = 'live';
    const PENDING = 'pending';
    const EXPIRED = 'expired';

    /**
     * @var Variant[] This product’s variants
     */
    private $_variants;

    // Public Methods
    // =============================================================================


    /**
     * @inheritdoc
     *
     * @return ProductQuery The newly created [[ProductQuery]] instance.
     */
    public static function find(): ProductQuery
    {
        return new ProductQuery(static::class);
    }

    /**
     * @return bool
     */
    public function isEditable()
    {
        if ($this->getType()) {
            $id = $this->getType()->id;

            return Craft::$app->getUser()->checkPermission('commerce-manageProductType:'.$id);
        }

        return false;
    }


    /**
     * Gets the products product type.
     *
     * @return ProductType
     * @throws InvalidConfigException
     */
    public function getType(): ProductType
    {

        if ($this->typeId === null) {
            throw new InvalidConfigException('Product is missing its product type ID');
        }

        if (($productType = Plugin::getInstance()->getProductTypes()->getProductTypeById($this->typeId)) === null) {
            throw new InvalidConfigException('Invalid product type ID: '.$this->typeId);
        }

        return $productType;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->title;
    }

    /*
     * Name is an alias to title.
     *
     * @return string
     */

    /**
     * Allow the variant to ask the product what data to snapshot
     *
     * @return string
     */
    public function getSnapshot()
    {
        $data = [
            'title' => $this->getTitle()
        ];

        return array_merge($this->getAttributes(), $data);
    }

    /*
     * Returns the URL format used to generate this element's URL.
     *
     * @return string
     */

    public function getName()
    {
        return $this->title;
    }

    public function getUrlFormat()
    {
        $productType = $this->getType();

        if ($productType && $productType->hasUrls) {
            $productTypeLocales = $productType->getLocales();

            if (isset($productTypeLocales[$this->locale])) {
                return $productTypeLocales[$this->locale]->urlFormat;
            }
        }
    }

    /**
     * Gets the tax category
     *
     * @return TaxCategory|null
     */
    public function getTaxCategory()
    {
        if ($this->taxCategoryId) {
            return Plugin::getInstance()->getTaxCategories()->getTaxCategoryById($this->taxCategoryId);
        }
    }

    /**
     * Gets the shipping category
     *
     * @return ShippingCategory|null
     */
    public function getShippingCategory()
    {
        if ($this->shippingCategoryId) {
            return Plugin::getInstance()->getShippingCategories()->getShippingCategoryById($this->shippingCategoryId);
        }
    }

    /**
     * @return null|string
     */
    public function getCpEditUrl()
    {
        $productType = $this->getType();

        // The slug *might* not be set if this is a Draft and they've deleted it for whatever reason
        $url = UrlHelper::cpUrl('commerce/products/'.$productType->handle.'/'.$this->id.($this->slug ? '-'.$this->slug : ''));

        if (Craft::$app->getIsMultiSite() && $this->siteId != Craft::$app->getSites()->currentSite->id) {
            $url .= '/'.$this->getSite()->handle;
        }

        return $url;
    }

    /**
     * @return FieldLayoutModel|null
     */
    public function getFieldLayout()
    {
        $productType = $this->getType();

        if ($productType) {
            return $productType->asa('productFieldLayout')->getFieldLayout();
        }

        return null;
    }

    /**
     * Gets the default variant.
     *
     * @return Variant
     */
    public function getDefaultVariant()
    {
        $defaultVariant = null;

        foreach ($this->getVariants() as $variant) {
            if ($defaultVariant === null || $variant->isDefault) {
                $defaultVariant = $variant;
            }
        };

        return $defaultVariant;
    }

    /**
     * Returns an array of the product's variants with sales applied.
     *
     * @return Variant[]
     */
    public function getVariants()
    {
        if (empty($this->_variants)) {
            if ($this->id) {
                if ($this->getType()->hasVariants) {
                    $this->setVariants(Plugin::getInstance()->getVariants()->getAllVariantsByProductId($this->id, $this->locale));
                } else {
                    $variant = Plugin::getInstance()->getVariants()->getDefaultVariantByProductId($this->id, $this->locale);
                    if ($variant) {
                        $this->setVariants([$variant]);
                    }
                }
            }

            // Must have at least one
            if (empty($this->_variants)) {
                $variant = new Variant();
                $this->setVariants([$variant]);
            }
        }

        return $this->_variants;
    }

    /**
     * @param $variants
     */
    public function setVariants($variants)
    {
        Plugin::getInstance()->getVariants()->setProductOnVariants($this, $variants);
        $this->_variants = $variants;
    }

    /**
     * @return null|string
     */
    public function getStatus()
    {
        $status = parent::getStatus();

        if ($status == static::ENABLED && $this->postDate) {
            $currentTime = DateTimeHelper::currentTimeStamp();
            $postDate = $this->postDate->getTimestamp();
            $expiryDate = ($this->expiryDate ? $this->expiryDate->getTimestamp() : null);

            if ($postDate <= $currentTime && (!$expiryDate || $expiryDate > $currentTime)) {
                return static::LIVE;
            } else {
                if ($postDate > $currentTime) {
                    return static::PENDING;
                } else {
                    return static::EXPIRED;
                }
            }
        }

        return $status;
    }

    /**
     * Gets the total amount of stock across all variants.
     */
    public function getTotalStock()
    {
        $stock = 0;
        foreach ($this->getVariants() as $variant) {
            if (!$variant->unlimitedStock) {
                $stock += $variant->stock;
            }
        }

        return $stock;
    }

    /**
     * Does at least one variant have unlimited stock?
     */
    public function getUnlimitedStock()
    {
        foreach ($this->getVariants() as $variant) {
            if ($variant->unlimitedStock) {
                return true;
            }
        }

        return false;
    }

    /**
     * Sets some eager loaded elements on a given handle.
     *
     * @param string             $handle   The handle to load the elements with in the future
     * @param BaseElementModel[] $elements The eager-loaded elements
     */
    public function setEagerLoadedElements($handle, $elements)
    {
        if ($handle == 'variants') {
            $this->setVariants($elements);
        } else {
            parent::setEagerLoadedElements($handle, $elements);
        }
    }

    // Protected Methods
    // =============================================================================


    // Original Product Element methods:

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
    public static function hasStatuses(): bool
    {
        return true;
    }

    /**
     * @return bool
     */
    public static function isLocalized(): bool
    {
        return true;
    }

    /**
     * @param null $source
     *
     * @return array
     */
    public function getAvailableActions($source = null)
    {
        // Get the section(s) we need to check permissions on
        switch ($source) {
            case '*': {
                $productTypes = Plugin::getInstance()->getProductTypes()->getEditableProductTypes();
                break;
            }
            default: {
                if (preg_match('/^productType:(\d+)$/', $source, $matches)) {
                    $productType = Plugin::getInstance()->getProductTypes()->getProductTypeById($matches[1]);

                    if ($productType) {
                        $productTypes = [$productType];
                    }
                }
            }
        }

        $actions = [];

        if (!empty($productTypes)) {
            $userSessionService = Craft::$app->getUser();
            $canManage = false;

            foreach ($productTypes as $productType) {
                $canManage = $userSessionService->checkPermission('commerce-manageProductType:'.$productType->id);
            }

            if ($canManage) {
                // Allow deletion
                $deleteAction = Craft::$app->getElements()->getAction('DeleteProduct');
                $deleteAction->setParams([
                    'confirmationMessage' => Craft::t('commerce', 'Are you sure you want to delete the selected product and its variants?'),
                    'successMessage' => Craft::t('commerce', 'Products and Variants deleted.'),
                ]);
                $actions[] = $deleteAction;

                // Allow setting status
                $setStatusAction = Craft::$app->getElements()->getAction('SetStatus');
                $setStatusAction->onSetStatus = function(Event $event) {
                    if ($event->params['status'] == BaseElementModel::ENABLED) {
                        // Set a Post Date as well
                        Craft::$app->getDb()->createCommand()->update('entries',
                            ['postDate' => DateTimeHelper::currentTimeForDb()],
                            ['and', ['in', 'id', $event->params['elementIds']], 'postDate is null']);
                    }
                };
                $actions[] = $setStatusAction;
            }

            if ($userSessionService->checkPermission('commerce-managePromotions')) {
                $actions[] = Craft::$app->getElements()->getAction('CreateSale');
                $actions[] = Craft::$app->getElements()->getAction('CreateDiscount');
            }
        }

        // Allow plugins to add additional actions
        $allPluginActions = Craft::$app->getPlugins()->call('commerce_addProductActions', [$source], true);

        foreach ($allPluginActions as $pluginActions) {
            $actions = array_merge($actions, $pluginActions);
        }

        return $actions;
    }

    /**
     * @param null $context
     *
     * @return array
     */
    public function getSources($context = null)
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
            '*' => [
                'label' => Craft::t('commerce', 'All products'),
                'criteria' => ['typeId' => $productTypeIds, 'editable' => $editable],
                'defaultSort' => ['postDate', 'desc']
            ]
        ];

        $sources[] = ['heading' => Craft::t('commerce', 'Product Types')];

        foreach ($productTypes as $productType) {
            $key = 'productType:'.$productType->id;
            $canEditProducts = Craft::$app->getUser()->checkPermission('commerce-manageProductType:'.$productType->id);

            $sources[$key] = [
                'label' => $productType->name,
                'data' => [
                    'handle' => $productType->handle,
                    'editable' => $canEditProducts
                ],
                'criteria' => ['typeId' => $productType->id, 'editable' => $editable]
            ];
        }

        // Allow plugins to modify the sources
        Craft::$app->getPlugins()->call('commerce_modifyProductSources', [&$sources, $context]);

        return $sources;
    }

    /**
     * @return array
     */
    public function defineAvailableTableAttributes()
    {
        $attributes = [
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

        // Allow plugins to modify the attributes
        $pluginAttributes = Craft::$app->getPlugins()->call('commerce_defineAdditionalProductTableAttributes', [], true);

        foreach ($pluginAttributes as $thisPluginAttributes) {
            $attributes = array_merge($attributes, $thisPluginAttributes);
        }

        return $attributes;
    }

    /**
     * @param string|null $source
     *
     * @return array
     */
    public function getDefaultTableAttributes($source = null)
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
    public static function defineSearchableAttributes(): array
    {
        return ['title', 'defaultSku'];
    }

    /**
     * @inheritdoc
     */
    public function tableAttributeHtml(string $attribute): string
    {
        /* @var $productType ProductType */
        $productType = $this->getType();

        switch ($attribute) {
            case 'type': {
                return ($productType ? Craft::t($productType->name) : '');
            }

            case 'taxCategory': {
                $taxCategory = $this->getTaxCategory();

                return ($taxCategory ? Craft::t($taxCategory->name) : '');
            }
            case 'shippingCategory': {
                $shippingCategory = $this->getShippingCategory();

                return ($shippingCategory ? Craft::t($shippingCategory->name) : '');
            }
            case 'defaultPrice': {
                $code = Plugin::getInstance()->getPaymentCurrencies()->getPrimaryPaymentCurrencyIso();

                return craft()->numberFormatter->formatCurrency($this->$attribute, strtoupper($code));
            }
            case 'defaultWeight': {
                if ($productType->hasDimensions) {
                    return craft()->numberFormatter->formatDecimal($this->$attribute)." ".Plugin::getInstance()->getSettings()->getSettings()->weightUnits;
                } else {
                    return "";
                }
            }
            case 'defaultLength':
            case 'defaultWidth':
            case 'defaultHeight': {
                if ($productType->hasDimensions) {
                    return craft()->numberFormatter->formatDecimal($this->$attribute)." ".Plugin::getInstance()->getSettings()->getSettings()->dimensionUnits;
                } else {
                    return "";
                }
            }
            case 'promotable':
            case 'freeShipping': {
                return ($this->$attribute ? '<span data-icon="check" title="'.Craft::t('commerce', 'Yes').'"></span>' : '');
            }

            default: {
                return parent::tableAttributeHtml($attribute);
            }
        }
    }

    /**
     * Sortable by
     *
     * @return array
     */
    public function defineSortableAttributes()
    {
        $attributes = [
            'title' => Craft::t('commerce', 'Title'),
            'postDate' => Craft::t('commerce', 'Post Date'),
            'expiryDate' => Craft::t('commerce', 'Expiry Date'),
            'defaultPrice' => Craft::t('commerce', 'Price')
        ];

        // Allow plugins to modify the attributes
        Craft::$app->getPlugins()->call('commerce_modifyProductSortableAttributes', [&$attributes]);

        return $attributes;
    }

    /**
     * @inheritDoc IElementType::getStatuses()
     *
     * @return array|null
     */
    public function getStatuses()
    {
        return [
            Product::LIVE => Craft::t('commerce', 'Live'),
            Product::PENDING => Craft::t('commerce', 'Pending'),
            Product::EXPIRED => Craft::t('commerce', 'Expired'),
            Element::STATUS_DISABLED => Craft::t('commerce', 'Disabled')
        ];
    }

    /**
     * @param array $row
     *
     * @return Element
     */
    public function populateElementModel($row)
    {
        return new Product($row);
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
            $variant = reset($this->getVariants());
            $namespace = $viewService->getNamespace();
            $newNamespace = 'variants['.($variant->id ?: 'new1').']';
            $viewService->setNamespace($newNamespace);
            $html .= $viewService->namespaceInputs($viewService->renderTemplateMacro('commerce/products/_fields', 'generalVariantFields', [$variant]));

            if ($productType->hasDimensions) {
                $html .= $viewService->namespaceInputs($viewService->renderTemplateMacro('commerce/products/_fields', 'dimensionVariantFields', [$variant]));
            }

            $viewService->setNamespace($namespace);
            $viewService->includeJs('Craft.Commerce.initUnlimitedStockCheckbox($(".elementeditor").find(".meta"));');
        }

        return $html;
    }

    /**
     * @inheritdoc
     */
    public function route()
    {
        return [
            'action' => 'templates/render',
            'params' => [
                'template' => $this->getType()->template,
                'variables' => [
                    'product' => $this
                ]
            ]
        ];
    }

    /**
     * @param BaseElementModel $element
     * @param array            $params
     *
     * @return bool
     * @throws Exception
     * @throws \Exception
     */
    public function saveElement(BaseElementModel $element, $params)
    {
        CommerceProductHelper::populateProductModel($element, $params);
        CommerceProductHelper::populateProductVariantModels($element, $params['variants']);

        return Plugin::getInstance()->getProducts()->saveProduct($element);
    }

    /**
     * @return array
     */
    protected function defineAttributes()
    {
        return array_merge(parent::defineAttributes(), [
            'typeId' => AttributeType::Number,
            'taxCategoryId' => AttributeType::Number,
            'shippingCategoryId' => AttributeType::Number,
            'promotable' => [AttributeType::Bool, 'default' => true],
            'freeShipping' => AttributeType::Bool,
            'postDate' => AttributeType::DateTime,
            'expiryDate' => AttributeType::DateTime,

            'defaultVariantId' => [AttributeType::Number],
            'defaultSku' => [AttributeType::String, 'label' => 'SKU'],
            'defaultPrice' => [AttributeType::Number, 'decimals' => 4],
            'defaultHeight' => [AttributeType::Number, 'decimals' => 4],
            'defaultLength' => [AttributeType::Number, 'decimals' => 4],
            'defaultWidth' => [AttributeType::Number, 'decimals' => 4],
            'defaultWeight' => [AttributeType::Number, 'decimals' => 4]
        ]);
    }

}
