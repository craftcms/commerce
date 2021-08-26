<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\services;

use Craft;
use craft\commerce\adjusters\Discount as DiscountAdjuster;
use craft\commerce\base\PurchasableInterface;
use craft\commerce\db\Table;
use craft\commerce\elements\Order;
use craft\commerce\events\DiscountEvent;
use craft\commerce\events\MatchLineItemEvent;
use craft\commerce\events\MatchOrderEvent;
use craft\commerce\models\Discount;
use craft\commerce\models\LineItem;
use craft\commerce\models\OrderAdjustment;
use craft\commerce\Plugin;
use craft\commerce\records\CustomerDiscountUse as CustomerDiscountUseRecord;
use craft\commerce\records\Discount as DiscountRecord;
use craft\commerce\records\DiscountCategory as DiscountCategoryRecord;
use craft\commerce\records\DiscountPurchasable as DiscountPurchasableRecord;
use craft\commerce\records\DiscountUserGroup as DiscountUserGroupRecord;
use craft\commerce\records\EmailDiscountUse as EmailDiscountUseRecord;
use craft\db\Query;
use craft\elements\Category;
use craft\errors\DeprecationException;
use craft\helpers\ArrayHelper;
use craft\helpers\DateTimeHelper;
use craft\helpers\Db;
use DateTime;
use Twig\Error\LoaderError;
use Twig\Error\SyntaxError;
use yii\base\Component;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\db\Expression;
use yii\db\StaleObjectException;
use function in_array;

/**
 * Discount service.
 *
 * @property array|Discount[] $allDiscounts
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0y
 */
class Discounts extends Component
{
    /**
     * @event DiscountEvent The event that is triggered before a discount is saved.
     *
     * ```php
     * use craft\commerce\events\DiscountEvent;
     * use craft\commerce\services\Discounts;
     * use craft\commerce\models\Discount;
     * use yii\base\Event;
     *
     * Event::on(
     *     Discounts::class,
     *     Discounts::EVENT_BEFORE_SAVE_DISCOUNT,
     *     function(DiscountEvent $event) {
     *         // @var Discount $discount
     *         $discount = $event->discount;
     *         // @var bool $isNew
     *         $isNew = $event->isNew;
     *
     *         // Let an external CRM know about a client’s new discount
     *         // ...
     *     }
     * );
     * ```
     */
    const EVENT_BEFORE_SAVE_DISCOUNT = 'beforeSaveDiscount';

    /**
     * @event DiscountEvent The event that is triggered after a discount is saved.
     *
     * ```php
     * use craft\commerce\events\DiscountEvent;
     * use craft\commerce\services\Discounts;
     * use craft\commerce\models\Discount;
     * use yii\base\Event;
     *
     * Event::on(
     *     Discounts::class,
     *     Discounts::EVENT_AFTER_SAVE_DISCOUNT,
     *     function(DiscountEvent $event) {
     *         // @var Discount $discount
     *         $discount = $event->discount;
     *         // @var bool $isNew
     *         $isNew = $event->isNew;
     *
     *         // Set this discount as default in an external CRM
     *         // ...
     *     }
     * );
     * ```
     */
    const EVENT_AFTER_SAVE_DISCOUNT = 'afterSaveDiscount';

    /**
     * @event DiscountEvent The event that is triggered after a discount is deleted.
     *
     * ```php
     * use craft\commerce\events\DiscountEvent;
     * use craft\commerce\services\Discounts;
     * use craft\commerce\models\Discount;
     * use yii\base\Event;
     *
     * Event::on(
     *     Discounts::class,
     *     Discounts::EVENT_AFTER_DELETE_DISCOUNT,
     *     function(DiscountEvent $event) {
     *         // @var Discount $discount
     *         $discount = $event->discount;
     *
     *         // Remove this discount from a payment gateway
     *         // ...
     *     }
     * );
     * ```
     */
    const EVENT_AFTER_DELETE_DISCOUNT = 'afterDeleteDiscount';

    /**
     * @event MatchLineItemEvent The event that is triggered when a line item is matched with a discount.
     *
     * This event will be raised if all standard conditions are met.
     * You may set the `isValid` property to `false` on the event to prevent the matching of the discount to the line item.
     *
     * ```php
     * use craft\commerce\services\Discounts;
     * use craft\commerce\events\MatchLineItemEvent;
     * use craft\commerce\models\Discount;
     * use craft\commerce\models\LineItem;
     * use yii\base\Event;
     *
     * Event::on(
     *     Discounts::class,
     *     Discounts::EVENT_DISCOUNT_MATCHES_LINE_ITEM,
     *     function(MatchLineItemEvent $event) {
     *         // @var LineItem $lineItem
     *         $lineItem = $event->lineItem;
     *         // @var Discount $discount
     *         $discount = $event->discount;
     *
     *         // Check some business rules and prevent a match in special cases
     *         // ...
     *     }
     * );
     * ```
     */
    const EVENT_DISCOUNT_MATCHES_LINE_ITEM = 'discountMatchesLineItem';

