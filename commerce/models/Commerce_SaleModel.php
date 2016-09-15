<?php
namespace Craft;

/**
 * Sale model.
 *
 * @property int      $id
 * @property string   $name
 * @property string   $description
 * @property DateTime $dateFrom
 * @property DateTime $dateTo
 * @property string   $discountType
 * @property float    $discountAmount
 * @property array    $groupIds
 * @property array    $productIds
 * @property array    $productTypeIds
 * @property bool     $allGroups
 * @property bool     $allProducts
 * @property bool     $allProductTypes
 * @property bool     $enabled
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
     * @return string|false
     */
    public function getCpEditUrl()
    {
        return UrlHelper::getCpUrl('commerce/promotions/sales/'.$this->id);
    }

    /**
     * @return array
     */
    public function getGroupIds()
    {
        return $this->getAttribute('groupIds');
    }

    /**
     * @return array
     */
    public function getProductTypeIds()
    {
        return $this->getAttribute('productTypeIds');
    }

    /**
     * @return array
     */
    public function getProductIds()
    {
        return $this->getAttribute('productIds');
    }

    /**
     * @return string
     */
    public function getDiscountAmountAsPercent()
    {
        $localeData = craft()->i18n->getLocaleData();
        $percentSign = $localeData->getNumberSymbol('percentSign');

        if ($this->discountAmount != 0)
        {
            return -$this->discountAmount * 100 ."".$percentSign;
        }

        return "0".$percentSign;
    }

    /**
     * @return string
     */
    public function getDiscountAmountAsFlat()
    {
        return $this->discountAmount != 0 ? $this->discountAmount * -1 : 0;
    }

    /**
     * @param float $price
     *
     * @return float
     * @throws Exception
     */
    public function calculateTakeoff($price)
    {
        if ($this->discountType == Commerce_SaleRecord::TYPE_FLAT)
        {
            $takeOff = $this->discountAmount;
        }
        else
        {
            $takeOff = $this->discountAmount * $price;
        }

        return $takeOff;
    }

    protected function defineAttributes()
    {
        return [
            'id'              => AttributeType::Number,
            'name'            => AttributeType::Name,
            'productIds'      => [AttributeType::Mixed, 'default' => []],
            'productTypeIds'  => [AttributeType::Mixed, 'default' => []],
            'groupIds'        => [AttributeType::Mixed, 'default' => []],
            'description'     => AttributeType::Mixed,
            'dateFrom'        => AttributeType::DateTime,
            'dateTo'          => AttributeType::DateTime,
            'discountType'    => [
                AttributeType::Enum,
                'values'   => [Commerce_SaleRecord::TYPE_PERCENT, Commerce_SaleRecord::TYPE_FLAT],
                'required' => true,
                'default'  => Commerce_SaleRecord::TYPE_FLAT
            ],
            'discountAmount'  => [AttributeType::Number, 'decimals' => 4, 'default' => 0],
            'allGroups'       => [
                AttributeType::Bool,
                'required' => true,
                'default'  => 0
            ],
            'allProducts'     => [
                AttributeType::Bool,
                'required' => true,
                'default'  => 0
            ],
            'allProductTypes' => [
                AttributeType::Bool,
                'required' => true,
                'default'  => 0
            ],
            'enabled'         => [AttributeType::Bool, 'default' => true],
        ];
    }
}