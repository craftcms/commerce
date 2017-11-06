<?php

namespace craft\commerce\services;

use Craft;
use craft\commerce\elements\Order;
use craft\commerce\elements\Variant;
use craft\commerce\events\MatchLineItemEvent;
use craft\commerce\models\Discount;
use craft\commerce\models\LineItem;
use craft\commerce\Plugin;
use craft\commerce\records\CustomerDiscountUse as CustomerDiscountUseRecord;
use craft\commerce\records\Discount as DiscountRecord;
use craft\commerce\records\DiscountProduct as DiscountProductRecord;
use craft\commerce\records\DiscountProductType as DiscountProductTypeRecord;
use craft\commerce\records\DiscountUserGroup as DiscountUserGroupRecord;
use craft\db\Query;
use craft\elements\User;
use DateTime;
use yii\base\Component;
use yii\base\Exception;

/**
 * Discount service.
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.services
 * @since     1.0
 *
 * @property array|\craft\commerce\models\Discount[] $allDiscounts
 */
class Discounts extends Component
{
    // Properties
    // =============================================================================

    /**
     * @var Discount[]
     */
    private $_allDiscounts;

    // Constants
    // =========================================================================

    /**
     * @event MatchLineItemEvent The event that is triggered when a line item is matched with a discount
     *
     * You may set [[MatchLineItemEvent::isValid]] to `false` to prevent the matched discount from apply.
     */
    const EVENT_BEFORE_MATCH_LINE_ITEM = 'beforeMatchLineItem';

    // Public Methods
    // =========================================================================

    /**
     * @param int $id
     *
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
     * @return Discount[]
     */
    public function getAllDiscounts(): array
    {
        if (null === $this->_allDiscounts) {
            $discounts = $this->_createDiscountQuery()
                ->leftJoin('{{%commerce_discount_products}} dp', '[[dp.discountId]]=[[discounts.id]]')
                ->leftJoin('{{%commerce_discount_producttypes}} dpt', '[[dpt.discountId]]=[[discounts.id]]')
                ->leftJoin('{{%commerce_discount_usergroups}} dug', '[[dug.discountId]]=[[discounts.id]]')
                ->all();

            $allDiscountsById = [];
            $products = [];
            $productTypes = [];
            $groups = [];

            foreach ($discounts as $discount) {
                $id = $discount['id'];
                if ($discount['productId']) {
                    $products[$id][] = $discount['productId'];
                }

                if ($discount['productTypeId']) {
                    $productTypes[$id][] = $discount['productTypeId'];
                }

                if ($discount['userGroupId']) {
                    $groups[$id][] = $discount['userGroupId'];
                }

                unset($discount['productId'], $discount['userGroupId'], $discount['productTypeId']);

                if (!isset($allDiscountsById[$id])) {
                    $allDiscountsById[$id] = new Discount($discount);
                }
            }

            foreach ($allDiscountsById as $id => $discount) {
                $discount->setProductIds($products[$id] ?? []);
                $discount->setProductTypeIds($productTypes[$id] ?? []);
                $discount->setUserGroupIds($groups[$id] ?? []);
            }

            $this->_allDiscounts = $allDiscountsById;
        }

        return $this->_allDiscounts;
    }

