<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Promotion\Models;

use craft\commerce\elements\conditions\addresses\DiscountAddressCondition;
use craft\commerce\elements\conditions\customers\DiscountCustomerCondition;
use craft\commerce\elements\conditions\orders\DiscountOrderCondition;
use craft\commerce\elements\Order;
use craft\commerce\Plugin;
use CraftCms\Cms\Component\Component;
use CraftCms\Cms\Element\Conditions\Contracts\ElementConditionInterface;
use CraftCms\Cms\Support\Facades\Conditions;
use CraftCms\Cms\Support\Facades\I18N;
use CraftCms\Cms\Support\Json;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Promotion\Coupons;
use CraftCms\Commerce\Promotion\Records\Discount as DiscountRecord;
use CraftCms\Commerce\Store\Concerns\StoreTrait;
use CraftCms\Commerce\Store\Contracts\HasStoreInterface;
use DateTime;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use function CraftCms\Cms\t;

class Discount extends Component implements HasStoreInterface
{
    use StoreTrait;

    public ?int $id = null;

    public string $name = '';

    public ?string $description = null;

    public string $couponFormat = Coupons::DEFAULT_COUPON_FORMAT;

    public ?ElementConditionInterface $_orderCondition = null;
    public ?ElementConditionInterface $_customerCondition = null;
    public ?ElementConditionInterface $_shippingAddressCondition = null;
    public ?ElementConditionInterface $_billingAddressCondition = null;

    public bool $requireCouponCode = false;
    public int $perUserLimit = 0;
    public int $perEmailLimit = 0;
    public int $totalDiscountUseLimit = 0;
    public int $totalDiscountUses = 0;

    public ?DateTime $dateFrom = null;
    public ?DateTime $dateTo = null;

    public float $purchaseTotal = 0;

    public ?string $orderConditionFormula = null;

    public int $purchaseQty = 0;
    public int $maxPurchaseQty = 0;
    public float $baseDiscount = 0;
    public float $perItemDiscount = 0.0;
    public float $percentDiscount = 0.0;

    public string $percentageOffSubject = DiscountRecord::TYPE_DISCOUNTED_SALEPRICE;

    public bool $excludeOnPromotion = false;
    public bool $hasFreeShippingForMatchingItems = false;
    public bool $hasFreeShippingForOrder = false;
    public bool $allPurchasables = false;
    /** TODO: Rename to $allEntries in 6.0 */
    public bool $allCategories = false;

    /** TODO: Rename to $entryRelationshipType in 6.0 */
    public string $categoryRelationshipType = DiscountRecord::CATEGORY_RELATIONSHIP_TYPE_BOTH;

    public bool $enabled = true;
    public bool $stopProcessing = false;
    public ?int $sortOrder = 999999;
    public ?DateTime $dateCreated = null;
    public ?DateTime $dateUpdated = null;
    public bool $ignorePromotions = true;
    public string $appliedTo = DiscountRecord::APPLIED_TO_MATCHING_LINE_ITEMS;

    /** @var int[] */
    private array $_purchasableIds;
    /** @var int[] */
    private array $_categoryIds;
    /** @var Coupon[]|null */
    private ?array $_coupons = null;

    #[\Override]
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

    public function getOrderCondition(): ElementConditionInterface
    {
        /** @var DiscountOrderCondition $condition */
        $condition = $this->_orderCondition ?? new DiscountOrderCondition();
        $condition->mainTag = 'div';
        $condition->name = 'orderCondition';
        $condition->storeId = $this->storeId;

        return $condition;
    }

    public function hasOrderCondition(): bool
    {
        if ($this->_orderCondition === null) {
            return false;
        }

        return !empty($this->getOrderCondition()->getConditionRules());
    }

