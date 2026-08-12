<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Services;

use craft\commerce\adjusters\Discount as DiscountAdjuster;
use craft\commerce\base\PurchasableInterface;
use craft\commerce\elements\Order;
use craft\commerce\Plugin;
use craft\commerce\records\CustomerDiscountUse;
use craft\commerce\records\Discount as DiscountRecord;
use craft\commerce\records\DiscountCategory as DiscountCategoryRecord;
use craft\commerce\records\DiscountPurchasable as DiscountPurchasableRecord;
use craft\commerce\records\EmailDiscountUse as EmailDiscountUseRecord;
use craft\elements\Category;
use CraftCms\Cms\Entry\Elements\Entry;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Order\Enums\OrderNoticeType;
use CraftCms\Commerce\Order\Models\OrderNotice;
use CraftCms\Commerce\Promotion\Events\DiscountEvent;
use CraftCms\Commerce\Promotion\Events\MatchLineItemEvent;
use CraftCms\Commerce\Promotion\Events\MatchOrderEvent;
use CraftCms\Commerce\Promotion\Models\Coupon;
use CraftCms\Commerce\Promotion\Models\Discount;
use DateTime;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use function CraftCms\Cms\t;

#[Singleton]
class Discounts
{
    /** @var array<int, Collection<int, Discount>>|null */
    private ?array $allDiscounts = null;

    /** @var array<string, Discount[]>|null */
    private ?array $activeDiscountsByKey = null;

    /** @var array<string, bool>|null */
    private ?array $matchingLineItemCategoryCondition = null;

    public function getDiscountById(int $id, ?int $storeId = null): ?Discount
    {
        // TODO: migrate to app(Stores::class)->getCurrentStore()->id once Stores service migrated
        /** @phpstan-ignore-next-line */
        $storeId ??= Plugin::getInstance()->getStores()->getCurrentStore()->id;

        $rows = $this->query()
            ->where('discounts.id', $id)
            ->where('storeId', $storeId)
            ->get()
            ->all();

        if (!$rows) {
            return null;
        }

        $populated = $this->populateDiscounts($rows);
        return reset($populated) ?: null;
    }

    /**
     * @return Collection<int, Discount>
     */
    public function getAllDiscounts(?int $storeId = null): Collection
    {
        // TODO: migrate to app(Stores::class)->getCurrentStore()->id once Stores service migrated
        /** @phpstan-ignore-next-line */
        $storeId ??= Plugin::getInstance()->getStores()->getCurrentStore()->id;

        if ($this->allDiscounts === null || !isset($this->allDiscounts[$storeId])) {
            $rows = $this->query()->where('storeId', $storeId)->get()->all();

            $this->allDiscounts ??= [];
            $this->allDiscounts[$storeId] = collect($rows ? $this->populateDiscounts($rows) : []);
        }

        return $this->allDiscounts[$storeId];
    }

