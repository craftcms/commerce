<?php

namespace craft\commerce\services;

use Craft;
use craft\commerce\elements\Order;
use craft\commerce\elements\Variant;
use craft\commerce\events\MatchLineItemEvent;
use craft\commerce\models\Discount;
use craft\commerce\models\LineItem;
use craft\commerce\records\CustomerDiscountUse as CustomerDiscountUseRecord;
use craft\commerce\records\Discount as DiscountRecord;
use craft\commerce\records\DiscountProduct as DiscountProductRecord;
use craft\commerce\records\DiscountProductType as DiscountProductTypeRecord;
use craft\commerce\records\DiscountUserGroup as DiscountUserGroupRecord;
use craft\helpers\ArrayHelper;
use yii\base\Component;

/**
 * Discount service.
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.services
 * @since     1.0
 */
class Discounts extends Component
{
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
     *
     * @return Discount[]
     */
    public function getAllDiscounts()
    {
        $records = DiscountRecord::find()->orderBy('sortOrder')->all();

        return ArrayHelper::map($records, 'id', function($record){
            return $this->_createDiscountFromDiscountRecord($record);
        });
    }

    /**
     * Get discount by code and check it's active and applies to the current
     * user
     *
     * @param int    $code
     * @param int    $customerId
     * @param string $error
     *
     * @return true
     */
    public function matchCode($code, $customerId, &$error = '')
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
        if ($from && $from > $now || $to && $to < $now) {
            $error = Craft::t('commerce', 'Discount is out of date');

            return false;
        }

        if (!$model->allGroups) {
            $customer = Plugin::getInstance()->getCustomers()->getCustomerById($customerId);
            $user = $customer ? $customer->getUser() : null;
            $groupIds = $this->getCurrentUserGroupIds($user);
            if (!$user || !array_intersect($groupIds, $model->getGroupIds())) {
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

            $uses = CustomerDiscountUseRecord::find()->where(['customerId' => $customerId, 'discountId' => $model->id])->all();
            if ($uses && $uses->uses >= $model->perUserLimit) {
                $error = Craft::t('commerce', 'You can not use this discount anymore');

                return false;
            }
        }

        if ($model->perEmailLimit > 0) {
            $cart = Plugin::getInstance()->getCart()->getCart();
            $email = $cart->email;

            if ($email) {
                $previousOrders = Plugin::getInstance()->getOrders()->getOrdersByEmail($email);

                $usedCount = 0;
                foreach ($previousOrders as $order) {
                    if ($order->couponCode == $code) {
                        $usedCount = $usedCount + 1;
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

        $result = DiscountRecord::find()->where(['code' => $code, 'enabled' => true])->all();

        if ($result) {
            return new Discount($result);
        }

        return null;
    }

    /**
     * Returns the user groups of the user param but defaults to the current user
     *
     * @param UserModel $user
     *
     * @return array
     */
    public function getCurrentUserGroupIds($user = null)
    {
        $groupIds = [];
        $currentUser = $user ?: Craft::$app->getUser()->getUser();
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
    public function matchLineItem(LineItem $lineItem, Discount $discount)
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
                if (!$discount->allProducts && !in_array($productId, $discount->getProductIds())) {
                    return false;
                }
            } else {
                return false;
            }
        }


        if ($discount->getProductTypeIds()) {
            if ($lineItem->purchasable instanceof Variant) {
                $productTypeId = $lineItem->purchasable->product->typeId;
                if (!$discount->allProductTypes && !in_array($productTypeId, $discount->getProductTypeIds())) {
                    return false;
                }
            } else {
                return false;
            }
        }

        if (!$discount->allGroups) {
            $customer = $lineItem->getOrder()->getCustomer();
            $user = $customer ? $customer->getUser() : null;
            $userGroups = $this->getCurrentUserGroupIds($user);
            if (!$user || !array_intersect($userGroups, $discount->getGroupIds())) {
                return false;
            }
        }


        //raising event
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
    public function saveDiscount(Discount $model, array $groups, array $productTypes, array $products)
    {
        if ($model->id) {
            $record = DiscountRecord::findOne($model->id);

            if (!$record) {
                throw new Exception(Craft::t('commerce', 'No discount exists with the ID “{id}”', ['id' => $model->id]));
            }
        } else {
            $record = new DiscountRecord();
        }

        $fields = ['id', 'name', 'description', 'dateFrom', 'dateTo', 'enabled', 'stopProcessing', 'purchaseTotal', 'purchaseQty', 'maxPurchaseQty', 'baseDiscount', 'perItemDiscount', 'percentDiscount', 'freeShipping', 'excludeOnSale', 'perUserLimit', 'perEmailLimit', 'totalUseLimit'];
        foreach ($fields as $field) {
            $record->$field = $model->$field;
        }

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
                    $relation->attributes = ['userGroupId' => $groupId, 'discountId' => $model->id];
                    $relation->insert();
                }

                foreach ($productTypes as $productTypeId) {
                    $relation = new DiscountProductTypeRecord;
                    $relation->attributes = ['productTypeId' => $productTypeId, 'discountId' => $model->id];
                    $relation->insert();
                }

                foreach ($products as $productId) {
                    $relation = new DiscountProductRecord;
                    $relation->attributes = ['productId' => $productId, 'discountId' => $model->id];
                    $relation->insert();
                }

                $transaction->commit();

                return true;
            }
        } catch (\Exception $e) {
            $transaction->rollBack();
            throw $e;
        }

        Db::rollbackStackedTransaction();

        return false;
    }

