<?php

namespace craft\commerce\models;

use Craft;
use craft\commerce\base\Model;
use craft\helpers\UrlHelper;

/**
 * Discount model
 *
 * @property \craft\commerce\elements\Product[]   $products
 * @property \craft\commerce\models\ProductType[] $productTypes
 * @property \craft\models\UserGroup[]            $userGroups
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2017, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.commerce
 * @since     2.0
 */
class Discount extends Model
{
    // Properties
    // =========================================================================

    /**
     * @var int ID
     */
    public $id;

    /**
     * @var string Name of the discount
     */
    public $name;

    /**
     * @var string The description of this discount
     */
    public $description;

    /**
     * @var string Coupon Code
     */
    public $code;

    /**
     * @var int Per user coupon use limit
     */
    public $perUserLimit = 0;

    /**
     * @var int Per email coupon use limit
     */
    public $perEmailLimit = 0;

    /**
     * @var int Total use limit by guests or users
     */
    public $totalUseLimit = 0;

    /**
     * @var int Total use counter;
     */
    public $totalUses = 0;

    /**
     * @var \DateTime|null Date the discount is valid from
     */
    public $dateFrom;

    /**
     * @var \DateTime|null Date the discount is valid to
     */
    public $dateTo;

    /**
     * @var float Total minimum spend on matching items
     */
    public $purchaseTotal = 0;

    /**
     * @var int Total minimum qty of matching items
     */
    public $purchaseQty = 0;

    /**
     * @var int Total maximum spend on matching items
     */
    public $maxPurchaseQty = 0;

    /**
     * @var float Base amount of discount
     */
    public $baseDiscount = 0;

    /**
     * @var float Amount of discount per item
     */
    public $perItemDiscount;

    /**
     * @var float Percentage of amount discount per item
     */
    public $percentDiscount;

    /**
     * @var bool Exclude on sale purchasables
     */
    public $excludeOnSale;

    /**
     * @var bool Order has free shipping.
     */
    public $freeShipping;

    /**
     * @var bool Match all user groups.
     */
    public $allGroups;

    /**
     * @var bool Match all products
     */
    public $allProducts;

    /**
     * @var bool Match all product types
     */
    public $allProductTypes;

    /**
     * @var bool Discount enabled?
     */
    public $enabled = true;

    /**
     * @var bool stopProcessing
     */
    public $stopProcessing;

    /**
     * @var int sortOrder
     */
    public $sortOrder;

    /**
     * @var \craft\commerce\elements\Product Products
     */
    private $_products;

    /**
     * @var \craft\commerce\models\ProductType[] Products
     */
    private $_productTypes;

    /**
     * @var \craft\models\UserGroup[]|null User Groups
     */
    private $_userGroups;

    /**
     * @inheritdoc
     */
    public function rules()
    {
        $rules = [
            [['name'], 'required'],
            [['purchaseTotal'], 'required'],
            [['purchaseQty'], 'required'],
            [['maxPurchaseQty'], 'required'],
            [['baseDiscount'], 'required'],
            [['perItemDiscount'], 'required'],
            [['perUserLimit'], 'required'],
            [['percentDiscount'], 'required'],
            [['excludeOnSale'], 'required'],
            [['freeShipping'], 'required'],
            [['allGroups'], 'required'],
            [['allProducts'], 'required'],
            [['allProductTypes'], 'required'],
            [['enabled'], 'required'],
            [['stopProcessing'], 'required']
        ];

        return $rules;
    }

    /**
     * @return string|false
     */
    public function getCpEditUrl()
    {
        return UrlHelper::cpUrl('commerce/promotions/discounts/'.$this->id);
    }

    /**
     * @return \craft\models\UserGroup[]|null
     */
    public function getGroups()
    {
        Craft::$app->getDeprecator()->log('Discount::groups', 'The "getGroups()" method has been deprecated. Use "getUserGroups" instead.');

        return $this->getUserGroups();
    }

    /**
     * @return \craft\models\UserGroup[]|null
     */
    public function getUserGroups()
    {
        return $this->_userGroups;
    }

    /**
     * @param \craft\models\UserGroup[] $groups
     */
    public function setUserGroups($groups)
    {
        $this->_userGroups = $groups;
    }

    /**
     * @return array
     */
    public function getGroupIds()
    {
        return array_column($this->getUserGroups(), 'id');
    }

    /**
     * @return array
     */
    public function getProductTypeIds()
    {
        return array_column($this->getProductTypes(), 'id');
    }

    /**
     * @return ProductType[]|null
     */
    public function getProductTypes()
    {
        return $this->_productTypes;
    }

    /**
     * @param ProductType[] $productTypes
     */
    public function setProductTypes($productTypes)
    {
        $this->_productTypes = $productTypes;
    }

    /**
     * @return array
     */
    public function getProductIds()
    {
        return array_column($this->getProducts(), 'id');
    }

    /**
     * @return \craft\commerce\elements\Product[]|null
     */
    public function getProducts()
    {
        return $this->_products;
    }

    /**
     * @param \craft\commerce\elements\Product[] $products
     */
    public function setProducts($products)
    {
        $this->_products = $products;
    }

    /**
     * @return string
     */
    public function getPercentDiscountAsPercent()
    {
        if ($this->percentDiscount) {
            return Craft::$app->formatter->asPercent($this->percentDiscount);
        }

        return "";
    }
}