    /**
     * Get all currently active discounts, pre-filtered for the given order.
     * TODO: update Order type hint when Order element is migrated to src/
     *
     * @return Discount[]
     */
    public function getAllActiveDiscounts(?Order $order = null): array
    {
        $purchasableIds = [];
        if ($order) {
            // TODO: update when LineItem is migrated
            $purchasableIds = collect($order->getLineItems())->pluck('purchasableId')->unique()->all();
        }

        if ($order && $order->dateOrdered) {
            $date = $order->dateOrdered;
        } else {
            $date = new DateTime();
            $date->setTime((int) $date->format('H'), (int) (round($date->format('i') / 1) * 1));
        }

        // TODO: migrate to app(Stores::class)->getCurrentStore() once Stores service migrated
        /** @phpstan-ignore-next-line */
        $store = $order ? $order->getStore() : Plugin::getInstance()->getStores()->getCurrentStore();

        $couponKey = ($order && $order->couponCode) ? $order->couponCode : '*';
        $dateKey = $date->format('c');
        $storeKey = $order ? $order->getStore()->id : '*';
        $purchasablesKey = !empty($purchasableIds) ? md5(serialize($purchasableIds)) : '*';
        $itemSubtotalKey = $order ? $order->getItemSubtotal() : '*';
        $orderTotalQtyKey = $order ? $order->getTotalQty() : '*';
        $orderEmailKey = ($order && $order->getEmail()) ? $order->getEmail() : '*';

        $cacheKeyMd5 = md5(implode(':', [$couponKey, $dateKey, $storeKey, $purchasablesKey, $itemSubtotalKey, $orderTotalQtyKey, $orderEmailKey]));

        if (isset($this->activeDiscountsByKey[$cacheKeyMd5])) {
            return $this->activeDiscountsByKey[$cacheKeyMd5];
        }

        $isPgsql = DB::connection()->getDriverName() === 'pgsql';

        $discountQuery = $this->query()
            ->where('enabled', true)
            ->where('storeId', $store->id)
            ->where(function($q) use ($date) {
                $q->whereNull('dateFrom')->orWhere('dateFrom', '<=', $date->format('Y-m-d H:i:s'));
            })
            ->where(function($q) use ($date) {
                $q->whereNull('dateTo')->orWhere('dateTo', '>=', $date->format('Y-m-d H:i:s'));
            })
            ->where(function($q) {
                $q->where('totalDiscountUseLimit', 0)
                  ->orWhereColumn('totalDiscountUses', '<', 'totalDiscountUseLimit');
            });

        if ($order) {
            if ($order->getEmail()) {
                $emailUsesSubQuery = DB::table(Table::EMAIL_DISCOUNTUSES . ' as edu')
                    ->selectRaw('COALESCE(SUM(edu.uses), 0)')
                    ->whereColumn('edu.discountId', 'discounts.id')
                    ->where('edu.email', $order->getEmail());

                $discountQuery->where(function($q) use ($emailUsesSubQuery) {
                    $q->where('perEmailLimit', 0)
                      ->orWhere(function($q2) use ($emailUsesSubQuery) {
                          $q2->where('perEmailLimit', '>', 0)
                             ->where('perEmailLimit', '>', $emailUsesSubQuery);
                      });
                });
            } else {
                $discountQuery->where('perEmailLimit', 0);
            }

            $discountQuery->where(function($q) use ($order) {
                $q->where('purchaseTotal', 0)
                  ->orWhere(function($q2) use ($order) {
                      $q2->where('allPurchasables', true)->where('allCategories', true)->where('purchaseTotal', '<=', $order->getItemSubtotal());
                  })
                  ->orWhere('allPurchasables', false)
                  ->orWhere('allCategories', false);
            });

            $discountQuery->where(function($q) use ($order) {
                $q->where(function($q2) {
                    $q2->where('purchaseQty', 0)->where('maxPurchaseQty', 0);
                })
                  ->orWhere(function($q2) use ($order) {
                      $q2->where('allPurchasables', true)->where('allCategories', true)
                         ->where('purchaseQty', '>', 0)->where('maxPurchaseQty', 0)->where('purchaseQty', '<=', $order->getTotalQty());
                  })
                  ->orWhere(function($q2) use ($order) {
                      $q2->where('allPurchasables', true)->where('allCategories', true)
                         ->where('maxPurchaseQty', '>', 0)->where('purchaseQty', 0)->where('maxPurchaseQty', '>=', $order->getTotalQty());
                  })
                  ->orWhere(function($q2) use ($order) {
                      $q2->where('allPurchasables', true)->where('allCategories', true)
                         ->where('maxPurchaseQty', '>', 0)->where('purchaseQty', '>', 0)
                         ->where('purchaseQty', '<=', $order->getTotalQty())->where('maxPurchaseQty', '>=', $order->getTotalQty());
                  })
                  ->orWhere('allPurchasables', false)
                  ->orWhere('allCategories', false);
            });
        }

        if ($order && $order->couponCode) {
            $code = $order->couponCode;
            $discountQuery->where(function($q) use ($code, $isPgsql) {
                $q->whereExists(function($sub) use ($code, $isPgsql) {
                    $sub->from(Table::COUPONS)
                        ->whereColumn('discountId', 'discounts.id')
                        ->where('requireCouponCode', true)
                        ->when($isPgsql,
                            fn($q) => $q->whereRaw('LOWER(code) = LOWER(?)', [$code]),
                            fn($q) => $q->where('code', $code)
                        )
                        ->where(function($q2) {
                            $q2->whereNull('maxUses')->orWhereColumn('uses', '<', 'maxUses');
                        });
                })->orWhere('requireCouponCode', false);
            });
        } elseif ($order && !$order->couponCode) {
            $discountQuery->where('requireCouponCode', false);
        }

        if ($order && !empty($purchasableIds)) {
            $discountQuery->where(function($q) use ($purchasableIds) {
                $q->where('allPurchasables', true)
                  ->orWhereExists(function($sub) use ($purchasableIds) {
                      $sub->from(Table::DISCOUNT_PURCHASABLES . ' as subdp')
                          ->whereColumn('subdp.discountId', 'discounts.id')
                          ->whereIn('subdp.purchasableId', $purchasableIds);
                  });
            });
        }

        $rows = $discountQuery->get()->all();
        $discounts = $this->populateDiscounts($rows);
        $this->activeDiscountsByKey[$cacheKeyMd5] = $discounts;

        return $discounts;
    }

    /**
     * TODO: update Order type hint when Order element migrated to src/
     */
    public function orderCouponAvailable(Order $order, ?string &$explanation = null): bool
    {
        $discount = $this->getDiscountByCode($order->couponCode, $order->storeId);

        if (!$discount) {
            $explanation = t('Coupon not valid.', category: 'commerce');
            return false;
        }

        if (!$discount->requireCouponCode) {
            $explanation = t('Coupon not valid.', category: 'commerce');
            return false;
        }

        if (!$this->isDiscountCouponCodeValid($order, $discount)) {
            $explanation = t('Coupon not valid.', category: 'commerce');
            return false;
        }

        if ($discount->hasOrderCondition() && !$discount->getOrderCondition()->matchElement($order)) {
            $explanation = t('Coupon can not apply discount to this order.', category: 'commerce');
            return false;
        }

        if ($discount->hasCustomerCondition() && (!$order->getCustomer() || !$discount->getCustomerCondition()->matchElement($order->getCustomer()))) {
            $explanation = t('Coupon can not apply discount to this order due to customer mismatch.', category: 'commerce');
            return false;
        }

        if ($discount->hasShippingAddressCondition() && (!$order->getShippingAddress() || !$discount->getShippingAddressCondition()->matchElement($order->getShippingAddress()))) {
            $explanation = t('Coupon can not apply discount to this order due to address mismatch.', category: 'commerce');
            return false;
        }

        if ($discount->hasBillingAddressCondition() && (!$order->getBillingAddress() || !$discount->getBillingAddressCondition()->matchElement($order->getBillingAddress()))) {
            $explanation = t('Coupon can not apply discount to this order due to address mismatch.', category: 'commerce');
            return false;
        }

        if (!$this->isDiscountConditionFormulaValid($order, $discount)) {
            $explanation = t('Discount is not allowed for the order', category: 'commerce');
            return false;
        }

        if (!$this->isDiscountDateValid($order, $discount)) {
            $explanation = t('Discount is out of date.', category: 'commerce');
            return false;
        }

        if (!$this->isDiscountTotalUseLimitValid($discount)) {
            $explanation = t('Discount use has reached its limit.', category: 'commerce');
            return false;
        }

        if (!$this->isDiscountPerUserUsageValid($discount, $order->getCustomer())) {
            $explanation = t('This coupon is for registered users and limited to {limit} uses.', ['limit' => $discount->perUserLimit], category: 'commerce');
            return false;
        }

        if (!$this->isDiscountEmailRequirementValid($discount, $order)) {
            $explanation = t('This coupon requires an email address.', category: 'commerce');
            return false;
        }

        if (!$this->isDiscountPerEmailLimitValid($discount, $order)) {
            $explanation = t('This coupon is limited to {limit} uses.', ['limit' => $discount->perEmailLimit], category: 'commerce');
            return false;
        }

        return true;
    }

