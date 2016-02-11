<?php
namespace Craft;

use Commerce\Traits\Commerce_ModelRelationsTrait;

/**
 * Product model.
 *
 * @property int $id
 * @property DateTime $postDate
 * @property DateTime $expiryDate
 * @property int $typeId
 * @property int $authorId
 * @property int $taxCategoryId
 * @property bool $promotable
 * @property bool $freeShipping
 * @property bool $enabled
 *
 * @property int defaultVariantId
 * @property string defaultSku
 * @property float defaultPrice
 * @property float defaultHeight
 * @property float defaultLength
 * @property float defaultWidth
 * @property float defaultWeight
 *
 * @property Commerce_ProductTypeModel $type
 * @property Commerce_TaxCategoryModel $taxCategory
 * @property Commerce_VariantModel[] $variants
 *
 * @property string $name
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.models
 * @since     1.0
 */
class Commerce_ProductModel extends BaseElementModel
{
    use Commerce_ModelRelationsTrait;

    const LIVE = 'live';
    const PENDING = 'pending';
    const EXPIRED = 'expired';

    /**
     * @var string
     */
    protected $elementType = 'Commerce_Product';

    /**
     * @var Commerce_VariantModel[] This product’s variants
     */
    private $_variants;

    // Public Methods
    // =============================================================================

    /**
     * @return bool
     */
    public function isEditable()
    {
        if($this->getType()){
            $id = $this->getType()->id;
            return craft()->userSession->checkPermission('commerce-manageProductType:'.$id);
        }

        return false;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->title;
    }

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
     * Name is an alias to title.
     *
     * @return string
     */
    public function getName()
    {
        return $this->title;
    }

    /*
     * Url to edit this Product in the control panel.
     *
     * @return string
     */
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
     * Gets the products type
     *
     * @return Commerce_ProductTypeModel
     */
    public function getType()
    {
        if ($this->typeId) {
            return craft()->commerce_productTypes->getProductTypeById($this->typeId);
        }
    }

    /**
     * Gets the tax category
     *
     * @return Commerce_TaxCategoryModel|null
     */
    public function getTaxCategory()
    {
        if ($this->taxCategoryId) {
            return craft()->commerce_taxCategories->getTaxCategoryById($this->taxCategoryId);
        }
    }

    /**
     * @return null|string
     */
    public function getCpEditUrl()
    {
        $productType = $this->getType();
        $url = "";

        if ($productType) {
            // The slug *might* not be set if this is a Draft and they've deleted it for whatever reason
            $url = UrlHelper::getCpUrl('commerce/products/' . $productType->handle . '/' . $this->id . ($this->slug ? '-' . $this->slug : ''));

            if (craft()->isLocalized() && $this->locale != craft()->language) {
                $url .= '/' . $this->locale;
            }
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
     * @return Commerce_VariantModel
     */
    public function getDefaultVariant()
    {
        $defaultVariant = null;

        foreach($this->getVariants() as $variant){
            if ($defaultVariant === null || $variant->isDefault)
            {
                $defaultVariant = $variant;
            }
        };

        return $defaultVariant;
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
     * @return bool
     */
    public function isLocalized()
    {
        return true;
    }


    /**
     * @param $variants
     */
    public function setVariants($variants)
    {
        $this->_variants = $variants;

        // ensure each has it's parent product set
        foreach ($this->_variants as $variant) {
            $variant->setProduct($this);
        }

        // apply all sales applicable
        craft()->commerce_variants->applySales($this->_variants, $this);
    }

    /**
     * Returns array of variants with sales applied. Will only return an array containing a single
     * variant when the product's type is set to have no variants.
     *
     * @return Commerce_VariantModel[]
     */
    public function getVariants()
    {
        if (empty($this->_variants)) {
            if ($this->id) {
                if ($this->getType()->hasVariants) {
                    $this->setVariants(craft()->commerce_variants->getAllVariantsByProductId($this->id, $this->locale));
                } else {
                    $variant = craft()->commerce_variants->getDefaultVariantByProductId($this->id, $this->locale);
                    if ($variant) {
                        $this->setVariants([$variant]);
                    }
                }
            }

            // Must have at least one
            if (empty($this->_variants)) {
                $variant = new Commerce_VariantModel();
                $this->setVariants([$variant]);
            }
        }

        return $this->_variants;
    }

    // Protected Methods
    // =============================================================================

    /**
     * @return array
     */
    protected function defineAttributes()
    {
        return array_merge(parent::defineAttributes(), [
            'typeId' => AttributeType::Number,
            'authorId' => AttributeType::Number,
            'taxCategoryId' => AttributeType::Number,
            'promotable' => [AttributeType::Bool,'default'=>true],
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
