<?php

namespace Craft;

use Market\Traits\Market_ModelRelationsTrait;
use Market\Interfaces\Purchasable;

/**
 * Class Market_ProductModel
 *
 * @property int                     id
 * @property DateTime                availableOn
 * @property DateTime                expiresOn
 * @property int                     typeId
 * @property int                     authorId
 * @property int                     taxCategoryId
 * @property bool                    enabled
 *
 * Inherited from record:
 * @property Market_ProductTypeModel type
 * @property Market_TaxCategoryModel taxCategory
 * @property Market_VariantModel[]   variants
 *
 * Magic properties:
 * @property string                  name
 * @package Craft
 */
class Market_ProductModel extends BaseElementModel implements Purchasable
{
    use Market_ModelRelationsTrait;

    const LIVE = 'live';
    const PENDING = 'pending';
    const EXPIRED = 'expired';

    protected $elementType = 'Market_Product';

    // Public Methods
    // =============================================================================


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
     * We need to be explicit to meet interface
     * @return string
     */
    public function getDescription()
    {
        if (!$this->type->hasVariants) {
            return (string) $this->title;
        }
    }

    /**
     * Validate based mast variant validation
     *
     * @param Market_LineItemModel $lineItem
     *
     * @return mixed
     */
    public function validateLineItem(Market_LineItemModel $lineItem)
    {
        $this->getMasterVariant()->validateLineItem($lineItem);
    }

    /**
     * @return int
     */
    public function getPurchasableId()
    {
        if (!$this->type->hasVariants) {
            return $this->getMasterVariant()->getPurchasableId();
        }
    }

    /**
     * We need to be explicit to meet interface
     * @return string
     */
    public function getSnapshot()
    {
        $data = [
            'title' => $this->getTitle()
        ];

        return array_merge($this->getAttributes(),$data);
    }

    /**
     * We need to be explicit to meet interface
     * @return string
     */
    public function getModelClass()
    {
        return 'Market_ProductModel';
    }

    /**
     * Returns the Master Variants's
     *
     * @return float
     */
    public function getPrice()
    {
        if (!$this->type->hasVariants) {
            return $this->getMasterVariant()->price;
        }
    }

    /**
     * Returns the Master Variants's
     *
     * @return float
     */
    public function getWidth()
    {
        if (!$this->type->hasVariants) {
            return $this->getMasterVariant()->width;
        }
    }

    /**
     * Returns the Master Variants's
     *
     * @return float
     */
    public function getSku()
    {
        if (!$this->type->hasVariants) {
            return $this->getMasterVariant()->sku;
        }
    }


    /**
     * Returns the Master Variants's
     *
     * @return float
     */
    public function getOnSale()
    {
        if (!$this->type->hasVariants) {
            return $this->getMasterVariant()->onSale;
        }
    }


    /**
     * Returns the Master Variants's
     *
     * @return float
     */
    public function getStock()
    {
        if (!$this->type->hasVariants) {
            return $this->getMasterVariant()->stock;
        }
    }

    /**
     * Returns the Master Variants's
     *
     * @return float
     */
    public function getSalePrice()
    {
        if (!$this->type->hasVariants) {
            return $this->getMasterVariant()->salePrice;
        }
    }


    /**
     * Returns the Master Variants's
     *
     * @return float
     */
    public function getUnlimitedStock()
    {
        if (!$this->type->hasVariants) {
            return $this->getMasterVariant()->unlimitedStock;
        }
    }

    /**
     * Returns the Master Variants's Height
     *
     * @return float
     */
    public function getHeight()
    {
        if (!$this->type->hasVariants) {
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
        if (!$this->type->hasVariants) {
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
        if (!$this->type->hasVariants) {
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

    /**
     * @return null|string
     */
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
        $variants = craft()->market_variant->getAllByProductId($this->id);
        craft()->market_variant->applySales($variants, $this);

        if ($this->type->hasVariants){
            $variants = array_filter($variants, function ($v) {
                return !$v->isMaster;
            });
        }

        return $variants;
    }

    /**
     * @return bool|mixed
     * @throws Exception
     */
    public function getMasterVariant()
    {

        if($this->id){
            $variants = craft()->market_variant->getAllByProductId($this->id);
            craft()->market_variant->applySales($variants, $this);

            $masterVariant = array_filter($variants, function ($v) {
                return $v->isMaster;
            });

            if (count($masterVariant) == 1){
                return array_shift(array_values($masterVariant));
            }else{
                throw new Exception('More than one master variant found. Contact Support.');
                return false;
            }
        }

        return false;
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