    public function getDiscountByCode(?string $code, ?int $storeId = null): ?Discount
    {
        if ($code === null || $code === '') {
            return null;
        }

        // TODO: migrate to app(Stores::class)->getCurrentStore()->id once Stores service migrated
        /** @phpstan-ignore-next-line */
        $storeId ??= Plugin::getInstance()->getStores()->getCurrentStore()->id;

        $isPgsql = DB::connection()->getDriverName() === 'pgsql';

        $query = $this->query()
            ->where('storeId', $storeId)
            ->join(Table::COUPONS . ' as coupons', 'coupons.discountId', '=', 'discounts.id');

        if ($isPgsql) {
            $query->whereRaw('LOWER(coupons.code) = LOWER(?)', [$code]);
        } else {
            $query->where('coupons.code', $code);
        }

        $rows = $query->get()->all();

        if (!$rows) {
            return null;
        }

        $discounts = $this->populateDiscounts($rows);

        foreach ($discounts as $discount) {
            if (!$discount->enabled) {
                continue;
            }
            foreach ($discount->getCoupons() as $coupon) {
                if (strcasecmp((string) $coupon->code, $code) === 0) {
                    return $discount;
                }
            }
        }

        return null;
    }

    /**
     * TODO: update PurchasableInterface type hint when migrated to src/
     *
     * @return Discount[]
     */
    public function getDiscountsRelatedToPurchasable(PurchasableInterface $purchasable): array
    {
        $discounts = [];

        if ($purchasable->getId()) {
            foreach ($this->getAllDiscounts($purchasable->getStoreId()) as $discount) {
                $purchasableIds = $discount->getPurchasableIds();
                $id = $purchasable->getId();

                // TODO: update Category/Entry element calls when migrated
                $relatedTo = [$discount->categoryRelationshipType => $purchasable->getPromotionRelationSource()];
                $categoryIds = $discount->getCategoryIds();
                /** @phpstan-ignore-next-line */
                $relatedCategories = Category::find()->id($categoryIds)->relatedTo($relatedTo)->ids();
                /** @phpstan-ignore-next-line */
                $relatedEntries = Entry::find()->id($categoryIds)->relatedTo($relatedTo)->ids();
                $relatedCategoriesOrEntries = array_merge($relatedCategories, $relatedEntries);

                if (in_array($id, $purchasableIds, false) || !empty($relatedCategoriesOrEntries)) {
                    $discounts[$discount->id] = $discount;
                }
            }
        }

        return $discounts;
    }

    /**
     * TODO: update Order/LineItem type hints when elements migrated to src/
     */
    public function matchLineItem(mixed $lineItem, Discount $discount, bool $matchOrder = false): bool
    {
        if ($matchOrder && !$this->matchOrder($lineItem->order, $discount)) {
            return false;
        }

        // TODO: update to new site API once migrated
        /** @phpstan-ignore-next-line */
        $siteId = $lineItem->order->orderSiteId ?? \Craft::$app->getSites()->getCurrentSite()->id;

        if ($lineItem->getOnPromotion() && $discount->excludeOnPromotion) {
            return false;
        }

        if (!$lineItem->getIsPromotable()) {
            return false;
        }

        // TODO: update LineItemType enum reference once migrated
        /** @phpstan-ignore-next-line */
        if ($lineItem->type === \craft\commerce\enums\LineItemType::Purchasable) {
            /** @phpstan-ignore-next-line */
            $purchasable = $lineItem->getPurchasable();

            if (!$discount->allPurchasables && !in_array($purchasable->id, $discount->getPurchasableIds(), false)) {
                return false;
            }

            if (!$discount->allCategories) {
                $key = 'relationshipType:' . $discount->categoryRelationshipType . ':purchasableId:' . $purchasable->getId() . ':categoryIds:' . implode('|', $discount->getCategoryIds());

                if (!isset($this->matchingLineItemCategoryCondition[$key])) {
                    $relatedTo = [$discount->categoryRelationshipType => $purchasable->getPromotionRelationSource()];

                    // TODO: update Category/Entry element calls when migrated
                    /** @phpstan-ignore-next-line */
                    $relatedEntries = Entry::find()->siteId($siteId)->relatedTo($relatedTo)->ids();
                    /** @phpstan-ignore-next-line */
                    $relatedCategories = Category::find()->siteId($siteId)->relatedTo($relatedTo)->ids();

                    $relatedCategoriesOrEntries = array_merge($relatedEntries, $relatedCategories);
                    $purchasableIsRelated = (bool) array_intersect($relatedCategoriesOrEntries, $discount->getCategoryIds());

                    $this->matchingLineItemCategoryCondition[$key] = $purchasableIsRelated;
                    if (!$purchasableIsRelated) {
                        return false;
                    }
                } elseif ($this->matchingLineItemCategoryCondition[$key] === false) {
                    return false;
                }
            }
        }

        $event = new MatchLineItemEvent(lineItem: $lineItem, discount: $discount);
        event($event);

        return $event->isValid;
    }

