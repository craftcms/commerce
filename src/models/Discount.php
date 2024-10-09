<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\models;

use Craft;
use craft\commerce\base\HasStoreInterface;
use craft\commerce\base\Model;
use craft\commerce\base\StoreTrait;
use craft\commerce\db\Table;
use craft\commerce\elements\conditions\addresses\DiscountAddressCondition;
use craft\commerce\elements\conditions\customers\DiscountCustomerCondition;
use craft\commerce\elements\conditions\orders\DiscountOrderCondition;
use craft\commerce\elements\Order;
use craft\commerce\Plugin;
use craft\commerce\records\Discount as DiscountRecord;
use craft\commerce\services\Coupons;
use craft\commerce\validators\CouponsValidator;
use craft\db\Query;
use craft\elements\conditions\ElementConditionInterface;
use craft\helpers\Json;
use DateTime;
use yii\base\InvalidConfigException;

/**
 * Discount model
 *
 * @property string|false $cpEditUrl
 * @property-read string $percentDiscountAsPercent
 * @property array $categoryIds
 * @property array $purchasableIds
 * @property array|Coupon[] $coupons
 * @property string|array|ElementConditionInterface $orderCondition
 * @property string|array|ElementConditionInterface $shippingAddressCondition
 * @property string|array|ElementConditionInterface $billingAddressCondition
 * @property string|array|ElementConditionInterface $customerCondition
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class Discount extends Model implements HasStoreInterface
{
    use StoreTrait;

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
     * @var ElementConditionInterface|null
     * @see getOrderCondition()
     * @see setOrderCondition()
     */
    public null|ElementConditionInterface $_orderCondition = null;

    /**
     * @var ElementConditionInterface|null
     * @see getCustomerCondition()
     * @see setCustomerCondition()
     */
    public null|ElementConditionInterface $_customerCondition = null;

    /**
     * @var ElementConditionInterface|null
     * @see getShippingAddressCondition()
     * @see setShippingAddressCondition()
     */
    public null|ElementConditionInterface $_shippingAddressCondition = null;

    /**
     * @var ElementConditionInterface|null
     * @see getBillingAddressCondition()
     * @see setBillingAddressCondition()
     */
    public null|ElementConditionInterface $_billingAddressCondition = null;

    /**
     * @var bool Requires a coupon code to be applied
     * @since 5.2.0
     */
    public bool $requireCouponCode = false;

    /**
     * @var int Per user coupon use limit
     */
    public int $perUserLimit = 0;

    /**
     * @var int Per email coupon use limit
     */
    public int $perEmailLimit = 0;

    /**
     * @var int Total use limit by users
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
    public float $baseDiscount = 0;

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
     * @var bool Exclude the “On Promotion” Purchasables
     */
    public bool $excludeOnPromotion = false;

    /**
     * @var bool Matching products have free shipping.
     */
    public bool $hasFreeShippingForMatchingItems = false;

    /**
     * @var bool The whole order has free shipping.
     */
    public bool $hasFreeShippingForOrder = false;

    /**
     * @var bool Match all products
     */
    public bool $allPurchasables = false;

    /**
     * @var bool Match all product types
     *
     * TODO: Rename to $allEntries in 6.0
     */
    public bool $allCategories = false;

    /**
     * @var string Type of relationship between Categories and Products
     *
     * TODO: Rename to $entryRelationshipType in 6.0
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
    public bool $ignorePromotions = true;

    /**
     * @var string What the per item amount and per item percentage off amounts can apply to
     */
    public string $appliedTo = DiscountRecord::APPLIED_TO_MATCHING_LINE_ITEMS;

    /**
     * @var int[] Product Ids
     */
    private array $_purchasableIds;

    /**
     * @var int[] Product Type IDs
     */
    private array $_categoryIds;

    /**
     * @var Coupon[]|null
     * @since 4.0
     */
    private ?array $_coupons = null;

    /**
     * @inheritdoc
     */
    public function extraFields(): array
    {
        $fields = parent::extraFields();
        $fields[] = 'purchasableIds';
        $fields[] = 'categoryIds';
        $fields[] = 'percentDiscountAsPercent';

        return $fields;
    }

    public function getCpEditUrl(): string
    {
        return $this->getStore()->getStoreSettingsUrl('discounts/' . $this->id);
    }

    /**
     * @param bool $exclude
     * @return void
     * @since 5.0.0
     * @deprecated in 5.0.0. Use `$excludeOnPromotion` instead.
     */
    public function setExcludeOnSale(bool $exclude): void
    {
        Craft::$app->getDeprecator()->log(__METHOD__, 'Discount::$excludeOnSale is deprecated. Use Discount::$excludeOnPromotion instead.');
        $this->excludeOnPromotion = $exclude;
    }

    /**
     * @return bool
     * @since 5.0.0
     * @deprecated in 5.0.0. Use `$excludeOnPromotion` instead.
     */
    public function getExcludeOnSale(): bool
    {
        Craft::$app->getDeprecator()->log(__METHOD__, 'Discount::$excludeOnSale is deprecated. Use Discount::$excludeOnPromotion instead.');
        return $this->excludeOnPromotion;
    }

    /**
     * @return ElementConditionInterface
     */
    public function getOrderCondition(): ElementConditionInterface
    {
        /** @var DiscountOrderCondition $condition */
        $condition = $this->_orderCondition ?? new DiscountOrderCondition();
        $condition->mainTag = 'div';
        $condition->name = 'orderCondition';
        $condition->storeId = $this->storeId;

        return $condition;
    }

    /**
     * @return bool
     * @since 4.3.0
     */
    public function hasOrderCondition(): bool
    {
        if ($this->_orderCondition === null) {
            return false;
        }

        return !empty($this->getOrderCondition()->getConditionRules());
    }

    /**
     * @param ElementConditionInterface|string|array $condition
     * @return void
     * @throws InvalidConfigException
     */
    public function setOrderCondition(ElementConditionInterface|string|array $condition): void
    {
        if (empty($condition)) {
            $this->_orderCondition = null;
            return;
        }

        if (is_string($condition)) {
            $condition = Json::decodeIfJson($condition);
        }

        if (!$condition instanceof ElementConditionInterface) {
            $condition['class'] = DiscountOrderCondition::class;
            /** @var DiscountOrderCondition $condition */
            $condition = Craft::$app->getConditions()->createCondition($condition);
        }
        $condition->forProjectConfig = false;

        $this->_orderCondition = $condition;
    }

    /**
     * @return ElementConditionInterface
     */
    public function getCustomerCondition(): ElementConditionInterface
    {
        $condition = $this->_customerCondition ?? new DiscountCustomerCondition();
        $condition->mainTag = 'div';
        $condition->name = 'customerCondition';

        return $condition;
    }

    /**
     * @return bool
     * @since 4.3.0
     */
    public function hasCustomerCondition(): bool
    {
        if ($this->_customerCondition === null) {
            return false;
        }

        return !empty($this->getCustomerCondition()->getConditionRules());
    }

    /**
     * @param ElementConditionInterface|string $condition
     * @return void
     * @throws InvalidConfigException
     */
    public function setCustomerCondition(ElementConditionInterface|string|array $condition): void
    {
        if (empty($condition)) {
            $this->_customerCondition = null;
            return;
        }

        if (is_string($condition)) {
            $condition = Json::decodeIfJson($condition);
        }

        if (!$condition instanceof ElementConditionInterface) {
            $condition['class'] = DiscountCustomerCondition::class;
            /** @var DiscountCustomerCondition $condition */
            $condition = Craft::$app->getConditions()->createCondition($condition);
        }
        $condition->forProjectConfig = false;

        $this->_customerCondition = $condition;
    }

    /**
     * @return ElementConditionInterface
     */
    public function getShippingAddressCondition(): ElementConditionInterface
    {
        $condition = $this->_shippingAddressCondition ?? new DiscountAddressCondition();
        $condition->mainTag = 'div';
        $condition->id = 'shippingAddressCondition';
        $condition->name = 'shippingAddressCondition';

        return $condition;
    }

    /**
     * @return bool
     * @since 4.3.0
     */
    public function hasShippingAddressCondition(): bool
    {
        if ($this->_shippingAddressCondition === null) {
            return false;
        }

        return !empty($this->getShippingAddressCondition()->getConditionRules());
    }

    /**
     * @param ElementConditionInterface|string|array $condition
     * @return void
     * @throws InvalidConfigException
     */
    public function setShippingAddressCondition(ElementConditionInterface|string|array $condition): void
    {
        if (empty($condition)) {
            $this->_shippingAddressCondition = null;
            return;
        }

        if (is_string($condition)) {
            $condition = Json::decodeIfJson($condition);
        }

        if (!$condition instanceof ElementConditionInterface) {
            $condition['class'] = DiscountAddressCondition::class;
            /** @var DiscountAddressCondition $condition */
            $condition = Craft::$app->getConditions()->createCondition($condition);
        }
        $condition->forProjectConfig = false;

        $this->_shippingAddressCondition = $condition;
    }

    /**
     * @return ElementConditionInterface
     */
    public function getBillingAddressCondition(): ElementConditionInterface
    {
        $condition = $this->_billingAddressCondition ?? new DiscountAddressCondition();
        $condition->mainTag = 'div';
        $condition->id = 'billingAddressCondition';
        $condition->name = 'billingAddressCondition';

        return $condition;
    }

    /**
     * @return bool
     * @since 4.3.0
     */
    public function hasBillingAddressCondition(): bool
    {
        if ($this->_billingAddressCondition === null) {
            return false;
        }

        return !empty($this->getBillingAddressCondition()->getConditionRules());
    }

    /**
     * @param ElementConditionInterface|string|array $condition
     * @return void
     * @throws InvalidConfigException
     */
    public function setBillingAddressCondition(ElementConditionInterface|string|array $condition): void
    {
        if (empty($condition)) {
            $this->_billingAddressCondition = null;
            return;
        }

        if (is_string($condition)) {
            $condition = Json::decodeIfJson($condition);
        }

        if (!$condition instanceof ElementConditionInterface) {
            $condition['class'] = DiscountAddressCondition::class;
            /** @var DiscountAddressCondition $condition */
            $condition = Craft::$app->getConditions()->createCondition($condition);
        }
        $condition->forProjectConfig = false;

        $this->_billingAddressCondition = $condition;
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
     * @param bool $value
     * @return void
     */
    public function setHasFreeShippingForMatchingItems(bool $value): void
    {
        $this->hasFreeShippingForMatchingItems = $value;
    }

    /**
     * @return bool
     */
    public function getHasFreeShippingForMatchingItems(): bool
    {
        return $this->hasFreeShippingForMatchingItems;
    }

    /**
     * @return array
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
            [
                'hasFreeShippingForOrder',
                function($attribute) {
                    if ($this->hasFreeShippingForMatchingItems && $this->hasFreeShippingForOrder) {
                        $this->addError($attribute, Craft::t('commerce', 'Free shipping can only be for whole order or matching items, not both.'));
                    }
                },
            ],
            [['orderConditionFormula'], 'string', 'length' => [1, 65000], 'skipOnEmpty' => true],
            [
                'orderConditionFormula',
                function($attribute) {
                    if ($this->{$attribute}) {
                        $order = Order::find()->one();
                        if (!$order) {
                            $order = new Order();
                        }

                        $fieldsAsArray = $order->getSerializedFieldValues();
                        $orderAsArray = $order->toArray([], ['lineItems.snapshot', 'shippingAddress', 'billingAddress']);
                        $orderConditionParams = [
                            'order' => array_merge($orderAsArray, $fieldsAsArray),
                        ];

                        if (!Plugin::getInstance()->getFormulas()->validateConditionSyntax($this->{$attribute}, $orderConditionParams)) {
                            $this->addError($attribute, Craft::t('commerce', 'Invalid order condition syntax.'));
                        }
                    }
                },
            ],
            [[
                'allCategories',
                'allPurchasables',
                'appliedTo',
                'baseDiscount',
                'baseDiscountType',
                'billingAddressCondition',
                'categoryIds',
                'categoryRelationshipType',
                'couponFormat',
                'customerCondition',
                'dateCreated',
                'dateFrom',
                'dateTo',
                'dateUpdated',
                'description',
                'enabled',
                // @TODO remove `excludeOnSale` in 6.0
                'excludeOnSale',
                'excludeOnPromotion',
                'hasFreeShippingForMatchingItems',
                'hasFreeShippingForOrder',
                'id',
                'ignoreSales',
                'maxPurchaseQty',
                'name',
                'orderCondition',
                'orderConditionFormula',
                'perEmailLimit',
                'perItemDiscount',
                'perUserLimit',
                'percentDiscount',
                'percentageOffSubject',
                'ignorePromotions',
                'purchasableIds',
                'purchaseQty',
                'purchaseTotal',
                'requireCouponCode',
                'shippingAddressCondition',
                'sortOrder',
                'stopProcessing',
                'storeId',
                'totalDiscountUseLimit',
                'totalDiscountUses',
            ], 'safe'],
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
}