    /**
     * @event MatchOrderEvent The event that is triggered when an order is matched with a discount.
     *
     * You may set the `isValid` property to `false` on the event to prevent the matching of the discount with the order.
     *
     * ```php
     * use craft\commerce\services\Discounts;
     * use craft\commerce\events\MatchOrderEvent;
     * use craft\commerce\models\Discount;
     * use craft\commerce\elements\Order;
     * use yii\base\Event;
     *
     * Event::on(
     *     Discounts::class,
     *     Discounts::EVENT_DISCOUNT_MATCHES_ORDER,
     *     function(MatchLineOrder $event) {
     *         // @var Order $order
     *         $order = $event->order;
     *         // @var Discount $discount
     *         $discount = $event->discount;
     *
     *         // Check some business rules and prevent a match in special cases
     *         // ... $event->isValid = false; // set to false if you want it to NOT match as it would.
     *     }
     * );
     * ```
     */
    const EVENT_DISCOUNT_MATCHES_ORDER = 'discountMatchesOrder';

    /**
     * @var Discount[]|null
     */
    private ?array $_allDiscounts = null;

    /**
     * @var Discount[][]|null
     */
    private ?array $_activeDiscountsByKey;

    /**
     * @var array|null
     */
    private ?array $_matchingDiscountsToOrder;

    /**
     * @var array|null
     */
    private ?array $_matchingDiscountsToLineItem;

    /**
     * Get a discount by its ID.
     *
     * @param int $id
     * @return Discount|null
     */
    public function getDiscountById($id): ?Discount
    {
        if (!$id) {
            return null;
        }

        $discounts = $this->_createDiscountQuery()->andWhere(['[[discounts.id]]' => $id])->all();

        if (!$discounts) {
            return null;
        }

        return ArrayHelper::firstValue($this->_populateDiscounts($discounts));
    }

    /**
     * Get all discounts.
     *
     * @return Discount[]
     */
    public function getAllDiscounts(): array
    {
        if (null === $this->_allDiscounts) {
            $discounts = $this->_createDiscountQuery()->all();

            $this->_allDiscounts = $this->_populateDiscounts($discounts);
        }

        return $this->_allDiscounts;
    }

    /**
     * Get all currently active discounts
     * We pass the Order to attempt ot optimize the query to only possible discounts that might match,
     * eliminating ones that definitely will not match.
     *
     * @param Order|null $order
     * @return Discount[]
     * @throws \Exception
     * @since 2.2.14
     */
    public function getAllActiveDiscounts(Order $order = null): array
    {
        // Date condition for use with key
        if ($order && $order->dateOrdered) {
            $date = $order->dateOrdered;
        } else {
            // We use a round the time so we can have a cache within the same request (rounded to 1 minute flat, no seconds)
            $date = new DateTime();
            $date->setTime($date->format('H'), round($date->format('i') / 1) * 1);
        }

        // Coupon condition key
        $couponKey = ($order && $order->couponCode) ? $order->couponCode : '*';
        $dateKey = DateTimeHelper::toIso8601($date);
        $cacheKey = implode(':', [$dateKey, $couponKey]);

        if (isset($this->_activeDiscountsByKey[$cacheKey])) {
            return $this->_activeDiscountsByKey[$cacheKey];
        }

        $discountQuery = $this->_createDiscountQuery()
            // Restricted by enabled discounts
            ->where([
                'enabled' => true,
            ])
            // Restrict by things that a definitely not in date
            ->andWhere([
                'or',
                ['dateFrom' => null],
                ['<=', 'dateFrom', Db::prepareDateForDb($date)]
            ])
            ->andWhere([
                'or',
                ['dateTo' => null],
                ['>=', 'dateTo', Db::prepareDateForDb($date)]
            ]);

        // If the order has a coupon code let's only get discounts for that code, or discounts that do not require a code
        if ($order && $order->couponCode) {
            if (Craft::$app->getDb()->getIsPgsql()) {
                $discountQuery->andWhere(
                    [
                        'or',
                        ['code' => null],
                        ['ilike', 'code', $order->couponCode]
                    ]
                );
            } else {
                $discountQuery->andWhere(
                    [
                        'or',
                        ['code' => null],
                        ['code' => $order->couponCode]
                    ]
                );
            }
        }

        $this->_activeDiscountsByKey[$cacheKey] = $this->_populateDiscounts($discountQuery->all());

        return $this->_activeDiscountsByKey[$cacheKey];
    }