    /**
     * TODO: update Order type hint when Order element migrated to src/
     */
    public function matchOrder(Order $order, Discount $discount): bool
    {
        if (!$discount->enabled) {
            return false;
        }

        if ($discount->hasOrderCondition() && !$discount->getOrderCondition()->matchElement($order)) {
            return false;
        }

        if ($discount->hasCustomerCondition() && (!$order->getCustomer() || !$discount->getCustomerCondition()->matchElement($order->getCustomer()))) {
            return false;
        }

        if ($discount->hasShippingAddressCondition() && (!$order->getShippingAddress() || !$discount->getShippingAddressCondition()->matchElement($order->getShippingAddress()))) {
            return false;
        }

        if ($discount->hasBillingAddressCondition() && (!$order->getBillingAddress() || !$discount->getBillingAddressCondition()->matchElement($order->getBillingAddress()))) {
            return false;
        }

        if (!$this->isDiscountCouponCodeValid($order, $discount)) {
            return false;
        }

        if (!$this->isDiscountDateValid($order, $discount)) {
            return false;
        }

        if (!$this->isDiscountTotalUseLimitValid($discount)) {
            return false;
        }

        if (!$this->isDiscountPerUserUsageValid($discount, $order->getCustomer())) {
            return false;
        }

        if (!$this->isDiscountEmailRequirementValid($discount, $order)) {
            return false;
        }

        if (!$this->isDiscountPerEmailLimitValid($discount, $order)) {
            return false;
        }

        if (!$this->isDiscountConditionFormulaValid($order, $discount)) {
            return false;
        }

        $allItemsMatch = ($discount->allPurchasables && $discount->allCategories);

        if ($allItemsMatch && $discount->purchaseTotal > 0 && $order->getItemSubtotal() < $discount->purchaseTotal) {
            return false;
        }

        if ($allItemsMatch && $discount->purchaseQty > 0 && $order->getTotalQty() < $discount->purchaseQty) {
            return false;
        }

        if ($allItemsMatch && $discount->maxPurchaseQty > 0 && $order->getTotalQty() > $discount->maxPurchaseQty) {
            return false;
        }

        if (!$discount->allPurchasables || !$discount->allCategories) {
            $matchingItems = collect($order->getLineItems())
                ->filter(fn($item) => $this->matchLineItem($item, $discount));

            if ($matchingItems->isEmpty()) {
                return false;
            }

            $matchingQty = $matchingItems->sum('qty');
            $matchingTotal = $matchingItems->sum('subtotal');

            if ($discount->purchaseTotal > 0 && $matchingTotal < $discount->purchaseTotal) {
                return false;
            }
            if ($discount->purchaseQty > 0 && $matchingQty < $discount->purchaseQty) {
                return false;
            }
            if ($discount->maxPurchaseQty > 0 && $matchingQty > $discount->maxPurchaseQty) {
                return false;
            }
        }

        $event = new MatchOrderEvent(order: $order, discount: $discount);
        event($event);

        return $event->isValid;
    }

