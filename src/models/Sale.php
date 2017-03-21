<?php
namespace craft\commerce\models;

use craft\commerce\base\Model;
use craft\helpers\UrlHelper;

/**
 * Sale model.
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.models
 * @since     1.0
 */
class Sale extends Model
{
    /**
     * @var int ID
     */
    public $id;

    /**
     * @var string Name
     */
    public $name;

    /**
     * @var int[] Product Ids
     */
    public $productIds = [];

    /**
     * @var int[] Product Type IDs
     */
    public $productTypeIds = [];

    /**
     * @var int[] Group IDs
     */
    public $groupIds = [];

    /**
     * @var string Description
     */
    public $description;

    /**
     * @var \DateTime Date From
     */
    public $dateFrom;

    /**
     * @var \DateTime Date To
     */
    public $dateTo;

    /**
     * @var string Discount Type
     */
    public $discountType;

    /**
     * @var float Discount amount
     */
    public $discountAmount;

    /**
     * @var bool Match all groups
     */
    public $allGroups = false;

    /**
     * @var bool Match all products
     */
    public $allProducts = false;

    /**
     * @var bool Match all product types
     */
    public $allProductTypes = false;

    /**
     * @var bool Enabled
     */
    public $enabled = true;

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [
                ['discountType'],
                'in',
                'range' => [
                    'percent',
                    'flat'
                ],
            ],
            [['default', 'enabled'], 'boolean'],
            [['discountType', 'allGroups', 'allProducts', 'allProductTypes'], 'required'],
        ];
    }

    /**
     * @return string|false
     */
    public function getCpEditUrl()
    {
        return UrlHelper::cpUrl('commerce/promotions/sales/'.$this->id);
    }

    /**
     * @return string
     */
    public function getDiscountAmountAsPercent()
    {
        $localeData = craft()->i18n->getLocaleData();
        $percentSign = $localeData->getNumberSymbol('percentSign');

        if ($this->discountAmount != 0) {
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
        if ($this->discountType == 'flat') {
            $takeOff = $this->discountAmount;
        } else {
            $takeOff = $this->discountAmount * $price;
        }

        return $takeOff;
    }
}