    /**
     * Is discount coupon available to the order
     *
     * @param Order $order
     * @param string|null $explanation
     * @return bool
     * @throws InvalidConfigException
     * @throws \Exception
     */
    public function orderCouponAvailable(Order $order, string &$explanation = null): bool
    {
        $discount = $this->getDiscountByCode($order->couponCode);

        if (!$discount) {
            $explanation = Craft::t('commerce', 'Coupon not valid.');
            return false;
        }

        if (!$this->_isDiscountConditionFormulaValid($order, $discount)) {
            $explanation = Craft::t('commerce', 'Discount is not allowed for the order');
            return false;
        }

        if (!$this->_isDiscountDateValid($order, $discount)) {
            $explanation = Craft::t('commerce', 'Discount is out of date.');
            return false;
        }

        if (!$this->_isDiscountTotalUseLimitValid($discount)) {
            $explanation = Craft::t('commerce', 'Discount use has reached its limit.');
            return false;
        }

        $customer = $order->getCustomer();
        $user = $customer ? $customer->getUser() : null;

        if (!$this->isDiscountUserGroupValid($discount, $user)) {
            $explanation = Craft::t('commerce', 'Discount is not allowed for the customer.');
            return false;
        }

        if (!$this->_isDiscountPerUserUsageValid($discount, $user, $customer)) {
            $explanation = Craft::t('commerce', 'This coupon is for registered users and limited to {limit} uses.', [
                'limit' => $discount->perUserLimit,
            ]);
            return false;
        }

        if (!$this->_isDiscountEmailRequirementValid($discount, $order)) {
            $explanation = Craft::t('commerce', 'This coupon requires an email address.');
            return false;
        }

        if (!$this->_isDiscountPerEmailLimitValid($discount, $order)) {
            $explanation = Craft::t('commerce', 'This coupon is limited to {limit} uses.', [
                'limit' => $discount->perEmailLimit,
            ]);
            return false;
        }

        return true;
    }

    /**
     * Returns an enabled discount by its code.
     *
     * @param string $code
     * @return Discount|null
     * @throws \Exception
     */
    public function getDiscountByCode(string $code): ?Discount
    {
        if (!$code) {
            return null;
        }

        $query = $this->_createDiscountQuery();
        if (Craft::$app->getDb()->getIsPgsql()) {
            $query->andWhere(['ilike', '[[discounts.code]]', $code]);
        } else {
            $query->andWhere(['[[discounts.code]]' => $code]);
        }
        $discounts = $query->all();

        if (!$discounts) {
            return null;
        }

        return ArrayHelper::firstWhere($this->_populateDiscounts($discounts), function($discount) use ($code) {
            return ($discount->enabled && $discount->code && $code && (strcasecmp($code, $discount->code) == 0));
        });
    }

    /**
     * @param PurchasableInterface $purchasable
     * @return array
     * @since 2.2
     */
    public function getDiscountsRelatedToPurchasable(PurchasableInterface $purchasable): array
    {
        $discounts = [];

        if ($purchasable->getId()) {
            foreach ($this->getAllDiscounts() as $discount) {
                // Get discount by related purchasable
                $purchasableIds = $discount->getPurchasableIds();
                $id = $purchasable->getId();

                // Get discount by related category
                $relatedTo = [$discount->categoryRelationshipType => $purchasable->getPromotionRelationSource()];
                $categoryIds = $discount->getCategoryIds();
                $relatedCategories = Category::find()->id($categoryIds)->relatedTo($relatedTo)->ids();

                if (in_array($id, $purchasableIds, false) || !empty($relatedCategories)) {
                    $discounts[$discount->id] = $discount;
                }
            }
        }

        return $discounts;
    }

    /**
     * Match a line item against a discount.
     *
     * @param LineItem $lineItem
     * @param Discount $discount
     * @param bool $matchOrder
     * @return bool
     * @throws \Exception
     */
    public function matchLineItem(LineItem $lineItem, Discount $discount, bool $matchOrder = false): bool
    {
        if ($matchOrder && !$this->matchOrder($lineItem->order, $discount)) {
            return false;
        }

        $matchCacheKey = spl_object_hash($lineItem) . ':' . spl_object_hash($discount);

        if (isset($this->_matchingDiscountsToLineItem[$matchCacheKey])) {
            return $this->_matchingDiscountsToLineItem[$matchCacheKey];
        }

        if ($lineItem->getOnSale() && $discount->excludeOnSale) {
            return $this->_matchingDiscountsToLineItem[$matchCacheKey] = false;
        }

        // can't match something not promotable
        if (!$lineItem->getPurchasable() || !$lineItem->getPurchasable()->getIsPromotable()) {
            return $this->_matchingDiscountsToLineItem[$matchCacheKey] = false;
        }

        if ($discount->getPurchasableIds() && !$discount->allPurchasables) {
            $purchasableId = $lineItem->purchasableId;
            if (!in_array($purchasableId, $discount->getPurchasableIds(), false)) {
                return $this->_matchingDiscountsToLineItem[$matchCacheKey] = false;
            }
        }

        if ($discount->getCategoryIds() && !$discount->allCategories && $lineItem->getPurchasable()) {
            $purchasable = $lineItem->getPurchasable();

            if (!$purchasable) {
                return $this->_matchingDiscountsToLineItem[$matchCacheKey] = false;
            }

            $key = $discount->id . ':' . $purchasable->getId() . 'categories:' . implode('|', $discount->getCategoryIds());

            $relatedTo = [$discount->categoryRelationshipType => $purchasable->getPromotionRelationSource()];
            $relatedCategories = Category::find()->relatedTo($relatedTo)->ids();
            $purchasableIsRelateToOneOrMoreCategories = (bool)array_intersect($relatedCategories, $discount->getCategoryIds());
            if (!$purchasableIsRelateToOneOrMoreCategories) {
                return $this->_matchingDiscountsToLineItem[$matchCacheKey] = false;
            }
        }

        $event = new MatchLineItemEvent(compact('lineItem', 'discount'));

        if ($this->hasEventHandlers(self::EVENT_DISCOUNT_MATCHES_LINE_ITEM)) {
            $this->trigger(self::EVENT_DISCOUNT_MATCHES_LINE_ITEM, $event);
        }

        return $this->_matchingDiscountsToLineItem[$matchCacheKey] = $event->isValid;
    }

