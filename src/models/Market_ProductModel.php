<?php

namespace Craft;

use Market\Traits\Market_ModelRelationsTrait;

/**
 * Class Market_ProductModel
 *
 * @property int                     id
 * @property DateTime                availableOn
 * @property DateTime                expiresOn
 * @property int                     typeId
 * @property int                     authorId
 * @property int                     taxCategoryId
 * @property bool                    promotable
 * @property bool                    freeShipping
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

    /**
     * Allow the variant to ask the product what data to snapshot
     * @return string
     */
    public function getSnapshot()
    {
        $data = [
            'title' => $this->getTitle(),
            'name' => $this->getTitle()
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
     * Either only implicit variant if there is only one or all without implicit
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
                return !$v->isImplicit;
            });
        }else{
            $variants = array_filter($variants, function ($v) {
                return $v->isImplicit;
            });
        }

        return $variants;
    }

    /**
     * @return bool|mixed
     * @throws Exception
     */
    public function getImplicitVariant()
    {

        if($this->id){
            $variants = craft()->market_variant->getAllByProductId($this->id);
            craft()->market_variant->applySales($variants, $this);

            $implicitVariant = array_filter($variants, function ($v)
            {
                return $v->isImplicit;
            });

            if (count($implicitVariant) == 1)
            {
                return array_shift(array_values($implicitVariant));
            }
            else
            {
                throw new Exception('More than one implicit variant found. Contact Support.');

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
            'promotable'    => AttributeType::Bool,
            'freeShipping'  => AttributeType::Bool,
            'availableOn'   => AttributeType::DateTime,
            'expiresOn'     => AttributeType::DateTime
        ]);
    }
}