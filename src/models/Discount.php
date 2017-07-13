<?php

namespace craft\commerce\models;

use Craft;
use craft\commerce\base\Model;
use craft\commerce\Plugin;
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
     * @var int[] Product Ids
     */
    private $_productIds;

    /**
     * @var int[] Product Type IDs
     */
    private $_productTypeIds;

    /**
     * @var int[] Group IDs
     */
    private $_userGroupIds;

    /**
     * @inheritdoc
     */
    public function rules(): array
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
     * @inheritdoc
     */
    public function datetimeAttributes(): array
    {
        $names = parent::datetimeAttributes();
        $names[] = 'dateFrom';
        $names[] = 'dateTo';

        return $names;
    }

    /**
     * @return string|false
     */
    public function getCpEditUrl()
    {
        return UrlHelper::cpUrl('commerce/promotions/discounts/'.$this->id);
    }

    /**
     * @return array
     */
    public function getProductTypeIds(): array
    {
        if (null === $this->_productTypeIds) {
            $this->_loadRelations();
        }

        return $this->_productTypeIds;
    }

    /**
     * @return array
     */
    public function getProductIds(): array
    {
        if (null === $this->_productIds) {
            $this->_loadRelations();
        }

        return $this->_productIds;
    }

    /**
     * @return array
     */
    public function getUserGroupIds(): array
    {
        if (null === $this->_userGroupIds) {
            $this->_loadRelations();
        }

        return $this->_userGroupIds;
    }

    /**
     * Set the related product type ids
     *
     * @param array $ids
     *
     * @return void
     */
    public function setProductTypeIds(array $ids)
    {
        $this->_productTypeIds = array_unique($ids);
    }

    /**
     * Set the related product ids
     *
     * @param array $productIds
     *
     * @return void
     */
    public function setProductIds(array $productIds)
    {
        $this->_productIds = array_unique($productIds);
    }

    /**
     * Set the related user group ids
     *
     * @param array $userGroupIds
     *
     * @return void
     */
    public function setUserGroupIds(array $userGroupIds)
    {
        $this->_userGroupIds = array_unique($userGroupIds);
    }

    /**
     * Load the sale relations
     *
     * @return void
     */
    private function _loadRelations()
    {
        Plugin::getInstance()->getDiscounts()->populateDiscountRelations($this);
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