    /**
     * @param Order $order
     * @param Discount $discount
     * @return bool
     * @throws \Exception
     */
    public function matchOrder(Order $order, Discount $discount): bool
    {
        $matchCacheKey = $order->number . ':' . spl_object_hash($discount);

        if (isset($this->_matchingDiscountsToOrder[$matchCacheKey])) {
            return $this->_matchingDiscountsToOrder[$matchCacheKey];
        }

        if (!$discount->enabled) {
            return $this->_matchingDiscountsToOrder[$matchCacheKey] = false;
        }

        if (!$this->_isDiscountCouponCodeValid($order, $discount)) {
            return $this->_matchingDiscountsToOrder[$matchCacheKey] = false;
        }

        if (!$this->_isDiscountDateValid($order, $discount)) {
            return $this->_matchingDiscountsToOrder[$matchCacheKey] = false;
        }

        $customer = $order->getCustomer();
        $user = $customer ? $customer->getUser() : null;

        if (!$this->isDiscountUserGroupValid($discount, $user)) {
            return $this->_matchingDiscountsToOrder[$matchCacheKey] = false;
        }

        if (!$this->_isDiscountTotalUseLimitValid($discount)) {
            return $this->_matchingDiscountsToOrder[$matchCacheKey] = false;
        }

        if (!$this->_isDiscountPerUserUsageValid($discount, $user, $customer)) {
            return $this->_matchingDiscountsToOrder[$matchCacheKey] = false;
        }

        if (!$this->_isDiscountEmailRequirementValid($discount, $order)) {
            return $this->_matchingDiscountsToOrder[$matchCacheKey] = false;
        }

        if (!$this->_isDiscountPerEmailLimitValid($discount, $order)) {
            return $this->_matchingDiscountsToOrder[$matchCacheKey] = false;
        }

        if (!$this->_isDiscountConditionFormulaValid($order, $discount)) {
            return $this->_matchingDiscountsToOrder[$matchCacheKey] = false;
        }

        if (($discount->allPurchasables && $discount->allCategories) && $discount->purchaseTotal > 0 && $order->getItemSubtotal() < $discount->purchaseTotal) {
            return $this->_matchingDiscountsToOrder[$matchCacheKey] = false;
        }

        if (($discount->allPurchasables && $discount->allCategories) && $discount->purchaseQty > 0 && $order->getTotalQty() < $discount->purchaseQty) {
            return $this->_matchingDiscountsToOrder[$matchCacheKey] = false;
        }

        if (($discount->allPurchasables && $discount->allCategories) && $discount->maxPurchaseQty > 0 && $order->getTotalQty() > $discount->maxPurchaseQty) {
            return $this->_matchingDiscountsToOrder[$matchCacheKey] = false;
        }

        // Check to see if we need to match on data related to the lineItems
        if (($discount->getPurchasableIds() && !$discount->allPurchasables) || ($discount->getCategoryIds() && !$discount->allCategories)) {
            $lineItemMatch = false;
            $matchingTotal = 0;
            $matchingQty = 0;
            foreach ($order->getLineItems() as $lineItem) {
                // Must mot match order as we would get an infinate recursion
                if ($this->matchLineItem($lineItem, $discount, false)) {
                    $lineItemMatch = true;
                    $matchingTotal += $lineItem->getSubtotal();
                    $matchingQty += $lineItem->qty;
                }
            }

            if (!$lineItemMatch) {
                return $this->_matchingDiscountsToOrder[$matchCacheKey] = false;
            }

            if ($discount->purchaseTotal > 0 && $matchingTotal < $discount->purchaseTotal) {
                return $this->_matchingDiscountsToOrder[$matchCacheKey] = false;
            }

            if ($discount->purchaseQty > 0 && $matchingQty < $discount->purchaseQty) {
                return $this->_matchingDiscountsToOrder[$matchCacheKey] = false;
            }

            if ($discount->maxPurchaseQty > 0 && $matchingQty > $discount->maxPurchaseQty) {
                return $this->_matchingDiscountsToOrder[$matchCacheKey] = false;
            }
        }

        // Raise the 'beforeMatchLineItem' event
        $event = new MatchOrderEvent(compact('order', 'discount'));

        if ($this->hasEventHandlers(self::EVENT_DISCOUNT_MATCHES_ORDER)) {
            $this->trigger(self::EVENT_DISCOUNT_MATCHES_ORDER, $event);
        }

        return $this->_matchingDiscountsToOrder[$matchCacheKey] = $event->isValid;
    }


