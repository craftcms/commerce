<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\models;

use Craft;
use craft\commerce\base\Model;
use craft\commerce\db\Table;
use craft\commerce\elements\Order;
use craft\commerce\helpers\Localization;
use craft\commerce\Plugin;
use craft\commerce\records\Discount as DiscountRecord;
use craft\commerce\services\Coupons;
use craft\commerce\validators\CouponsValidator;
use craft\db\Query;
use craft\helpers\UrlHelper;
use craft\validators\UniqueValidator;
use DateTime;
use yii\base\InvalidConfigException;

/**
 * Discount model
 *
 * @property string|false $cpEditUrl
 * @property-read string $percentDiscountAsPercent
 * @property array $categoryIds
 * @property array $purchasableIds
 * @property-read array $userGroupsConditions
 * @property array|Coupon[] $coupons
 * @property array $userGroupIds
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class Discount extends Model
{
    /**
     * @var int|null ID
     */
    public ?int $id = null;

    /**
     * @var string Name of the discount
     */
    public string $name = '';

    /**
     * @var string|null The description of this discount
     */
    public ?string $description = null;

    /**
     * @var string Format coupons should be generated with
     * @since 4.0
     */
    public string $couponFormat = Coupons::DEFAULT_COUPON_FORMAT;

    /**
     * @var int Per user coupon use limit
     */
    public int $perUserLimit = 0;

    /**
     * @var int Per email coupon use limit
     */
    public int $perEmailLimit = 0;

    /**
     * @var int Total use limit by guests or users
     * @since 3.0
     */
    public int $totalDiscountUseLimit = 0;

    /**
     * @var int Total use counter;
     * @since 3.0
     */
    public int $totalDiscountUses = 0;

    /**
     * @var DateTime|null Date the discount is valid from
     */
    public ?DateTime $dateFrom = null;

    /**
     * @var DateTime|null Date the discount is valid to
     */
    public ?DateTime $dateTo = null;

    /**
     * @var float Total minimum spend on matching items
     */
    public float $purchaseTotal = 0;

    /**
     * @var string|null Condition that must match to match the order, null or empty string means match all
     */
    public ?string $orderConditionFormula = null;

    /**
     * @var int Total minimum qty of matching items
     */
    public int $purchaseQty = 0;

    /**
     * @var int Total maximum spend on matching items
     */
    public int $maxPurchaseQty = 0;

    /**
     * @var float Base amount of discount
     */
    public $baseDiscount = 0;

    /**
     * @var string Type of discount for the base discount e.g. currency value or percentage
     */
    public string $baseDiscountType = DiscountRecord::BASE_DISCOUNT_TYPE_VALUE;

    /**
     * @var float Amount of discount per item
     */
    public float $perItemDiscount = 0.0;

    /**
     * @var float Percentage of amount discount per item
     */
    public float $percentDiscount = 0.0;

    /**
     * @var string Whether the discount is off the original price, or the already discount price.
     */
    public string $percentageOffSubject = DiscountRecord::TYPE_DISCOUNTED_SALEPRICE;

    /**
     * @var bool Exclude the “On Sale” Purchasables
     */
    public bool $excludeOnSale = false;

    /**
     * @var bool Matching products have free shipping.
     */
    public bool $hasFreeShippingForMatchingItems = false;

    /**
     * @var bool The whole order has free shipping.
     */
    public bool $hasFreeShippingForOrder = false;

    /**
     * @var string Type of user group condition that should match the discount.
     * @see getUserConditions()
     */
    public ?string $userGroupsCondition = null;

    /**
     * @var bool Match all products
     */
    public bool $allPurchasables = false;

    /**
     * @var bool Match all product types
     */
    public bool $allCategories = false;

    /**
     * @var string Type of relationship between Categories and Products
     */
    public string $categoryRelationshipType = DiscountRecord::CATEGORY_RELATIONSHIP_TYPE_BOTH;

    /**
     * @var bool Discount enabled?
     */
    public bool $enabled = true;

    /**
     * @var bool stopProcessing
     */
    public bool $stopProcessing = false;

    /**
     * @var int|null sortOrder
     */
    public ?int $sortOrder = 999999;

    /**
     * @var DateTime|null
     */
    public ?DateTime $dateCreated = null;

    /**
     * @var DateTime|null
     */
    public ?DateTime $dateUpdated = null;

    /**
     * @var bool Discount ignores sales
     */
    public bool $ignoreSales = true;

    /**
     * @var bool What the per item amount and per item percentage off amounts can apply to
     */
    public $appliedTo = DiscountRecord::APPLIED_TO_MATCHING_LINE_ITEMS;

    /**
     * @var int[] Product Ids
     */
    private array $_purchasableIds;

    /**
     * @var int[] Product Type IDs
     */
    private array $_categoryIds;

    /**
     * @var int[] Group IDs
     */
    private array $_userGroupIds;

    /**
     * @var Coupon[]|null
     * @since 4.0
     */
    private ?array $_coupons = null;

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

    public function getCpEditUrl(): string
    {
        return UrlHelper::cpUrl('commerce/promotions/discounts/' . $this->id);
    }

    /**
     * @return int[]
     */
    public function getCategoryIds(): array
    {
        if (!isset($this->_categoryIds)) {
            $this->_loadCategoryRelations();
        }

        return $this->_categoryIds;
    }

    /**
     * @return int[]
     */
    public function getPurchasableIds(): array
    {
        if (!isset($this->_purchasableIds)) {
            $this->_loadPurchasableRelations();
        }

        return $this->_purchasableIds;
    }

    /**
     * @return int[]
     */
    public function getUserGroupIds(): array
    {
        if (!isset($this->_userGroupIds)) {
            $this->_loadUserGroupRelations();
        }

        return $this->_userGroupIds;
    }

    /**
     * Sets the related product type ids
     *
     * @param int[] $categoryIds
     */
    public function setCategoryIds(array $categoryIds): void
    {
        $this->_categoryIds = array_unique($categoryIds);
    }

    /**
     * Sets the related product ids
     *
     * @param int[] $purchasableIds
     */
    public function setPurchasableIds(array $purchasableIds): void
    {
        $this->_purchasableIds = array_unique($purchasableIds);
    }

    /**
     * Sets the related user group ids
     *
     * @param int[] $userGroupIds
     */
    public function setUserGroupIds(array $userGroupIds): void
    {
        $this->_userGroupIds = array_unique($userGroupIds);
    }

    public function setHasFreeShippingForMatchingItems(bool $value): void
    {
        $this->hasFreeShippingForMatchingItems = $value;
    }

    public function getHasFreeShippingForMatchingItems(): bool
    {
        return $this->hasFreeShippingForMatchingItems;
    }

    /**
     * @return array|Coupon[]
     * @throws InvalidConfigException
     */
    public function getCoupons(): array
    {
        if ($this->_coupons === null && $this->id) {
            $this->_coupons = Plugin::getInstance()->getCoupons()->getCouponsByDiscountId($this->id);
        }

        return $this->_coupons ?? [];
    }

    /**
     * @param array $coupons
     */
    public function setCoupons(array $coupons): void
    {
        $this->_coupons = $coupons;
    }

    public function getPercentDiscountAsPercent(): string
    {
        return Craft::$app->getFormatter()->asPercent(-($this->percentDiscount ?? 0.0));
    }

    public function getUserGroupsConditions(): array
    {
        return [
            DiscountRecord::CONDITION_USER_GROUPS_ANY_OR_NONE => Craft::t('commerce', 'All users'),
            DiscountRecord::CONDITION_USER_GROUPS_INCLUDE_ALL => Craft::t('commerce', 'Users in all of these groups:'),
            DiscountRecord::CONDITION_USER_GROUPS_INCLUDE_ANY => Craft::t('commerce', 'Users in any of these groups:'),
            DiscountRecord::CONDITION_USER_GROUPS_EXCLUDE => Craft::t('commerce', 'Users in none of these groups:'),
        ];
    }

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        return [
            [['name', 'couponFormat'], 'required'],
            [
                [
                    'perUserLimit',
                    'perEmailLimit',
                    'totalDiscountUseLimit',
                    'totalDiscountUses',
                    'purchaseTotal',
                    'purchaseQty',
                    'maxPurchaseQty',
                    'baseDiscount',
                    'perItemDiscount',
                    'percentDiscount',
                ], 'number', 'skipOnEmpty' => false,
            ],
            [['coupons'], CouponsValidator::class, 'skipOnEmpty' => true],
            [['couponFormat'], 'string', 'length' => [1, 20]],
            [
                ['categoryRelationshipType'],
                'in', 'range' => [
                DiscountRecord::CATEGORY_RELATIONSHIP_TYPE_SOURCE,
                DiscountRecord::CATEGORY_RELATIONSHIP_TYPE_TARGET,
                DiscountRecord::CATEGORY_RELATIONSHIP_TYPE_BOTH,
            ],
            ],
            [
                ['appliedTo'],
                'in',
                'range' => [
                    DiscountRecord::APPLIED_TO_MATCHING_LINE_ITEMS,
                    DiscountRecord::APPLIED_TO_ALL_LINE_ITEMS,
                ],
            ],
            [['code'], UniqueValidator::class, 'targetClass' => DiscountRecord::class, 'targetAttribute' => ['code']],
            [
                'hasFreeShippingForOrder',
                function($attribute, $params, $validator) {
                    if ($this->hasFreeShippingForMatchingItems && $this->hasFreeShippingForOrder) {
                        $this->addError($attribute, Craft::t('commerce', 'Free shipping can only be for whole order or matching items, not both.'));
                    }
                },
            ],
            [['orderConditionFormula'], 'string', 'length' => [1, 65000], 'skipOnEmpty' => true],
            [
                'orderConditionFormula',
                function($attribute, $params, $validator) {
                    if ($this->{$attribute}) {
                        $order = Order::find()->one();
                        if (!$order) {
                            $order = new Order();
                        }

                        if (!Plugin::getInstance()->getFormulas()->validateConditionSyntax($this->{$attribute}, Plugin::getInstance()->getDiscounts()->getOrderConditionParams($order))) {
                            $this->addError($attribute, Craft::t('commerce', 'Invalid order condition syntax.'));
                        }
                    }
                },
            ],
        ];
    }

    /**
     * Loads the related purchasable IDs into this discount
     */
    private function _loadPurchasableRelations(): void
    {
        $purchasableIds = (new Query())->select(['dp.purchasableId'])
            ->from(Table::DISCOUNTS . ' discounts')
            ->leftJoin(Table::DISCOUNT_PURCHASABLES . ' dp', '[[dp.discountId]]=[[discounts.id]]')
            ->where(['discounts.id' => $this->id])
            ->column();

        $this->setPurchasableIds($purchasableIds);
    }

    /**
     * Loads the related category IDs into this discount
     */
    private function _loadCategoryRelations(): void
    {
        $categoryIds = (new Query())->select(['dpt.categoryId'])
            ->from(Table::DISCOUNTS . ' discounts')
            ->leftJoin(Table::DISCOUNT_CATEGORIES . ' dpt', '[[dpt.discountId]]=[[discounts.id]]')
            ->where(['discounts.id' => $this->id])
            ->column();

        $this->setCategoryIds($categoryIds);
    }

    /**
     * Loads the related user group IDs into this discount
     */
    private function _loadUserGroupRelations(): void
    {
        $userGroupIds = (new Query())->select(['dug.userGroupId'])
            ->from(Table::DISCOUNTS . ' discounts')
            ->leftJoin(Table::DISCOUNT_USERGROUPS . ' dug', '[[dug.discountId]]=[[discounts.id]]')
            ->where(['discounts.id' => $this->id])
            ->column();

        $this->setUserGroupIds($userGroupIds);
    }
}
