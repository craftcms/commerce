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
use craft\helpers\Db;
use DateTime;
use yii\base\Component;
use yii\base\Exception;
use yii\db\Expression;
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
     * You may set the `isValid` property to `false` on the event to prevent the application of the matched discount.
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
     *     Discounts::EVENT_BEFORE_MATCH_LINE_ITEM,
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
    const EVENT_BEFORE_MATCH_LINE_ITEM = 'beforeMatchLineItem';


    /**
     * @var Discount[]
     */
    private $_allDiscounts;

    /**
     * @var Discount[]
     */
    private $_allActiveDiscounts;


    /**
     * Get a discount by its ID.
     *
     * @param int $id
     * @return Discount|null
     */
    public function getDiscountById($id)
    {
        foreach ($this->getAllDiscounts() as $discount) {
            if ($discount->id == $id) {
                return $discount;
            }
        }

        return null;
    }

    /**
     * Get all discounts.
     *
     * @return Discount[]
     */
    public function getAllDiscounts(): array
    {
        if (null === $this->_allDiscounts) {
            $discounts = $this->_createDiscountQuery()
                ->addSelect([
                    'dp.purchasableId',
                    'dpt.categoryId',
                    'dug.userGroupId',
                ])
                ->leftJoin(Table::DISCOUNT_PURCHASABLES . ' dp', '[[dp.discountId]]=[[discounts.id]]')
                ->leftJoin(Table::DISCOUNT_CATEGORIES . ' dpt', '[[dpt.discountId]]=[[discounts.id]]')
                ->leftJoin(Table::DISCOUNT_USERGROUPS . ' dug', '[[dug.discountId]]=[[discounts.id]]')
                ->all();

            $this->_allDiscounts = $this->_populateDiscountsRelations($discounts);
        }

        return $this->_allDiscounts;
    }

    /**
     * Get all currently active discounts
     *
     * @param Order|null $order
     * @return array
     * @throws \Exception
     * @since 2.2.14
     */
    public function getAllActiveDiscounts($order = null): array
    {
        if (null === $this->_allActiveDiscounts) {
            $date = $order && $order->dateOrdered ? $order->dateOrdered : new DateTime();

            $discounts = $this->_createDiscountQuery()
                ->addSelect([
                    'dp.purchasableId',
                    'dpt.categoryId',
                    'dug.userGroupId',
                ])
                ->leftJoin(Table::DISCOUNT_PURCHASABLES . ' dp', '[[dp.discountId]]=[[discounts.id]]')
                ->leftJoin(Table::DISCOUNT_CATEGORIES . ' dpt', '[[dpt.discountId]]=[[discounts.id]]')
                ->leftJoin(Table::DISCOUNT_USERGROUPS . ' dug', '[[dug.discountId]]=[[discounts.id]]')
                // Restricted by enabled discounts
                ->where([
                    'enabled' => 1,
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
                ])
                ->all();

            $this->_allActiveDiscounts = $this->_populateDiscountsRelations($discounts);
        }

        return $this->_allActiveDiscounts;
    }

    /**
     * Populates a discount's relations.
     *
     * @param Discount $discount
     */
    public function populateDiscountRelations(Discount $discount)
    {
        $rows = (new Query())->select(
            'dp.purchasableId,
            dpt.categoryId,
            dug.userGroupId')
            ->from(Table::DISCOUNTS . ' discounts')
            ->leftJoin(Table::DISCOUNT_PURCHASABLES . ' dp', '[[dp.discountId]]=[[discounts.id]]')
            ->leftJoin(Table::DISCOUNT_CATEGORIES . ' dpt', '[[dpt.discountId]]=[[discounts.id]]')
            ->leftJoin(Table::DISCOUNT_USERGROUPS . ' dug', '[[dug.discountId]]=[[discounts.id]]')
            ->where(['discounts.id' => $discount->id])
            ->all();

        $purchasableIds = [];
        $categoryIds = [];
        $userGroupIds = [];

        foreach ($rows as $row) {
            if ($row['purchasableId']) {
                $purchasableIds[] = $row['purchasableId'];
            }

            if ($row['categoryId']) {
                $categoryIds[] = $row['categoryId'];
            }

            if ($row['userGroupId']) {
                $userGroupIds[] = $row['userGroupId'];
            }
        }

        $discount->setPurchasableIds($purchasableIds);
        $discount->setCategoryIds($categoryIds);
        $discount->setUserGroupIds($userGroupIds);
    }

    /**
     * Is discount coupon available to the order
     *
     * @param Order $order
     * @param string|null $explanation
     * @return bool
     */
    public function orderCouponAvailable(Order $order, string &$explanation = null): bool
    {
        $discount = $this->getDiscountByCode($order->couponCode);

        if (!$discount) {
            $explanation = Plugin::t('Coupon not valid.');
            return false;
        }

        if (!$this->_isDiscountDateValid($order, $discount)) {
            $explanation = Plugin::t('Discount is out of date.');
            return false;
        }

        if (!$this->_isDiscountTotalUseLimitValid($discount)) {
            $explanation = Plugin::t('Discount use has reached its limit.');
            return false;
        }

        $customer = $order->getCustomer();
        $user = $customer ? $customer->getUser() : null;

        if (!$this->_isDiscountUserGroupValid($order, $discount, $user)) {
            $explanation = Plugin::t('Discount is not allowed for the customer');
            return false;
        }

        if (!$this->_isDiscountPerUserUsageValid($discount, $user, $customer)) {
            $explanation = Plugin::t('This coupon is for registered users and limited to {limit} uses.', [
                'limit' => $discount->perUserLimit,
            ]);
            return false;
        }

        if (!$this->_isDiscountPerEmailLimitValid($discount, $order)) {
            $explanation = Plugin::t('This coupon is limited to {limit} uses.', [
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
     */
    public function getDiscountByCode($code)
    {
        if (!$code) {
            return null;
        }

        $result = $this->_createDiscountQuery()
            ->where(['code' => $code, 'enabled' => true])
            ->one();

        return $result ? new Discount($result) : null;
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
                $relatedTo = ['sourceElement' => $purchasable->getPromotionRelationSource()];
                $categoryIds = $discount->getCategoryIds();
                $relatedCategories = Category::find()->id($categoryIds)->relatedTo($relatedTo)->ids();

                if (in_array($id, $purchasableIds) || !empty($relatedCategories)) {
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
     */
    public function matchLineItem(LineItem $lineItem, Discount $discount, bool $matchOrder = false): bool
    {
        if ($matchOrder && !$this->matchOrder($lineItem->order, $discount)) {
            return false;
        }

        if ($lineItem->onSale && $discount->excludeOnSale) {
            return false;
        }

        // can't match something not promotable
        if (!$lineItem->purchasable->getIsPromotable()) {
            return false;
        }

        if ($discount->getPurchasableIds() && !$discount->allPurchasables) {
            $purchasableId = $lineItem->purchasableId;
            if (!in_array($purchasableId, $discount->getPurchasableIds(), true)) {
                return false;
            }
        }

        if ($discount->getCategoryIds() && !$discount->allCategories && $lineItem->getPurchasable()) {
            $purchasable = $lineItem->getPurchasable();

            if (!$purchasable) {
                return false;
            }

            $relatedTo = [$discount->categoryRelationshipType => $purchasable->getPromotionRelationSource()];
            $relatedCategories = Category::find()->relatedTo($relatedTo)->ids();
            $purchasableIsRelateToOneOrMoreCategories = (bool)array_intersect($relatedCategories, $discount->getCategoryIds());
            if (!$purchasableIsRelateToOneOrMoreCategories) {
                return false;
            }
        }

        // Raise the 'beforeMatchLineItem' event
        $event = new MatchLineItemEvent(compact('lineItem', 'discount'));

        $this->trigger(self::EVENT_BEFORE_MATCH_LINE_ITEM, $event);

        return $event->isValid;
    }

    /**
     * @param Order $order
     * @param Discount $discount
     * @return bool
     */
    public function matchOrder(Order $order, Discount $discount): bool
    {
        if (!$discount->enabled) {
            return false;
        }

        if (!$this->_isDiscountCouponCodeValid($order, $discount)) {
            return false;
        }

        if (!$this->_isDiscountDateValid($order, $discount)) {
            return false;
        }

        $customer = $order->getCustomer();
        $user = $customer ? $customer->getUser() : null;

        if (!$this->_isDiscountUserGroupValid($order, $discount, $user)) {
            return false;
        }

        if (!$this->_isDiscountTotalUseLimitValid($discount)) {
            return false;
        }

        if (!$this->_isDiscountPerUserUsageValid($discount, $user, $customer)) {
            return false;
        }

        if (!$this->_isDiscountPerEmailLimitValid($discount, $order)) {
            return false;
        }

        // Check to see if we need to match on data related to the lineItems
        if (($discount->getPurchasableIds() && !$discount->allPurchasables) || ($discount->getCategoryIds() && !$discount->allCategories)) {
            $lineItemMatch = false;
            foreach ($order->getLineItems() as $lineItem) {
                // Must mot match order as we would get an infinate recursion
                if ($this->matchLineItem($lineItem, $discount, false)) {
                    $lineItemMatch = true;
                    break;
                }
            }

            if (!$lineItemMatch) {
                return false;
            }
        }

        return true;
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
                throw new Exception(Plugin::t('No discount exists with the ID “{id}”', ['id' => $model->id]));
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

        $record->sortOrder = $record->sortOrder ?: 999;
        $record->code = $model->code ?: null;

        $record->allGroups = $model->allGroups = empty($model->getUserGroupIds());
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
     */
    public function deleteDiscountById($id): bool
    {
        $discountRecord = DiscountRecord::findOne($id);

        if (!$discountRecord) {
            return false;
        }

        // Get the Discount model before deletion to pass to the Event.
        $discount = $this->getDiscountById($id);

        $result = (bool)$discountRecord->delete();

        //Raise the afterDeleteDiscount event
        if ($this->hasEventHandlers(self::EVENT_AFTER_DELETE_DISCOUNT)) {
            $this->trigger(self::EVENT_AFTER_DELETE_DISCOUNT, new DiscountEvent([
                'discount' => $discount,
                'isNew' => false
            ]));
        }

        return $result;
    }

    /**
     * @param int $id
     * @throws \yii\db\Exception
     * @since 3.0
     */
    public function clearCustomerUsageHistoryById(int $id)
    {
        $db = Craft::$app->getDb();

        $db->createCommand()
            ->delete(Table::CUSTOMER_DISCOUNTUSES, ['discountId' => $id])
            ->execute();
    }

    /**
     * @param int $id
     * @throws \yii\db\Exception
     * @since 3.0
     */
    public function clearEmailUsageHistoryById(int $id)
    {
        $db = Craft::$app->getDb();

        $db->createCommand()
            ->delete(Table::EMAIL_DISCOUNTUSES, ['discountId' => $id])
            ->execute();
    }

    /**
     * Clear total discount uses
     *
     * @param int $id
     * @throws \yii\db\Exception
     * @since 3.0
     */
    public function clearDiscountUsesById(int $id)
    {
        $db = Craft::$app->getDb();
        $db->createCommand()
            ->update(Table::DISCOUNTS, ['totalDiscountUses' => 0], ['id' => $id])
            ->execute();
    }

    /**
     * Reorder discounts by an array of ids.
     *
     * @param array $ids
     * @return bool
     */
    public function reorderDiscounts(array $ids): bool
    {
        foreach ($ids as $sortOrder => $id) {
            Craft::$app->getDb()->createCommand()
                ->update(Table::DISCOUNTS, ['sortOrder' => $sortOrder + 1], ['id' => $id])
                ->execute();
        }

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
     */
    public function orderCompleteHandler($order)
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
        $now = $order->dateUpdated ?? new DateTime();
        $from = $discount->dateFrom;
        $to = $discount->dateTo;

        return !(($from && $from > $now) || ($to && $to < $now));
    }

    /**
     * @param Order $order
     * @param Discount $discount
     * @param $user
     * @return bool
     */
    private function _isDiscountUserGroupValid(Order $order, Discount $discount, $user): bool
    {
        if (!$discount->allGroups) {
            $groupIds = $user ? Plugin::getInstance()->getCustomers()->getUserGroupIdsForUser($user) : [];
            if (empty(array_intersect($groupIds, $discount->getUserGroupIds()))) {
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
    private function _isDiscountPerEmailLimitValid(Discount $discount, Order $order): bool
    {
        if ($discount->perEmailLimit > 0 && !$order->getEmail()) {
            return false;
        }

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
    private function _populateDiscountsRelations($discounts): array
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
        return (new Query())
            ->select([
                'discounts.id',
                'discounts.name',
                'discounts.description',
                'discounts.code',
                'discounts.perUserLimit',
                'discounts.perEmailLimit',
                'discounts.totalDiscountUseLimit',
                'discounts.totalDiscountUses',
                'discounts.dateFrom',
                'discounts.dateTo',
                'discounts.purchaseTotal',
                'discounts.purchaseQty',
                'discounts.maxPurchaseQty',
                'discounts.baseDiscount',
                'discounts.baseDiscountType',
                'discounts.perItemDiscount',
                'discounts.percentDiscount',
                'discounts.percentageOffSubject',
                'discounts.excludeOnSale',
                'discounts.hasFreeShippingForMatchingItems',
                'discounts.hasFreeShippingForOrder',
                'discounts.allGroups',
                'discounts.allPurchasables',
                'discounts.allCategories',
                'discounts.categoryRelationshipType',
                'discounts.enabled',
                'discounts.stopProcessing',
                'discounts.ignoreSales',
                'discounts.sortOrder',
                'discounts.dateCreated',
                'discounts.dateUpdated',
            ])
            ->from(['discounts' => Table::DISCOUNTS])
            ->orderBy(['sortOrder' => SORT_ASC]);
    }
}