    /**
     * Save a discount.
     *
     * @param Discount $model the discount being saved
     * @param bool $runValidation should we validate this discount before saving.
     * @return bool
     * @throws \Exception
     */
    public function saveDiscount(Discount $model, bool $runValidation = true): bool
    {
        $isNew = !$model->id;

        if ($model->id) {
            $record = DiscountRecord::findOne($model->id);

            if (!$record) {
                throw new Exception(Craft::t('commerce', 'No discount exists with the ID “{id}”', ['id' => $model->id]));
            }
        } else {
            $record = new DiscountRecord();
        }

        // Raise the beforeSaveDiscount event
        if ($this->hasEventHandlers(self::EVENT_BEFORE_SAVE_DISCOUNT)) {
            $this->trigger(self::EVENT_BEFORE_SAVE_DISCOUNT, new DiscountEvent([
                'discount' => $model,
                'isNew' => $isNew,
            ]));
        }

        if ($runValidation && !$model->validate()) {
            Craft::info('Discount not saved due to validation error.', __METHOD__);

            return false;
        }

        $record->name = $model->name;
        $record->description = $model->description;
        $record->dateFrom = $model->dateFrom;
        $record->dateTo = $model->dateTo;
        $record->enabled = $model->enabled;
        $record->stopProcessing = $model->stopProcessing;
        $record->purchaseTotal = $model->purchaseTotal;
        $record->orderConditionFormula = $model->orderConditionFormula;
        $record->purchaseQty = $model->purchaseQty;
        $record->maxPurchaseQty = $model->maxPurchaseQty;
        $record->baseDiscount = $model->baseDiscount;
        $record->baseDiscountType = $model->baseDiscountType;
        $record->perItemDiscount = $model->perItemDiscount;
        $record->percentDiscount = $model->percentDiscount;
        $record->percentageOffSubject = $model->percentageOffSubject;
        $record->hasFreeShippingForMatchingItems = $model->hasFreeShippingForMatchingItems;
        $record->hasFreeShippingForOrder = $model->hasFreeShippingForOrder;
        $record->excludeOnSale = $model->excludeOnSale;
        $record->perUserLimit = $model->perUserLimit;
        $record->perEmailLimit = $model->perEmailLimit;
        $record->totalDiscountUseLimit = $model->totalDiscountUseLimit;
        $record->ignoreSales = $model->ignoreSales;
        $record->categoryRelationshipType = $model->categoryRelationshipType;
        $record->appliedTo = $model->appliedTo;

        $record->sortOrder = $record->sortOrder ?: 999;
        $record->code = $model->code ?: null;

        $record->userGroupsCondition = $model->userGroupsCondition;
        $record->allCategories = $model->allCategories = empty($model->getCategoryIds());
        $record->allPurchasables = $model->allPurchasables = empty($model->getPurchasableIds());

        $db = Craft::$app->getDb();
        $transaction = $db->beginTransaction();

        try {
            $record->save(false);
            $model->id = $record->id;

            DiscountUserGroupRecord::deleteAll(['discountId' => $model->id]);
            DiscountPurchasableRecord::deleteAll(['discountId' => $model->id]);
            DiscountCategoryRecord::deleteAll(['discountId' => $model->id]);

            foreach ($model->getUserGroupIds() as $groupId) {
                $relation = new DiscountUserGroupRecord;
                $relation->userGroupId = $groupId;
                $relation->discountId = $model->id;
                $relation->save(false);
            }

            foreach ($model->getCategoryIds() as $categoryId) {
                $relation = new DiscountCategoryRecord();
                $relation->categoryId = $categoryId;
                $relation->discountId = $model->id;
                $relation->save(false);
            }

            foreach ($model->getPurchasableIds() as $purchasableId) {
                $relation = new DiscountPurchasableRecord();
                $element = Craft::$app->getElements()->getElementById($purchasableId);
                $relation->purchasableType = get_class($element);
                $relation->purchasableId = $purchasableId;
                $relation->discountId = $model->id;
                $relation->save(false);
            }

            $transaction->commit();

            // Raise the afterSaveDiscount event
            if ($this->hasEventHandlers(self::EVENT_AFTER_SAVE_DISCOUNT)) {
                $this->trigger(self::EVENT_AFTER_SAVE_DISCOUNT, new DiscountEvent([
                    'discount' => $model,
                    'isNew' => $isNew,
                ]));
            }

            // Reset internal cache
            $this->_allDiscounts = null;
            $this->_activeDiscountsByKey = null;
            $this->_matchingDiscountsToLineItem = null;
            $this->_matchingDiscountsToOrder = null;

            return true;
        } catch (\Exception $e) {
            $transaction->rollBack();
            throw $e;
        }
    }

