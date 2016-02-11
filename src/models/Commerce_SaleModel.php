<?php
namespace Craft;

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
    /**
     * @return array
     */
    public function getGroupIds()
    {
	    return $this->getAttribute('groups');
    }

    /**
     * @return array
     */
    public function getProductTypeIds()
    {
	    return $this->getAttribute('productTypes');
    }

    /**
     * @return array
     */
    public function getProductIds()
    {
	    return $this->getAttribute('products');
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
            'products' => [AttributeType::Mixed, 'default' => []],
            'productTypes' => [AttributeType::Mixed, 'default' => []],
            'groups' => [AttributeType::Mixed, 'default' => []],
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
            'enabled' => [AttributeType::Bool, 'default' => true],
        ];
    }
}