    public function setOrderCondition(ElementConditionInterface|string|array|null $condition): void
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
            $condition = Conditions::createCondition($condition);
        }
        $condition->forProjectConfig = false;

        $this->_orderCondition = $condition;
    }

    public function getCustomerCondition(): ElementConditionInterface
    {
        $condition = $this->_customerCondition ?? new DiscountCustomerCondition();
        $condition->mainTag = 'div';
        $condition->name = 'customerCondition';

        return $condition;
    }

    public function hasCustomerCondition(): bool
    {
        if ($this->_customerCondition === null) {
            return false;
        }

        return !empty($this->getCustomerCondition()->getConditionRules());
    }

    public function setCustomerCondition(ElementConditionInterface|string|array|null $condition): void
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
            $condition = Conditions::createCondition($condition);
        }
        $condition->forProjectConfig = false;

        $this->_customerCondition = $condition;
    }

    public function getShippingAddressCondition(): ElementConditionInterface
    {
        $condition = $this->_shippingAddressCondition ?? new DiscountAddressCondition();
        $condition->mainTag = 'div';
        $condition->id = 'shippingAddressCondition';
        $condition->name = 'shippingAddressCondition';

        return $condition;
    }

    public function hasShippingAddressCondition(): bool
    {
        if ($this->_shippingAddressCondition === null) {
            return false;
        }

        return !empty($this->getShippingAddressCondition()->getConditionRules());
    }

    public function setShippingAddressCondition(ElementConditionInterface|string|array|null $condition): void
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
            $condition = Conditions::createCondition($condition);
        }
        $condition->forProjectConfig = false;

        $this->_shippingAddressCondition = $condition;
    }

    public function getBillingAddressCondition(): ElementConditionInterface
    {
        $condition = $this->_billingAddressCondition ?? new DiscountAddressCondition();
        $condition->mainTag = 'div';
        $condition->id = 'billingAddressCondition';
        $condition->name = 'billingAddressCondition';

        return $condition;
    }

    public function hasBillingAddressCondition(): bool
    {
        if ($this->_billingAddressCondition === null) {
            return false;
        }

        return !empty($this->getBillingAddressCondition()->getConditionRules());
    }

    public function setBillingAddressCondition(ElementConditionInterface|string|array|null $condition): void
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
            $condition = Conditions::createCondition($condition);
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
     * @param int[] $categoryIds
     */
    public function setCategoryIds(array $categoryIds): void
    {
        $this->_categoryIds = array_unique($categoryIds);
    }

    /**
     * @param int[] $purchasableIds
     */
    public function setPurchasableIds(array $purchasableIds): void
    {
        $this->_purchasableIds = array_unique($purchasableIds);
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
     * @return Coupon[]
     */
    public function getCoupons(): array
    {
        if ($this->_coupons === null && $this->id) {
            /** @phpstan-ignore-next-line */
            $this->_coupons = Plugin::getInstance()->getCoupons()->getCouponsByDiscountId($this->id);
        }

        return $this->_coupons ?? [];
    }

    /**
     * @param Coupon[] $coupons
     */
    public function setCoupons(array $coupons): void
    {
        $this->_coupons = $coupons;
    }

    public function getPercentDiscountAsPercent(): string
    {
        return I18N::getFormatter()->asPercent(-$this->percentDiscount);
    }

    #[\Override]
    public function getRules(): array
    {
        return [
            'name' => ['required', 'string'],
            'couponFormat' => ['required', 'string', 'min:1', 'max:20'],
            'perUserLimit' => ['numeric'],
            'perEmailLimit' => ['numeric'],
            'totalDiscountUseLimit' => ['numeric'],
            'totalDiscountUses' => ['numeric'],
            'purchaseQty' => ['numeric'],
            'maxPurchaseQty' => ['numeric'],
            'baseDiscount' => ['numeric'],
            'perItemDiscount' => ['numeric'],
            'percentDiscount' => ['numeric'],
            'categoryRelationshipType' => [Rule::in([
                DiscountRecord::CATEGORY_RELATIONSHIP_TYPE_SOURCE,
                DiscountRecord::CATEGORY_RELATIONSHIP_TYPE_TARGET,
                DiscountRecord::CATEGORY_RELATIONSHIP_TYPE_BOTH,
            ])],
            'appliedTo' => [Rule::in([
                DiscountRecord::APPLIED_TO_MATCHING_LINE_ITEMS,
                DiscountRecord::APPLIED_TO_ALL_LINE_ITEMS,
            ])],
            'hasFreeShippingForOrder' => [
                function($attribute, $value, \Closure $fail) {
                    if ($this->hasFreeShippingForMatchingItems && $this->hasFreeShippingForOrder) {
                        $fail(t('Free shipping can only be for whole order or matching items, not both.', category: 'commerce'));
                    }
                },
            ],
            'orderConditionFormula' => [
                'nullable',
                'string',
                'max:65000',
                function($attribute, $value, \Closure $fail) {
                    if (!$value) {
                        return;
                    }
                    /** @var Order $order */
                    $order = Order::find()->one() ?? new Order();

                    $fieldsAsArray = $order->getSerializedFieldValues();
                    $orderAsArray = $order->toArray([], ['lineItems.snapshot', 'shippingAddress', 'billingAddress']);
                    $orderConditionParams = [
                        'order' => array_merge($orderAsArray, $fieldsAsArray),
                    ];

                    /** @phpstan-ignore-next-line */
                    if (!Plugin::getInstance()->getFormulas()->validateConditionSyntax($value, $orderConditionParams)) {
                        $fail(t('Invalid order condition syntax.', category: 'commerce'));
                    }
                },
            ],
        ];
    }

    private function _loadPurchasableRelations(): void
    {
        $purchasableIds = DB::table(Table::DISCOUNTS . ' as discounts')
            ->leftJoin(Table::DISCOUNT_PURCHASABLES . ' as dp', 'dp.discountId', '=', 'discounts.id')
            ->where('discounts.id', $this->id)
            ->pluck('dp.purchasableId')
            ->all();

        $this->setPurchasableIds($purchasableIds);
    }

    private function _loadCategoryRelations(): void
    {
        $categoryIds = DB::table(Table::DISCOUNTS . ' as discounts')
            ->leftJoin(Table::DISCOUNT_CATEGORIES . ' as dpt', 'dpt.discountId', '=', 'discounts.id')
            ->where('discounts.id', $this->id)
            ->pluck('dpt.categoryId')
            ->all();

        $this->setCategoryIds($categoryIds);
    }
}