    /**
     * Delete a discount by its ID.
     *
     * @param int $id
     * @return bool
     * @throws \Throwable
     * @throws StaleObjectException
     */
    public function deleteDiscountById(int $id): bool
    {
        $discountRecord = DiscountRecord::findOne($id);

        if (!$discountRecord) {
            return false;
        }

        // Get the Discount model before deletion to pass to the Event.
        $discount = $this->getDiscountById($id);

        $result = (bool)$discountRecord->delete();

        //Raise the afterDeleteDiscount event
        if ($result && $this->hasEventHandlers(self::EVENT_AFTER_DELETE_DISCOUNT)) {
            $this->trigger(self::EVENT_AFTER_DELETE_DISCOUNT, new DiscountEvent([
                'discount' => $discount,
                'isNew' => false
            ]));
        }

        // Reset internal cache
        $this->_allDiscounts = null;
        $this->_activeDiscountsByKey = null;

        return $result;
    }

    /**
     * @param int $id
     * @throws \yii\db\Exception
     * @since 3.0
     */
    public function clearCustomerUsageHistoryById(int $id): void
    {
        $db = Craft::$app->getDb();

        $db->createCommand()
            ->delete(Table::CUSTOMER_DISCOUNTUSES, ['discountId' => $id])
            ->execute();

        // Reset internal cache
        $this->_allDiscounts = null;
        $this->_activeDiscountsByKey = null;
    }

    /**
     * @param int $id
     * @throws \yii\db\Exception
     * @since 3.0
     */
    public function clearEmailUsageHistoryById(int $id): void
    {
        $db = Craft::$app->getDb();

        $db->createCommand()
            ->delete(Table::EMAIL_DISCOUNTUSES, ['discountId' => $id])
            ->execute();

        // Reset internal cache
        $this->_allDiscounts = null;
        $this->_activeDiscountsByKey = null;
    }

    /**
     * Clear total discount uses
     *
     * @param int $id
     * @throws \yii\db\Exception
     * @since 3.0
     */
    public function clearDiscountUsesById(int $id): void
    {
        $db = Craft::$app->getDb();
        $db->createCommand()
            ->update(Table::DISCOUNTS, ['totalDiscountUses' => 0], ['id' => $id])
            ->execute();

        // Reset internal cache
        $this->_allDiscounts = null;
        $this->_activeDiscountsByKey = null;
    }

    /**
     * Reorder discounts by an array of ids.
     *
     * @param array $ids
     * @return bool
     * @throws \yii\db\Exception
     */
    public function reorderDiscounts(array $ids): bool
    {
        foreach ($ids as $sortOrder => $id) {
            Craft::$app->getDb()->createCommand()
                ->update(Table::DISCOUNTS, ['sortOrder' => $sortOrder + 1], ['id' => $id])
                ->execute();
        }

        // Reset internal cache
        $this->_allDiscounts = null;
        $this->_activeDiscountsByKey = null;

        return true;
    }

    /**
     * Email usage stats for discount
     *
     * @param int $id
     * @return array return in the format ['uses' => int, 'emails' => int]
     */
    public function getEmailUsageStatsById(int $id): array
    {
        $usage = (new Query())
            ->select(['COALESCE(SUM(uses), 0) as uses', 'COUNT(email) as emails'])
            ->from(Table::EMAIL_DISCOUNTUSES)
            ->where(['discountId' => $id])
            ->one();

        return $usage;
    }

    /**
     * Customer usage stats for discount
     *
     * @param int $id
     * @return array return in the format ['uses' => int, 'customers' => int]
     */
    public function getCustomerUsageStatsById(int $id): array
    {
        $usage = (new Query())
            ->select(['COALESCE(SUM(uses), 0) as uses', 'COUNT([[customerId]]) as customers'])
            ->from(Table::CUSTOMER_DISCOUNTUSES)
            ->where(['[[discountId]]' => $id])
            ->one();

        return $usage;
    }