    /**
     * Populate a discount's relations.
     *
     * @param Discount $discount
     *
     * @return void
     */
    public function populateDiscountRelations(Discount $discount)
    {
        $rows = (new Query())->select(
            'dp.productId,
            dpt.productTypeId,
            dug.userGroupId')
            ->from('{{%commerce_discounts}} discounts')
            ->leftJoin('{{%commerce_discount_products}} dp', '[[dp.discountId]]=[[discounts.id]]')
            ->leftJoin('{{%commerce_discount_producttypes}} dpt', '[[dpt.discountId]]=[[discounts.id]]')
            ->leftJoin('{{%commerce_discount_usergroups}} dug', '[[dug.discountId]]=[[discounts.id]]')
            ->where(['discounts.id' => $discount->id])
            ->all();

        $productIds = [];
        $productTypeIds = [];
        $userGroupIds = [];

        foreach ($rows as $row) {
            if ($row['productId']) {
                $productIds[] = $row['productId'];
            }

            if ($row['productTypeId']) {
                $productTypeIds[] = $row['productTypeId'];
            }

            if ($row['userGroupId']) {
                $userGroupIds[] = $row['userGroupId'];
            }
        }

        $discount->setProductIds($productIds);
        $discount->setProductTypeIds($productTypeIds);
        $discount->setUserGroupIds($userGroupIds);
    }

    /**
     * Get discount by code and check it's active and applies to the current
     * user
     *
     * @param int    $code
     * @param int    $customerId
     * @param string $error
     *
     * @return bool
     */
    public function matchCode($code, $customerId, &$error): bool
    {
        $model = $this->getDiscountByCode($code);
        if (!$model) {
            $error = Craft::t('commerce', 'Coupon not valid');

            return false;
        }

        if (!$model->enabled) {
            $error = Craft::t('commerce', 'Discount is not available');

            return false;
        }

        if ($model->totalUseLimit > 0 && $model->totalUses >= $model->totalUseLimit) {
            $error = Craft::t('commerce', 'Discount use has reached it’s limit');

            return false;
        }

        $now = new DateTime();
        $from = $model->dateFrom;
        $to = $model->dateTo;
        if (($from && $from > $now) || ($to && $to < $now)) {
            $error = Craft::t('commerce', 'Discount is out of date');

            return false;
        }

        $plugin = Plugin::getInstance();

        if (!$model->allGroups) {
            $customer = $plugin->getCustomers()->getCustomerById($customerId);
            $user = $customer ? $customer->getUser() : null;
            $groupIds = $this->getCurrentUserGroupIds($user);
            if (!$user || !array_intersect($groupIds, $model->getUserGroupIds())) {
                $error = Craft::t('commerce', 'Discount is not allowed for the customer');

                return false;
            }
        }

        if ($customerId) {
            // The 'Per User Limit' can only be tracked against logged in users since guest customers are re-generated often
            if ($model->perUserLimit > 0 && !Craft::$app->getUser()->isLoggedIn()) {
                $error = Craft::t('commerce', 'Discount is limited to use by logged in users only.');

                return false;
            }

            $allUsedUp = (new Query())
                ->select('id')
                ->from('{{%commerce_customer_discountuses}}')
                ->where(['>=', 'uses', $model->perUserLimit])
                ->one();

            if ($allUsedUp) {
                $error = Craft::t('commerce', 'You can not use this discount anymore');

                return false;
            }
        }

        if ($model->perEmailLimit > 0) {
            $cart = $plugin->getCart()->getCart();
            $email = $cart->email;

            if ($email) {
                $previousOrders = $plugin->getOrders()->getOrdersByEmail($email);

                $usedCount = 0;
                foreach ($previousOrders as $order) {
                    if (strcasecmp($order->couponCode, $code) == 0) {
                        $usedCount++;
                    }
                }

                if ($usedCount >= $model->perEmailLimit) {
                    $error = Craft::t('commerce', 'This coupon limited to {limit} uses.', [
                        'limit' => $model->perEmailLimit,
                    ]);

                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @param string $code
     *
     * @return Discount|null
     */
    public function getDiscountByCode($code)
    {
        if (!$code) {
            return null;
        }

        $result = $this->_createDiscountQuery()
            ->where(['code' => $code, 'enabled' => true])
            ->all();

        if ($result) {
            return new Discount($result);
        }

        return null;
    }

    /**
     * Returns the user groups of the user param but defaults to the current user
     *
     * @param User $user
     *
     * @return array
     */
    public function getCurrentUserGroupIds(User $user = null): array
    {
        $groupIds = [];
        $currentUser = $user ?: Craft::$app->getUser()->getIdentity();

        if ($currentUser) {
            foreach ($currentUser->getGroups() as $group) {
                $groupIds[] = $group->id;
            }

            return $groupIds;
        }

        return $groupIds;
    }

    /**
     * @param LineItem $lineItem
     * @param Discount $discount
     *
     * @return bool
     */
    public function matchLineItem(LineItem $lineItem, Discount $discount): bool
    {
        if ($lineItem->onSale && $discount->excludeOnSale) {
            return false;
        }

        // can't match something not promotable
        if (!$lineItem->purchasable->getIsPromotable()) {
            return false;
        }

        if ($discount->getProductIds()) {
            if ($lineItem->purchasable instanceof Variant) {
                $productId = $lineItem->purchasable->productId;
                if (!$discount->allProducts && !in_array($productId, $discount->getProductIds(), true)) {
                    return false;
                }
            } else {
                return false;
            }
        }

        if ($discount->getProductTypeIds()) {
            if ($lineItem->purchasable instanceof Variant) {
                $productTypeId = $lineItem->purchasable->product->typeId;
                if (!$discount->allProductTypes && !in_array($productTypeId, $discount->getProductTypeIds(), true)) {
                    return false;
                }
            } else {
                return false;
            }
        }

        // Raise the 'beforeMatchLineItem' event
        $event = new MatchLineItemEvent([
            'lineItem' => $lineItem,
            'discount' => $discount
        ]);

        $this->trigger(self::EVENT_BEFORE_MATCH_LINE_ITEM, $event);

        return $event->isValid;
    }

    /**
     * @param Discount $model
     * @param array    $groups       ids
     * @param array    $productTypes ids
     * @param array    $products     ids
     *
     * @return bool
     * @throws \Exception
     */
    public function saveDiscount(Discount $model, array $groups, array $productTypes, array $products): bool
    {
        if ($model->id) {
            $record = DiscountRecord::findOne($model->id);

            if (!$record) {
                throw new Exception(Craft::t('commerce', 'No discount exists with the ID “{id}”', ['id' => $model->id]));
            }
        } else {
            $record = new DiscountRecord();
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
        $record->perItemDiscount = $model->perItemDiscount;
        $record->percentDiscount = $model->percentDiscount;
        $record->percentageOffSubject = $model->percentageOffSubject;
        $record->freeShipping = $model->freeShipping;
        $record->excludeOnSale = $model->excludeOnSale;
        $record->perUserLimit = $model->perUserLimit;
        $record->perEmailLimit = $model->perEmailLimit;
        $record->totalUseLimit = $model->totalUseLimit;

        $record->sortOrder = $record->sortOrder ?: 999;
        $record->code = $model->code ?: null;

        $record->allGroups = $model->allGroups = empty($groups);
        $record->allProductTypes = $model->allProductTypes = empty($productTypes);
        $record->allProducts = $model->allProducts = empty($products);

        $record->validate();
        $model->addErrors($record->getErrors());

        $db = Craft::$app->getDb();
        $transaction = $db->beginTransaction();

        try {
            if (!$model->hasErrors()) {
                $record->save(false);
                $model->id = $record->id;

                DiscountUserGroupRecord::deleteAll(['discountId' => $model->id]);
                DiscountProductRecord::deleteAll(['discountId' => $model->id]);
                DiscountProductTypeRecord::deleteAll(['discountId' => $model->id]);

                foreach ($groups as $groupId) {
                    $relation = new DiscountUserGroupRecord;
                    $relation->userGroupId = $groupId;
                    $relation->discountId = $model->id;
                    $relation->save();
                }

                foreach ($productTypes as $productTypeId) {
                    $relation = new DiscountProductTypeRecord();
                    $relation->productTypeId = $productTypeId;
                    $relation->discountId = $model->id;
                    $relation->save();
                }

                foreach ($products as $productId) {
                    $relation = new DiscountProductRecord;
                    $relation->productId = $productId;
                    $relation->discountId = $model->id;
                    $relation->save();
                }

                $transaction->commit();

                return true;
            }
        } catch (\Exception $e) {
            $transaction->rollBack();
            throw $e;
        }

        $transaction->rollBack();

        return false;
    }

    /**
     * @param int $id
     *
     * @return bool
     */
    public function deleteDiscountById($id): bool
    {
        $record = DiscountRecord::findOne($id);

        if ($record) {
            return $record->delete();
        }

        return false;
    }

    /**
     * Clear a coupon's usage history.
     *
     * @param int $id coupon id
     */
    public function clearCouponUsageHistoryById(int $id)
    {
        $db = Craft::$app->getDb();

        $db->createCommand()
            ->delete('{{%commerce_customer_discountuses}}', ['discountId' => $id])
            ->execute();

        $db->createCommand()
            ->update('{{%commerce_discounts}}', ['totalUses' => 0], ['id' => $id])
            ->execute();
    }

    /**
     * @param $ids
     *
     * @return bool
     */
    public function reorderDiscounts($ids): bool
    {
        foreach ($ids as $sortOrder => $id) {
            Craft::$app->getDb()->createCommand()
                ->update('{{%commerce_discounts}}', ['sortOrder' => $sortOrder + 1], ['id' => $id])
                ->execute();
        }

        return true;
    }

    /**
     * Update discount uses counters
     *
     * @param Order $order
     */
    public function orderCompleteHandler($order)
    {
        if (!$order->couponCode) {
            return;
        }

        /** @var DiscountRecord $record */
        $record = DiscountRecord::find()->where(['code' => $order->couponCode])->one();
        if (!$record || !$record->id) {
            return;
        }

        if ($record->totalUseLimit) {
            // Increment total uses.
            Craft::$app->getDb()->createCommand()
                ->update('{{%commerce_discounts}}', ['[[totalUses]]' => '[[totalUses]] + 1'], ['code' => $order->couponCode])
                ->execute();
        }

        if ($record->perUserLimit && $order->customerId) {
            $customerDiscountUseRecord = CustomerDiscountUseRecord::find()->where(['customerId' => $order->customerId, 'discountId' => $record->id])->one();

            if (!$customerDiscountUseRecord) {
                $customerDiscountUseRecord = new CustomerDiscountUseRecord();
                $customerDiscountUseRecord->customerId = $order->customerId;
                $customerDiscountUseRecord->discountId = $record->id;
                $customerDiscountUseRecord->uses = 1;
                $customerDiscountUseRecord->save();
            } else {
                Craft::$app->getDb()->createCommand()
                    ->update('{{%commerce_customer_discountuse}}', ['[[uses]]' => '[[uses]] + 1'], ['customerId' => $order->customerId, 'discountId' => $record->id])
                    ->execute();
            }
        }
    }

    // Private Methods
    // =========================================================================

    /**
     * Returns a Query object prepped for retrieving discounts
     *
     * @return Query
     */
    private function _createDiscountQuery(): Query
    {
        return (new Query())->select(
            'discounts.id,
            discounts.name,
            discounts.description,
            discounts.code,
            discounts.perUserLimit,
            discounts.perEmailLimit,
            discounts.totalUseLimit,
            discounts.totalUses,
            discounts.dateFrom,
            discounts.dateTo,
            discounts.purchaseTotal,
            discounts.purchaseQty,
            discounts.maxPurchaseQty,
            discounts.baseDiscount,
            discounts.perItemDiscount,
            discounts.percentDiscount,
            discounts.percentageOffSubject,
            discounts.excludeOnSale,
            discounts.freeShipping,
            discounts.allGroups,
            discounts.allProducts,
            discounts.allProductTypes,
            discounts.enabled,
            discounts.stopProcessing,
            discounts.sortOrder,
            dp.productId,
            dpt.productTypeId,
            dug.userGroupId')
            ->from('{{%commerce_discounts}} discounts')
            ->orderBy(['sortOrder' => SORT_ASC]);
    }
}
