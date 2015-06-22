<?php

namespace Craft;

use Market\Traits\Market_ModelRelationsTrait;

/**
 * Class Market_ProductModel
 *
 * @property int                     $id
 * @property DateTime                $availableOn
 * @property DateTime                $expiresOn
 * @property int                     typeId
 * @property int                     authorId
 * @property int                     taxCategoryId
 * @property bool                    enabled
 *
 * Inherited from record:
 * @property Market_ProductTypeModel type
 * @property Market_TaxCategoryModel taxCategory
 * @property Market_VariantModel[]   allVariants
 * @property Market_VariantModel     $master
 *
 * Magic properties:
 * @property Market_VariantModel[]   $variants
 * @property Market_VariantModel[]   $nonMasterVariants
 * @property string                  name
 * @package Craft
 */
class Market_ProductModel extends BaseElementModel
{
    use Market_ModelRelationsTrait;

    const LIVE = 'live';
    const PENDING = 'pending';
    const EXPIRED = 'expired';

    protected $elementType = 'Market_Product';

    // Public Methods
    // =============================================================================

    /**
     * Setting default taxCategoryId
     *
     * @param null $attributes
     */
    public function __construct($attributes = null)
    {
        parent::__construct($attributes);

        if (empty($this->taxCategoryId)) {
            $this->taxCategoryId = craft()->market_taxCategory->getDefaultId();
        }
    }

    /**
     * @return bool
     */
    public function isEditable()
    {
        return true;
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
    public function getName()
    {
        return $this->title;
    }


    /**
     * Returns the placeholder template for the sku
     *
     */
    public function getSkuPlaceholder()
    {
        //TODO implement SKU template
        return "";
    }

    /**
     * Returns the Master Variants's
     *
     * @return float
     */
    public function getPrice()
    {
        if ($this->getMasterVariant()) {
            return $this->getMasterVariant()->price;
        }
    }

    /**
     * Gets only the variant that is master.
     *
     * @return Market_VariantModel|null
     */
    public function getMasterVariant()
    {

        $masterVariant = array_filter($this->allVariants, function ($v) {
            return $v->isMaster;
        });

        return isset($masterVariant[0]) ? $masterVariant[0] : null;
    }

    /**
     * Returns the Master Variants's
     *
     * @return float
     */
    public function getWidth()
    {
        if ($this->getMasterVariant()) {
            return $this->getMasterVariant()->width;
        }
    }

    /**
     * Returns the Master Variants's Height
     *
     * @return float
     */
    public function getHeight()
    {
        if ($this->getMasterVariant()) {
            return $this->getMasterVariant()->height;
        }
    }

    /**
     * Returns the Master Variants's Length
     *
     * @return float
     */
    public function getLength()
    {
        if ($this->getMasterVariant()) {
            return $this->getMasterVariant()->length;
        }
    }

    /**
     * Returns the Master Variants's Weight
     *
     * @return float
     */
    public function getWeight()
    {
        if ($this->getMasterVariant()) {
            return $this->getMasterVariant()->weight;
        }
    }

    /*
     * Url to edit this Product in the control panel.
     */

    /**
     * What is the Url Format for this ProductType
     *
     * @return string
     */
    public function getUrlFormat()
    {
        if ($this->typeId) {
            return craft()->market_productType->getById($this->typeId)->urlFormat;
        }

        return null;
    }

    public function getCpEditUrl()
    {
        if ($this->typeId) {
            $productTypeHandle = craft()->market_productType->getById($this->typeId)->handle;

            return UrlHelper::getCpUrl('market/products/' . $productTypeHandle . '/' . $this->id);
        }

        return null;

    }

    /**
     * @return FieldLayoutModel|null
     */
    public function getFieldLayout()
    {
        if ($this->typeId) {
            return craft()->market_productType->getById($this->typeId)->asa('productFieldLayout')->getFieldLayout();
        }

        return null;
    }

    /**
     * @return null|string
     */
    public function getStatus()
    {
        $status = parent::getStatus();

        if ($status == static::ENABLED && $this->availableOn) {
            $currentTime = DateTimeHelper::currentTimeStamp();
            $availableOn = $this->availableOn->getTimestamp();
            $expiresOn   = ($this->expiresOn ? $this->expiresOn->getTimestamp() : null);

            if ($availableOn <= $currentTime && (!$expiresOn || $expiresOn > $currentTime)) {
                return static::LIVE;
            } else {
                if ($availableOn > $currentTime) {
                    return static::PENDING;
                } else {
                    return static::EXPIRED;
                }
            }
        }

        return $status;
    }

    public function isLocalized()
    {
        return false;
    }

    /**
     * Either only master variant if there is only one or all without master
     * Applies sales to the product before returning
     *
     * @return Market_VariantModel[]
     */
    public function getVariants()
    {
        if (count($this->allVariants) == 1) {
            $variants = $this->allVariants;
        } else {
            $variants = $this->nonMasterVariants;
        }
        
        craft()->market_variant->applySales($variants, $this);

        return $variants;
    }

    /**
     * @return Market_VariantModel[]
     */
    public function getNonMasterVariants()
    {
        return array_filter($this->allVariants, function ($v) {
            return !$v->isMaster;
        });
    }

    // Protected Methods
    // =============================================================================

    /**
     * @return array
     */
    protected function defineAttributes()
    {
        return array_merge(parent::defineAttributes(), [
            'typeId'        => AttributeType::Number,
            'authorId'      => AttributeType::Number,
            'taxCategoryId' => AttributeType::Number,
            'availableOn'   => AttributeType::DateTime,
            'expiresOn'     => AttributeType::DateTime
        ]);
    }
}