    /**
     * Updates discount uses counters.
     *
     * @param Order $order
     * @throws \yii\db\Exception
     */
    public function orderCompleteHandler(Order $order): void
    {
        $discountAdjustments = $order->getAdjustmentsByType(DiscountAdjuster::ADJUSTMENT_TYPE);

        if (empty($discountAdjustments)) {
            return;
        }

        /* We only need to make counter updates once for each discount. A discount
        might be returned multiple times due to it being a lineItem adjustment */
        $discounts = [];
        /** @var OrderAdjustment $discountAdjustment */
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

        $customer = $order->getCustomer();
        foreach ($discounts as $discount) {
            // Count if there was a user on this order
            if ($customer && $customer->userId) {
                $customerDiscountUseRecord = CustomerDiscountUseRecord::find()->where(['[[customerId]]' => $order->customerId, '[[discountId]]' => $discount['discountUseId']])->one();

                if (!$customerDiscountUseRecord) {
                    $customerDiscountUseRecord = new CustomerDiscountUseRecord();
                    $customerDiscountUseRecord->customerId = $order->customerId;
                    $customerDiscountUseRecord->discountId = $discount['discountUseId'];
                    $customerDiscountUseRecord->uses = 1;
                    $customerDiscountUseRecord->save();
                } else {
                    Craft::$app->getDb()->createCommand()
                        ->update(Table::CUSTOMER_DISCOUNTUSES, [
                            'uses' => new Expression('[[uses]] + 1')
                        ], [
                            'customerId' => $order->customerId,
                            'discountId' => $discount['discountUseId']
                        ])
                        ->execute();
                }
            }

            // Count email usage
            $customerDiscountUseRecord = EmailDiscountUseRecord::find()->where(['email' => $order->getEmail(), 'discountId' => $discount['discountUseId']])->one();
            if (!$customerDiscountUseRecord) {
                $customerDiscountUseRecord = new EmailDiscountUseRecord();
                $customerDiscountUseRecord->email = $order->getEmail();
                $customerDiscountUseRecord->discountId = $discount['discountUseId'];
                $customerDiscountUseRecord->uses = 1;
                $customerDiscountUseRecord->save();
            } else {
                Craft::$app->getDb()->createCommand()
                    ->update(Table::EMAIL_DISCOUNTUSES, [
                        'uses' => new Expression('[[uses]] + 1')
                    ], [
                        'email' => $order->getEmail(),
                        'discountId' => $discount['discountUseId']
                    ])
                    ->execute();
            }

            // Update the total uses
            Craft::$app->getDb()->createCommand()
                ->update(Table::DISCOUNTS, [
                    'totalDiscountUses' => new Expression('[[totalDiscountUses]] + 1')
                ], [
                    'id' => $discount['discountUseId']
                ])
                ->execute();

            // Reset internal cache
            $this->_allDiscounts = null;
            $this->_activeDiscountsByKey = null;
        }
    }


    /**
     * @param Order $order
     * @param Discount $discount
     * @return bool
     */
    private function _isDiscountCouponCodeValid(Order $order, Discount $discount): bool
    {
        if (!$discount->code) {
            return true;
        }

        return ($discount->code && $order->couponCode && (strcasecmp($order->couponCode, $discount->code) == 0));
    }

    /**
     * @param Order $order
     * @param Discount $discount
     * @return bool
     * @throws \Exception
     */
    private function _isDiscountDateValid(Order $order, Discount $discount): bool
    {
        $now = new DateTime();

        if ($order->isCompleted && $order->dateOrdered) {
            $now = $order->dateOrdered;
        }

        $from = $discount->dateFrom;
        $to = $discount->dateTo;

        return !(($from && $from > $now) || ($to && $to < $now));
    }

    /**
     * @param Order $order
     * @param Discount $discount
     * @return bool
     * @throws InvalidConfigException
     * @throws LoaderError
     * @throws SyntaxError
     */
    private function _isDiscountConditionFormulaValid(Order $order, Discount $discount): bool
    {
        if ($discount->orderConditionFormula) {
            $fieldsAsArray = $order->getSerializedFieldValues();
            $orderAsArray = $order->toArray([], ['lineItems.snapshot', 'shippingAddress', 'billingAddress']);
            $orderConditionParams = [
                'order' => array_merge($orderAsArray, $fieldsAsArray)
            ];
            return Plugin::getInstance()->getFormulas()->evaluateCondition($discount->orderConditionFormula, $orderConditionParams, 'Evaluate Order Discount Condition Formula');
        }

        return true;
    }