    /**
     * @param int $id
     */
    public function deleteDiscountById($id)
    {
        $record = DiscountRecord::findOne($id);

        if ($record) {
            $record->delete();
        }
    }

    public function clearCouponUsageHistory($id)
    {
        $discount = $this->getDiscountById($id);

        if ($discount) {
            CustomerDiscountUseRecord::deleteAll(['discountId' => $discount->id]);

            if ($discount->code) {
                $discount = DiscountRecord::find()->where(['code' => $discount->code])->one();

                if ($discount) {
                    $discount->totalUses = 0;
                    $discount->save();
                }
            }
        }
    }

    /**
     * @param int $id
     *
     * @return Discount|null
     */
    public function getDiscountById($id)
    {
        $result = DiscountRecord::findOne($id);

        if ($result) {
            return new Discount($result);
        }

        return null;
    }

    /**
     * @param $ids
     *
     * @return bool
     */
    public function reorderDiscounts($ids)
    {
        foreach ($ids as $sortOrder => $id) {
            Craft::$app->getDb()->createCommand()->update('commerce_discounts',
                ['sortOrder' => $sortOrder + 1], ['id' => $id]);
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
            $record->updateCounters(['totalUses' => 1]);
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
                $customerDiscountUseRecord->saveCounters(['uses' => 1]);
            }
        }
    }

    // Private Methods
    // =========================================================================

    /**
     * Creates a Discount with attributes from a DiscountRecord.
     *
     * @param DiscountRecord|null $record
     *
     * @return Discount|null
     */
    private function _createDiscountFromDiscountRecord(DiscountRecord $record = null)
    {
        if (!$record) {
            return null;
        }

        return new Discount($record->toArray([
            'name',
            'description',
            'code',
            'perUserLimit',
            'perEmailLimit',
            'totalUseLimit',
            'totalUses',
            'dateFrom',
            'dateTo',
            'purchaseTotal',
            'purchaseQty',
            'maxPurchaseQty',
            'baseDiscount',
            'perItemDiscount',
            'percentDiscount',
            'excludeOnSale',
            'freeShipping',
            'allGroups',
            'allProducts',
            'allProductTypes',
            'enabled',
            'stopProcessing',
            'sortOrder'
        ]));
    }
}
