<?php

namespace craft\commerce\models;

use Craft;
use craft\commerce\base\Model;
use craft\commerce\Plugin;
use craft\helpers\UrlHelper;

/**
 * Discount model
 *
 * @property string|false $cpEditUrl
 * @property string $percentDiscountAsPercent
 * @property array $userGroupIds
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
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
     * @var string Whether the discount is off the original price, or the already discount price.
     */
    public $percentageOffSubject;

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
    public $allPurchasables;

    /**
     * @var bool Match all product types
     */
    public $allCategories;

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
    private $_purchasableIds;

    /**
     * @var int[] Product Type IDs
     */
    private $_categoryIds;

    /**
     * @var int[] Group IDs
     */
    private $_userGroupIds;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function datetimeAttributes(): array
    {
        $attributes = parent::datetimeAttributes();
        $attributes[] = 'dateFrom';
        $attributes[] = 'dateTo';

        return $attributes;
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
    public function getCategoryIds(): array
    {
        if (null === $this->_categoryIds) {
            $this->_loadRelations();
        }

        return $this->_categoryIds;
    }

    /**
     * @return array
     */
    public function getPurchasableIds(): array
    {
        if (null === $this->_purchasableIds) {
            $this->_loadRelations();
        }

        return $this->_purchasableIds;
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
     * @param array $categoryIds
     */
    public function setCategoryIds(array $categoryIds)
    {
        $this->_categoryIds = array_unique($categoryIds);
    }

    /**
     * Set the related product ids
     *
     * @param array $purchasableIds
     */
    public function setPurchasableIds(array $purchasableIds)
    {
        $this->_purchasableIds = array_unique($purchasableIds);
    }

    /**
     * Set the related user group ids
     *
     * @param array $userGroupIds
     */
    public function setUserGroupIds(array $userGroupIds)
    {
        $this->_userGroupIds = array_unique($userGroupIds);
    }

    /**
     * @return string
     */
    public function getPercentDiscountAsPercent(): string
    {
        if ($this->percentDiscount !== 0) {
            return Craft::$app->formatter->asPercent(-$this->percentDiscount);
        }

        return Craft::$app->formatter->asPercent(0);
    }

    // Private Methods
    // =========================================================================

    /**
     * Load the sale relations
     */
    private function _loadRelations()
    {
        Plugin::getInstance()->getDiscounts()->populateDiscountRelations($this);
    }
}