    /**
     * @param Discount $discount
     * @param $user
     * @return bool
     * @throws InvalidConfigException
     */
    public function isDiscountUserGroupValid(Discount $discount, $user): bool
    {
        $groupIds = $user ? Plugin::getInstance()->getCustomers()->getUserGroupIdsForUser($user) : [];

        $discountGroupIds = $discount->getUserGroupIds();
        if ($discount->userGroupsCondition !== DiscountRecord::CONDITION_USER_GROUPS_ANY_OR_NONE) {

            if ($discount->userGroupsCondition === DiscountRecord::CONDITION_USER_GROUPS_INCLUDE_ANY &&
                (count(array_intersect($groupIds, $discountGroupIds)) === 0)
            ) {
                return false;
            }

            sort($groupIds);
            sort($discountGroupIds);
            if ($discount->userGroupsCondition === DiscountRecord::CONDITION_USER_GROUPS_INCLUDE_ALL
                && $groupIds !== $discountGroupIds
            ) {
                return false;
            }

            if ($discount->userGroupsCondition === DiscountRecord::CONDITION_USER_GROUPS_EXCLUDE &&
                count(array_intersect($groupIds, $discountGroupIds)) > 0
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param Discount $discount
     * @return bool
     */
    private function _isDiscountTotalUseLimitValid(Discount $discount): bool
    {
        if ($discount->totalDiscountUseLimit > 0) {
            if ($discount->totalDiscountUses >= $discount->totalDiscountUseLimit) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param Discount $discount
     * @param $user
     * @param $customer
     * @return bool
     */
    private function _isDiscountPerUserUsageValid(Discount $discount, $user, $customer): bool
    {
        if ($discount->perUserLimit > 0) {
            if (!$user) {
                return false;
            }

            // The 'Per User Limit' can only be tracked against logged in users since guest customers are re-generated often
            $usage = (new Query())
                ->select(['uses'])
                ->from([Table::CUSTOMER_DISCOUNTUSES])
                ->where(['[[customerId]]' => $customer->id, 'discountId' => $discount->id])
                ->scalar();

            if ($usage && $usage >= $discount->perUserLimit) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param Discount $discount
     * @param Order $order
     * @return bool
     */
    private function _isDiscountEmailRequirementValid(Discount $discount, Order $order): bool
    {
        if ($discount->perEmailLimit > 0 && !$order->getEmail()) {
            return false;
        }

        return true;
    }

    /**
     * @param Discount $discount
     * @param Order $order
     * @return bool
     */
    private function _isDiscountPerEmailLimitValid(Discount $discount, Order $order): bool
    {
        if ($discount->perEmailLimit > 0 && $order->getEmail()) {
            $usage = (new Query())
                ->select(['uses'])
                ->from([Table::EMAIL_DISCOUNTUSES])
                ->where(['email' => $order->getEmail(), 'discountId' => $discount->id])
                ->scalar();

            if ($usage && $usage >= $discount->perEmailLimit) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param $discounts
     * @return array
     * @since 2.2.14
     */
    private function _populateDiscounts($discounts): array
    {
        $allDiscountsById = [];

        if (empty($discounts)) {
            return $allDiscountsById;
        }

        $purchasables = [];
        $categories = [];
        $userGroups = [];

        foreach ($discounts as $discount) {
            $id = $discount['id'];
            if ($discount['purchasableId']) {
                $purchasables[$id][] = $discount['purchasableId'];
            }

            if ($discount['categoryId']) {
                $categories[$id][] = $discount['categoryId'];
            }

            if ($discount['userGroupId']) {
                $userGroups[$id][] = $discount['userGroupId'];
            }

            unset($discount['purchasableId'], $discount['userGroupId'], $discount['categoryId']);

            if (!isset($allDiscountsById[$id])) {
                $allDiscountsById[$id] = new Discount($discount);
            }
        }

        foreach ($allDiscountsById as $id => $discount) {
            $discount->setPurchasableIds($purchasables[$id] ?? []);
            $discount->setCategoryIds($categories[$id] ?? []);
            $discount->setUserGroupIds($userGroups[$id] ?? []);
        }

        return $allDiscountsById;
    }

    /**
     * Returns a Query object prepped for retrieving discounts
     *
     * @return Query
     */
    private function _createDiscountQuery(): Query
    {
        $query = (new Query())
            ->select([
                '[[discounts.allCategories]]',
                '[[discounts.allPurchasables]]',
                '[[discounts.appliedTo]]',
                '[[discounts.baseDiscount]]',
                '[[discounts.baseDiscountType]]',
                '[[discounts.categoryRelationshipType]]',
                '[[discounts.code]]',
                '[[discounts.dateCreated]]',
                '[[discounts.dateFrom]]',
                '[[discounts.dateTo]]',
                '[[discounts.dateUpdated]]',
                '[[discounts.description]]',
                '[[discounts.enabled]]',
                '[[discounts.excludeOnSale]]',
                '[[discounts.hasFreeShippingForMatchingItems]]',
                '[[discounts.hasFreeShippingForOrder]]',
                '[[discounts.id]]',
                '[[discounts.ignoreSales]]',
                '[[discounts.maxPurchaseQty]]',
                '[[discounts.name]]',
                '[[discounts.orderConditionFormula]]',
                '[[discounts.percentageOffSubject]]',
                '[[discounts.percentDiscount]]',
                '[[discounts.perEmailLimit]]',
                '[[discounts.perItemDiscount]]',
                '[[discounts.perUserLimit]]',
                '[[discounts.purchaseQty]]',
                '[[discounts.purchaseTotal]]',
                '[[discounts.sortOrder]]',
                '[[discounts.stopProcessing]]',
                '[[discounts.totalDiscountUseLimit]]',
                '[[discounts.totalDiscountUses]]',
                '[[discounts.userGroupsCondition]]',
            ])
            ->from(['discounts' => Table::DISCOUNTS])
            ->orderBy(['sortOrder' => SORT_ASC]);

        $query->addSelect([
            'dp.purchasableId',
            'dpt.categoryId',
            'dug.userGroupId',
        ])->leftJoin(Table::DISCOUNT_PURCHASABLES . ' dp', '[[dp.discountId]]=[[discounts.id]]')
            ->leftJoin(Table::DISCOUNT_CATEGORIES . ' dpt', '[[dpt.discountId]]=[[discounts.id]]')
            ->leftJoin(Table::DISCOUNT_USERGROUPS . ' dug', '[[dug.discountId]]=[[discounts.id]]');

        return $query;
    }
}
