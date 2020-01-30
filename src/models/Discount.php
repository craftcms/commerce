<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\models;

use Craft;
use craft\commerce\base\Model;
use craft\commerce\Plugin;
use craft\commerce\records\Discount as DiscountRecord;
use craft\helpers\UrlHelper;
use craft\validators\UniqueValidator;
use DateTime;

/**
 * Discount model
 *
 * @property string|false $cpEditUrl
 * @property-read string $percentDiscountAsPercent
 * @property array $categoryIds
 * @property array $purchasableIds
 * @property array $userGroupIds
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class Discount extends Model
{
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
     * @since 3.0
     */
    public $totalDiscountUseLimit = 0;

    /**
     * @var int Total use counter;
     * @since 3.0
     */
    public $totalDiscountUses = 0;

    /**
     * @var DateTime|null Date the discount is valid from
     */
    public $dateFrom;

    /**
     * @var DateTime|null Date the discount is valid to
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
     * @var string Type of discount for the base discount e.g. currency value or percentage
     */
    public $baseDiscountType;

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
     * @var bool Matching products have free shipping.
     */
    public $hasFreeShippingForMatchingItems;

    /**
     * @var bool The whole order has free shipping.
     */
    public $hasFreeShippingForOrder;

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
     * @var string Type of relationship between Categories and Products
     */
    public $categoryRelationshipType;

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
     * @var DateTime|null
     */
    public $dateCreated;

    /**
     * @var DateTime|null
     */
    public $dateUpdated;

    /**
     * @var bool Discount ignores sales
     */
    public $ignoreSales = true;

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
        return UrlHelper::cpUrl('commerce/promotions/discounts/' . $this->id);
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
     * Sets the related product type ids
     *
     * @param array $categoryIds
     */
    public function setCategoryIds(array $categoryIds)
    {
        $this->_categoryIds = array_unique($categoryIds);
    }

    /**
     * Sets the related product ids
     *
     * @param array $purchasableIds
     */
    public function setPurchasableIds(array $purchasableIds)
    {
        $this->_purchasableIds = array_unique($purchasableIds);
    }

    /**
     * Sets the related user group ids
     *
     * @param array $userGroupIds
     */
    public function setUserGroupIds(array $userGroupIds)
    {
        $this->_userGroupIds = array_unique($userGroupIds);
    }

    /**
     * @param bool $value
     */
    public function setHasFreeShippingForMatchingItems($value)
    {
        $this->hasFreeShippingForMatchingItems = (bool)$value;
    }

    /**
     * @return bool
     */
    public function getHasFreeShippingForMatchingItems(): bool
    {
        return (bool)$this->hasFreeShippingForMatchingItems;
    }

    /**
     * @return string
     */
    public function getPercentDiscountAsPercent(): string
    {
        if ($this->percentDiscount !== 0) {
            $string = (string)$this->percentDiscount;
            $number = rtrim($string, '0');
            $diff = strlen($string) - strlen($number);
            return Craft::$app->formatter->asPercent(-$this->percentDiscount, 2 - $diff);
        }

        return Craft::$app->formatter->asPercent(0);
    }

    /**
     * @inheritdoc
     */
    public function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [['name'], 'required'];
        $rules[] = [
            [
                'purchaseTotal',
                'perUserLimit',
                'perEmailLimit',
                'totalDiscountUseLimit',
                'totalDiscountUses',
                'purchaseTotal',
                'purchaseQty',
                'maxPurchaseQty',
                'baseDiscount',
                'perItemDiscount',
                'percentDiscount'
            ], 'number', 'skipOnEmpty' => false
        ];
        $rules[] = [['code'], UniqueValidator::class, 'targetClass' => DiscountRecord::class, 'targetAttribute' => ['code']];
        $rules[] = [
            ['categoryRelationshipType'], 'in', 'range' =>
                [
                    DiscountRecord::CATEGORY_RELATIONSHIP_TYPE_SOURCE,
                    DiscountRecord::CATEGORY_RELATIONSHIP_TYPE_TARGET,
                    DiscountRecord::CATEGORY_RELATIONSHIP_TYPE_BOTH
                ]
        ];
        $rules[] = [['code'], UniqueValidator::class, 'targetClass' => DiscountRecord::class, 'targetAttribute' => ['code']];
        $rules[] = [
            'hasFreeShippingForOrder', function($attribute, $params, $validator) {
                if ($this->hasFreeShippingForMatchingItems && $this->hasFreeShippingForOrder) {
                    $this->addError($attribute, 'Free shipping can only be for whole order or matching items, not both.');
                }
            }
        ];

        return $rules;
    }


    /**
     * Loads the sale relations
     */
    private function _loadRelations()
    {
        Plugin::getInstance()->getDiscounts()->populateDiscountRelations($this);
    }
}