    public function saveDiscount(Discount $model, bool $runValidation = true): bool
    {
        $isNew = !$model->id;

        if ($model->id) {
            /** @phpstan-ignore-next-line */
            $record = DiscountRecord::findOne($model->id);

            if (!$record) {
                throw new \RuntimeException(t('No discount exists with the ID "{id}"', ['id' => $model->id], category: 'commerce'));
            }
        } else {
            $record = new DiscountRecord();
        }

        if (!$isNew) {
            // TODO: update to new date helper once migrated
            /** @phpstan-ignore-next-line */
            $model->dateCreated = \CraftCms\Cms\Support\DateTimeHelper::toDateTime($record->dateCreated);
            /** @phpstan-ignore-next-line */
            $model->dateUpdated = \CraftCms\Cms\Support\DateTimeHelper::toDateTime($record->dateUpdated);
        }

        $ev = new DiscountEvent(discount: $model, isNew: $isNew);
        event($ev);

        if ($runValidation && !$model->validate()) {
            Log::info('Discount not saved due to validation error.');
            return false;
        }

        /** @phpstan-ignore-next-line */
        $record->storeId = $model->storeId;
        /** @phpstan-ignore-next-line */
        $record->name = $model->name;
        /** @phpstan-ignore-next-line */
        $record->description = $model->description;
        /** @phpstan-ignore-next-line */
        $record->dateFrom = $model->dateFrom;
        /** @phpstan-ignore-next-line */
        $record->dateTo = $model->dateTo;
        /** @phpstan-ignore-next-line */
        $record->enabled = $model->enabled;
        /** @phpstan-ignore-next-line */
        $record->stopProcessing = $model->stopProcessing;
        /** @phpstan-ignore-next-line */
        $record->orderCondition = $model->hasOrderCondition() ? $model->getOrderCondition()->getConfig() : null;
        /** @phpstan-ignore-next-line */
        $record->customerCondition = $model->hasCustomerCondition() ? $model->getCustomerCondition()->getConfig() : null;
        /** @phpstan-ignore-next-line */
        $record->shippingAddressCondition = $model->hasShippingAddressCondition() ? $model->getShippingAddressCondition()->getConfig() : null;
        /** @phpstan-ignore-next-line */
        $record->billingAddressCondition = $model->hasBillingAddressCondition() ? $model->getBillingAddressCondition()->getConfig() : null;
        /** @phpstan-ignore-next-line */
        $record->requireCouponCode = $model->requireCouponCode;
        /** @phpstan-ignore-next-line */
        $record->orderConditionFormula = $model->orderConditionFormula;
        /** @phpstan-ignore-next-line */
        $record->purchaseQty = $model->purchaseQty;
        /** @phpstan-ignore-next-line */
        $record->maxPurchaseQty = $model->maxPurchaseQty;
        /** @phpstan-ignore-next-line */
        $record->baseDiscount = $model->baseDiscount;
        /** @phpstan-ignore-next-line */
        $record->purchaseTotal = $model->purchaseTotal;
        /** @phpstan-ignore-next-line */
        $record->perItemDiscount = $model->perItemDiscount;
        /** @phpstan-ignore-next-line */
        $record->percentDiscount = $model->percentDiscount;
        /** @phpstan-ignore-next-line */
        $record->percentageOffSubject = $model->percentageOffSubject;
        /** @phpstan-ignore-next-line */
        $record->hasFreeShippingForMatchingItems = $model->hasFreeShippingForMatchingItems;
        /** @phpstan-ignore-next-line */
        $record->hasFreeShippingForOrder = $model->hasFreeShippingForOrder;
        /** @phpstan-ignore-next-line */
        $record->excludeOnPromotion = $model->excludeOnPromotion;
        /** @phpstan-ignore-next-line */
        $record->perUserLimit = $model->perUserLimit;
        /** @phpstan-ignore-next-line */
        $record->perEmailLimit = $model->perEmailLimit;
        /** @phpstan-ignore-next-line */
        $record->totalDiscountUseLimit = $model->totalDiscountUseLimit;
        /** @phpstan-ignore-next-line */
        $record->ignorePromotions = $model->ignorePromotions;
        /** @phpstan-ignore-next-line */
        $record->appliedTo = $model->appliedTo;
        /** @phpstan-ignore-next-line */
        $record->purchasableIds = $model->getPurchasableIds();
        /** @phpstan-ignore-next-line */
        $record->categoryIds = $model->getCategoryIds();
        /** @phpstan-ignore-next-line */
        $record->sortOrder = $record->sortOrder ?: 0;
        /** @phpstan-ignore-next-line */
        $record->couponFormat = $model->couponFormat;
        /** @phpstan-ignore-next-line */
        $record->categoryRelationshipType = $model->categoryRelationshipType;

        if ($record->allCategories = $model->allCategories) {
            $model->setCategoryIds([]);
            /** @phpstan-ignore-next-line */
            $record->categoryIds = null;
        }
        if ($record->allPurchasables = $model->allPurchasables) {
            $model->setPurchasableIds([]);
            /** @phpstan-ignore-next-line */
            $record->purchasableIds = null;
        }

        DB::beginTransaction();

        try {
            /** @phpstan-ignore-next-line */
            $record->save(false);
            $model->id = $record->id;

            // TODO: update to new date helper once migrated
            /** @phpstan-ignore-next-line */
            $model->dateCreated = \CraftCms\Cms\Support\DateTimeHelper::toDateTime($record->dateCreated);
            /** @phpstan-ignore-next-line */
            $model->dateUpdated = \CraftCms\Cms\Support\DateTimeHelper::toDateTime($record->dateUpdated);

            /** @phpstan-ignore-next-line */
            DiscountPurchasableRecord::deleteAll(['discountId' => $model->id]);
            /** @phpstan-ignore-next-line */
            DiscountCategoryRecord::deleteAll(['discountId' => $model->id]);

            // TODO: update getStore()->getSites() when Store/Sites migrated
            /** @phpstan-ignore-next-line */
            $siteIds = $model->getStore()->getSites()->pluck('id')->all();

            foreach ($model->getCategoryIds() as $categoryId) {
                $relation = new DiscountCategoryRecord();
                /** @phpstan-ignore-next-line */
                $relation->categoryId = $categoryId;
                /** @phpstan-ignore-next-line */
                $relation->discountId = $model->id;
                /** @phpstan-ignore-next-line */
                $relation->save(false);
            }

            foreach ($model->getPurchasableIds() as $purchasableId) {
                $relation = new DiscountPurchasableRecord();
                // TODO: update to new Elements API once migrated
                /** @phpstan-ignore-next-line */
                $element = \Craft::$app->getElements()->getElementById($purchasableId, siteId: $siteIds);
                /** @phpstan-ignore-next-line */
                $relation->purchasableType = $element::class;
                /** @phpstan-ignore-next-line */
                $relation->purchasableId = $purchasableId;
                /** @phpstan-ignore-next-line */
                $relation->discountId = $model->id;
                /** @phpstan-ignore-next-line */
                $relation->save(false);
            }

            app(\CraftCms\Commerce\Services\Coupons::class)->saveDiscountCoupons($model);

            DB::commit();

            $this->ensureSortOrder($model->storeId);

            $afterEv = new DiscountEvent(discount: $model, isNew: $isNew);
            event($afterEv);

            $this->clearCaches();

            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function deleteDiscountById(int $id): bool
    {
        /** @phpstan-ignore-next-line */
        $discountRecord = DiscountRecord::findOne($id);

        if (!$discountRecord) {
            return false;
        }

        /** @phpstan-ignore-next-line */
        $discount = $this->getDiscountById($id, $discountRecord->storeId);
        /** @phpstan-ignore-next-line */
        $storeId = $discount->storeId;

        /** @phpstan-ignore-next-line */
        $result = (bool) $discountRecord->delete();

        if ($result) {
            $this->ensureSortOrder($storeId);

            $ev = new DiscountEvent(discount: $discount, isNew: false);
            event($ev);
        }

        $this->clearCaches();

        return $result;
    }

    public function ensureSortOrder(?int $storeId = null): void
    {
        // TODO: migrate to app(Stores::class)->getCurrentStore()->id once Stores service migrated
        /** @phpstan-ignore-next-line */
        $storeId ??= Plugin::getInstance()->getStores()->getCurrentStore()->id;

        $table = Table::DISCOUNTS;
        $isPgsql = DB::connection()->getDriverName() === 'pgsql';

        if ($isPgsql) {
            DB::statement("
                UPDATE {$table} a
                SET sortOrder = b.rownumber
                FROM (
                    SELECT id, sortOrder, ROW_NUMBER() OVER (ORDER BY sortOrder ASC, id ASC) as rownumber
                    FROM {$table}
                    WHERE storeId = {$storeId}
                    ORDER BY sortOrder ASC, id ASC
                ) b
                WHERE a.id = b.id
            ");
        } else {
            DB::statement("
                UPDATE {$table} a
                JOIN (
                    SELECT id, sortOrder, (@ROW_NUMBER := @ROW_NUMBER + 1) as rownumber
                    FROM {$table},
                    (SELECT @ROW_NUMBER := 0) AS X
                    WHERE storeId = {$storeId}
                    ORDER BY sortOrder ASC, id ASC
                ) b ON a.id = b.id
                SET a.sortOrder = b.rownumber
            ");
        }

        $this->clearCaches();
    }

    public function clearCustomerUsageHistoryById(int $id): void
    {
        DB::table(Table::CUSTOMER_DISCOUNTUSES)->where('discountId', $id)->delete();
        $this->clearCaches();
    }

    public function clearEmailUsageHistoryById(int $id): void
    {
        DB::table(Table::EMAIL_DISCOUNTUSES)->where('discountId', $id)->delete();
        $this->clearCaches();
    }

    public function clearDiscountUsesById(int $id): void
    {
        DB::table(Table::DISCOUNTS)->where('id', $id)->update(['totalDiscountUses' => 0]);
        $this->clearCaches();
    }

    public function reorderDiscounts(array $ids): bool
    {
        foreach ($ids as $sortOrder => $id) {
            DB::table(Table::DISCOUNTS)->where('id', $id)->update(['sortOrder' => $sortOrder + 1]);
        }

        $this->clearCaches();

        return true;
    }

    public function appendCouponCode(int $discountId, string|Coupon $coupon, ?int $maxUses = null): bool
    {
        $discount = $this->getDiscountById($discountId);

        if (!$discount) {
            throw new \RuntimeException('No discount exists with the ID "' . $discountId . '"');
        }

        if (!$discount->requireCouponCode) {
            throw new \RuntimeException('The discount with ID "' . $discountId . '" does not require a coupon code');
        }

        if (is_string($coupon)) {
            $couponModel = new Coupon();
            $couponModel->discountId = $discountId;
            $couponModel->code = $coupon;
            $couponModel->maxUses = $maxUses;
            $couponModel->uses = 0;
        } else {
            $couponModel = $coupon;
            $couponModel->discountId = $discountId;
        }

        $result = app(\CraftCms\Commerce\Services\Coupons::class)->saveCoupon($couponModel);

        if ($result) {
            $this->clearCaches();
        }

        return $result;
    }

    public function getEmailUsageStatsById(int $id): array
    {
        return (array) DB::table(Table::EMAIL_DISCOUNTUSES)
            ->selectRaw('COALESCE(SUM(uses), 0) as uses, COUNT(email) as emails')
            ->where('discountId', $id)
            ->first();
    }

    public function getCustomerUsageStatsById(int $id): array
    {
        return (array) DB::table(Table::CUSTOMER_DISCOUNTUSES)
            ->selectRaw('COALESCE(SUM(uses), 0) as uses, COUNT(customerId) as users')
            ->where('discountId', $id)
            ->first();
    }

    /**
     * TODO: update Order type hint when Order element migrated to src/
     * TODO: update LineItem/OrderAdjustment references when migrated
     */
    public function orderCompleteHandler(Order $order): void
    {
        // TODO: update DiscountAdjuster::ADJUSTMENT_TYPE reference when migrated
        /** @phpstan-ignore-next-line */
        $discountAdjustments = $order->getAdjustmentsByType(DiscountAdjuster::ADJUSTMENT_TYPE);

        if (empty($discountAdjustments)) {
            return;
        }

        $discounts = [];
        foreach ($discountAdjustments as $discountAdjustment) {
            $snapshot = $discountAdjustment->sourceSnapshot ?? null;
            if (!$snapshot || !isset($snapshot['discountUseId']) || isset($discounts[$snapshot['discountUseId']])) {
                continue;
            }
            $discounts[$snapshot['discountUseId']] = $snapshot;
        }

        if (empty($discounts)) {
            return;
        }

        // TODO: update User/customer references when elements migrated
        $user = $order->getCustomer();

        foreach ($discounts as $discount) {
            if ($user && $user->getIsCredentialed()) {
                /** @phpstan-ignore-next-line */
                $userDiscountUseRecord = CustomerDiscountUse::find()->where(['customerId' => $user->id, 'discountId' => $discount['discountUseId']])->one();

                if (!$userDiscountUseRecord) {
                    /** @phpstan-ignore-next-line */
                    $userDiscountUseRecord = new CustomerDiscountUse();
                    /** @phpstan-ignore-next-line */
                    $userDiscountUseRecord->customerId = $user->id;
                    /** @phpstan-ignore-next-line */
                    $userDiscountUseRecord->discountId = $discount['discountUseId'];
                    /** @phpstan-ignore-next-line */
                    $userDiscountUseRecord->uses = 1;
                    /** @phpstan-ignore-next-line */
                    $userDiscountUseRecord->save();
                } else {
                    DB::table(Table::CUSTOMER_DISCOUNTUSES)
                        ->where('customerId', $order->getCustomerId())
                        ->where('discountId', $discount['discountUseId'])
                        ->increment('uses');
                }
            }

            /** @phpstan-ignore-next-line */
            $emailRecord = EmailDiscountUseRecord::find()->where(['email' => $order->getEmail(), 'discountId' => $discount['discountUseId']])->one();

            if (!$emailRecord) {
                $emailRecord = new EmailDiscountUseRecord();
                /** @phpstan-ignore-next-line */
                $emailRecord->email = $order->getEmail();
                /** @phpstan-ignore-next-line */
                $emailRecord->discountId = $discount['discountUseId'];
                /** @phpstan-ignore-next-line */
                $emailRecord->uses = 1;
                /** @phpstan-ignore-next-line */
                $emailRecord->save();
            } else {
                DB::table(Table::EMAIL_DISCOUNTUSES)
                    ->where('email', $order->getEmail())
                    ->where('discountId', $discount['discountUseId'])
                    ->increment('uses');
            }

            DB::table(Table::DISCOUNTS)->where('id', $discount['discountUseId'])->increment('totalDiscountUses');

            // Check if the total use limit has been exceeded (race condition / oversell scenario)
            if (($discount['totalDiscountUseLimit'] ?? 0) > 0) {
                $updatedUses = DB::table(Table::DISCOUNTS)
                    ->where('id', $discount['discountUseId'])
                    ->value('totalDiscountUses');
                if ($updatedUses > $discount['totalDiscountUseLimit']) {
                    $notice = new OrderNotice([
                        'type' => 'discountUsageExceeded',
                        'attribute' => 'couponCode',
                        'message' => t('The discount "{name}" has exceeded its total usage limit of {limit}.', [
                            'name' => $discount['name'] ?? $discount['discountUseId'],
                            'limit' => $discount['totalDiscountUseLimit'],
                        ], category: 'commerce'),
                        'noticeType' => OrderNoticeType::Admin,
                    ]);
                    $order->addNotice($notice);
                }
            }

            if ($order->couponCode) {
                // TODO: update CouponRecord reference when records are migrated to Eloquent
                /** @phpstan-ignore-next-line */
                $coupon = \craft\commerce\records\Coupon::findOne(['code' => $order->couponCode, 'discountId' => $discount['discountUseId']]);
                if ($coupon) {
                    DB::table(Table::COUPONS)->where('id', $coupon->id)->increment('uses');

                    // Check if the coupon's max uses has been exceeded
                    if ($coupon->maxUses !== null && ($coupon->uses + 1) > $coupon->maxUses) {
                        $notice = new OrderNotice([
                            'type' => 'couponUsageExceeded',
                            'attribute' => 'couponCode',
                            'message' => t('The coupon "{code}" has exceeded its usage limit of {limit}.', [
                                'code' => $order->couponCode,
                                'limit' => $coupon->maxUses,
                            ], category: 'commerce'),
                            'noticeType' => OrderNoticeType::Admin,
                        ]);
                        $order->addNotice($notice);
                    }
                }
            }

            $this->clearCaches();
        }
    }

    private function isDiscountCouponCodeValid(Order $order, Discount $discount): bool
    {
        if (!$discount->requireCouponCode) {
            return true;
        }

        $coupons = $discount->getCoupons();
        if (empty($coupons)) {
            return false;
        }

        foreach ($coupons as $coupon) {
            if (strcasecmp((string) $coupon->code, (string) $order->couponCode) === 0 && ($coupon->maxUses === null || $coupon->maxUses > $coupon->uses)) {
                return true;
            }
        }

        return false;
    }

    private function isDiscountDateValid(Order $order, Discount $discount): bool
    {
        $now = new DateTime();

        if ($order->isCompleted && $order->dateOrdered) {
            $now = $order->dateOrdered;
        }

        $from = $discount->dateFrom;
        $to = $discount->dateTo;

        return !(($from && $from > $now) || ($to && $to < $now));
    }

    private function isDiscountConditionFormulaValid(Order $order, Discount $discount): bool
    {
        if ($discount->orderConditionFormula) {
            $fieldsAsArray = $order->getSerializedFieldValues();
            $orderAsArray = $order->toArray([], ['lineItems.snapshot', 'shippingAddress', 'billingAddress']);
            $orderConditionParams = ['order' => array_merge($orderAsArray, $fieldsAsArray)];

            // TODO: migrate to app(Formulas::class)->evaluateCondition() once Formulas service migrated
            /** @phpstan-ignore-next-line */
            return Plugin::getInstance()->getFormulas()->evaluateCondition($discount->orderConditionFormula, $orderConditionParams, 'Evaluate Order Discount Condition Formula');
        }

        return true;
    }

    private function isDiscountTotalUseLimitValid(Discount $discount): bool
    {
        if ($discount->totalDiscountUseLimit > 0) {
            if ($discount->totalDiscountUses >= $discount->totalDiscountUseLimit) {
                return false;
            }
        }

        return true;
    }

    private function isDiscountPerUserUsageValid(Discount $discount, ?User $user): bool
    {
        if ($discount->perUserLimit > 0) {
            if (!$user) {
                return false;
            }

            // TODO: update to new request/user API once migrated
            /** @phpstan-ignore-next-line */
            if (\Craft::$app->getRequest()->getIsSiteRequest()) {
                /** @phpstan-ignore-next-line */
                $currentUser = \Craft::$app->getUser()->getIdentity();
                $isCustomerCurrentUser = ($currentUser && $currentUser->id == $user->id);

                if (!$isCustomerCurrentUser) {
                    return false;
                }
            }

            $usage = DB::table(Table::CUSTOMER_DISCOUNTUSES)
                ->where('customerId', $user->id)
                ->where('discountId', $discount->id)
                ->value('uses');

            if ($usage && $usage >= $discount->perUserLimit) {
                return false;
            }
        }

        return true;
    }

    private function isDiscountEmailRequirementValid(Discount $discount, Order $order): bool
    {
        if ($discount->perEmailLimit > 0 && !$order->getEmail()) {
            return false;
        }

        return true;
    }

    private function isDiscountPerEmailLimitValid(Discount $discount, Order $order): bool
    {
        if ($discount->perEmailLimit > 0 && $order->getEmail()) {
            $usage = DB::table(Table::EMAIL_DISCOUNTUSES)
                ->where('email', $order->getEmail())
                ->where('discountId', $discount->id)
                ->value('uses');

            if ($usage && $usage >= $discount->perEmailLimit) {
                return false;
            }
        }

        return true;
    }

    private function clearCaches(): void
    {
        $this->allDiscounts = null;
        $this->activeDiscountsByKey = null;
        $this->matchingLineItemCategoryCondition = null;
    }

    /**
     * @param object[] $rows
     * @return Discount[]
     */
    private function populateDiscounts(array $rows): array
    {
        $discounts = [];
        foreach ($rows as $row) {
            $data = (array) $row;
            $data['purchasableIds'] = !empty($data['purchasableIds']) ? json_decode($data['purchasableIds'], true) : [];
            $data['categoryIds'] = !empty($data['categoryIds']) ? json_decode($data['categoryIds'], true) : [];
            $data['orderCondition'] ??= '';
            $data['customerCondition'] ??= '';
            $data['billingAddressCondition'] ??= '';
            $data['shippingAddressCondition'] ??= '';

            $discounts[] = new Discount($data);
        }

        return $discounts;
    }

    private function query(): \Illuminate\Database\Query\Builder
    {
        return DB::table(Table::DISCOUNTS . ' as discounts')
            ->select([
                'discounts.allCategories',
                'discounts.allPurchasables',
                'discounts.appliedTo',
                'discounts.baseDiscount',
                'discounts.categoryRelationshipType',
                'discounts.couponFormat',
                'discounts.dateCreated',
                'discounts.dateFrom',
                'discounts.dateTo',
                'discounts.dateUpdated',
                'discounts.description',
                'discounts.enabled',
                'discounts.excludeOnPromotion',
                'discounts.hasFreeShippingForMatchingItems',
                'discounts.hasFreeShippingForOrder',
                'discounts.id',
                'discounts.ignorePromotions',
                'discounts.maxPurchaseQty',
                'discounts.name',
                'discounts.orderCondition',
                'discounts.orderConditionFormula',
                'discounts.percentageOffSubject',
                'discounts.percentDiscount',
                'discounts.perEmailLimit',
                'discounts.perItemDiscount',
                'discounts.perUserLimit',
                'discounts.purchaseTotal',
                'discounts.purchaseQty',
                'discounts.requireCouponCode',
                'discounts.sortOrder',
                'discounts.stopProcessing',
                'discounts.storeId',
                'discounts.totalDiscountUseLimit',
                'discounts.totalDiscountUses',
                'discounts.customerCondition',
                'discounts.shippingAddressCondition',
                'discounts.billingAddressCondition',
                'discounts.purchasableIds',
                'discounts.categoryIds',
            ])
            ->leftJoin(Table::DISCOUNT_PURCHASABLES . ' as dp', 'dp.discountId', '=', 'discounts.id')
            ->leftJoin(Table::DISCOUNT_CATEGORIES . ' as dpt', 'dpt.discountId', '=', 'discounts.id')
            ->groupBy('discounts.id')
            ->orderBy('discounts.sortOrder');
    }
}
