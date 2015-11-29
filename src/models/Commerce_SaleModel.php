<?php
namespace Craft;

use Commerce\Traits\Commerce_ModelRelationsTrait;

/**
 * Sale model.
 *
 * @property int $id
 * @property string $name
 * @property string $description
 * @property DateTime $dateFrom
 * @property DateTime $dateTo
 * @property string $discountType
 * @property float $discountAmount
 * @property bool $allGroups
 * @property bool $allProducts
 * @property bool $allProductTypes
 * @property bool $enabled
 *
 * @property Commerce_ProductModel[] $products
 * @property Commerce_ProductTypeModel[] $productTypes
 * @property UserGroupModel[] $groups
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.models
 * @since     1.0
 */
class Commerce_SaleModel extends BaseModel
{
    use Commerce_ModelRelationsTrait;

    /**
     * @return array
     */
    public function getGroupIds()
    {
        return array_map(function ($group) {
            return $group->id;
        }, $this->groups);
    }

    /**
     * @return array
     */
    public function getProductTypeIds()
    {
        return array_map(function ($type) {
            return $type->id;
        }, $this->productTypes);
    }

    /**
     * @return array
     */
    public function getProductIds()
    {
        return array_map(function ($product) {
            return $product->id;
        }, $this->products);
    }

    /**
     * @return string
     */
    public function getDiscountAmountAsPercent()
    {
        $localeData = craft()->i18n->getLocaleData();
        $percentSign = $localeData->getNumberSymbol('percentSign');

        return -$this->discountAmount * 100 . "" . $percentSign;
    }

    /**
     * @return string
     */
    public function getDiscountAmountAsFlat()
    {
        return -$this->discountAmount;
    }

    /**
     * @param float $price
     *
     * @return float
     * @throws Exception
     */
    public function calculateTakeoff($price)
    {
        if ($this->discountType == Commerce_SaleRecord::TYPE_FLAT) {
            $takeOff = $this->discountAmount;
        } else {
            $takeOff = $this->discountAmount * $price;
        }

        return $takeOff;
    }

    protected function defineAttributes()
    {
        return [
            'id' => AttributeType::Number,
            'name' => AttributeType::Name,
            'description' => AttributeType::Mixed,
            'dateFrom' => AttributeType::DateTime,
            'dateTo' => AttributeType::DateTime,
            'discountType' => AttributeType::Enum,
            'discountAmount' => AttributeType::Number,
            'allGroups' => [
                AttributeType::Bool,
                'required' => true,
                'default' => 0
            ],
            'allProducts' => [
                AttributeType::Bool,
                'required' => true,
                'default' => 0
            ],
            'allProductTypes' => [
                AttributeType::Bool,
                'required' => true,
                'default' => 0
            ],
            'enabled' => AttributeType::Bool,
        ];
    }
}