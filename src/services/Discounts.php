<?php
namespace craft\commerce\services;

use craft\commerce\elements\Variant;
use craft\commerce\helpers\Db;
use craft\commerce\models\Discount;
use craft\commerce\models\LineItem;
use craft\commerce\records\CustomerDiscountUse as CustomerDiscountUseRecord;
use craft\commerce\records\Discount as DiscountRecord;
use craft\commerce\records\DiscountProduct as DiscountProductRecord;
use craft\commerce\records\DiscountProductType as DiscountProductTypeRecord;
use craft\commerce\records\DiscountUserGroup as DiscountUserGroupRecord;
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
    /**
     * @param array|\CDbCriteria $criteria
     *
     * @return Discount[]
     */
    public function getAllDiscounts($criteria = [])
    {
        $records = DiscountRecord::model()->findAll($criteria);

        return Discount::populateModels($records);
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
            $error = Craft::t('commerce', 'commerce', 'Coupon not valid');

            return false;
        }

        if (!$model->enabled) {
            $error = Craft::t('commerce', 'commerce', 'Discount is not available');

            return false;
        }

        if ($model->totalUseLimit > 0 && $model->totalUses >= $model->totalUseLimit) {
            $error = Craft::t('commerce', 'commerce', 'Discount use has reached it’s limit');

            return false;
        }

        $now = new DateTime();
        $from = $model->dateFrom;
        $to = $model->dateTo;
        if ($from && $from > $now || $to && $to < $now) {
            $error = Craft::t('commerce', 'commerce', 'Discount is out of date');

            return false;
        }

        if (!$model->allGroups) {
            $customer = Plugin::getInstance()->getCustomers()->getCustomerById($customerId);
            $user = $customer ? $customer->getUser() : null;
            $groupIds = $this->getCurrentUserGroupIds($user);
            if (!$user || !array_intersect($groupIds, $model->getGroupIds())) {
                $error = Craft::t('commerce', 'commerce', 'Discount is not allowed for the customer');

                return false;
            }
        }


        if ($customerId) {
            // The 'Per User Limit' can only be tracked against logged in users since guest customers are re-generated often
            if ($model->perUserLimit > 0 && !Craft::$app->getUser()->isLoggedIn()) {
                $error = Craft::t('commerce', 'commerce', 'Discount is limited to use by logged in users only.');

                return false;
            }

            $uses = CustomerDiscountUseRecord::model()->findByAttributes(['customerId' => $customerId, 'discountId' => $model->id]);
            if ($uses && $uses->uses >= $model->perUserLimit) {
                $error = Craft::t('commerce', 'commerce', 'You can not use this discount anymore');

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
                    $error = Craft::t('commerce', 'commerce', 'This coupon limited to {limit} uses.', [
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

        $result = DiscountRecord::model()->findByAttributes(['code' => $code]);

        if ($result) {
            return Discount::populateModel($result);
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
        $currentUser = $user ? $user : Craft::$app->getUser()->getUser();
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
        $event = new Event($this, ['lineItem' => $lineItem, 'discount' => $discount]);
        $this->onBeforeMatchLineItem($event);

        if (!$event->performAction) {
            return false;
        }

        return true;
    }

    /**
     * Before matching a lineitem
     * Event params: address(Address)
     *
     * @param \CEvent $event
     *
     * @throws \CException
     */
    public function onBeforeMatchLineItem(\CEvent $event)
    {
        $params = $event->params;
        if (empty($params['lineItem']) || !($params['lineItem'] instanceof LineItem)) {
            throw new Exception('onBeforeMatchLineItem event requires "lineItem" param with LineItem instance');
        }

        if (empty($params['discount']) || !($params['discount'] instanceof LineItem)) {
            throw new Exception('onBeforeMatchLineItem event requires "discount" param with Discount instance');
        }

        $this->raiseEvent('onBeforeMatchLineItem', $event);
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
            $record = DiscountRecord::model()->findById($model->id);

            if (!$record) {
                throw new Exception(Craft::t('commerce', 'commerce', 'No discount exists with the ID “{id}”', ['id' => $model->id]));
            }
        } else {
            $record = new DiscountRecord();
        }

        $fields = ['id', 'name', 'description', 'dateFrom', 'dateTo', 'enabled', 'stopProcessing', 'purchaseTotal', 'purchaseQty', 'maxPurchaseQty', 'baseDiscount', 'perItemDiscount', 'percentDiscount', 'freeShipping', 'excludeOnSale', 'perUserLimit', 'perEmailLimit', 'totalUseLimit'];
        foreach ($fields as $field) {
            $record->$field = $model->$field;
        }

        $record->sortOrder = $model->sortOrder ? $model->sortOrder : 999;
        $record->code = $model->code ?: null;

        $record->allGroups = $model->allGroups = empty($groups);
        $record->allProductTypes = $model->allProductTypes = empty($productTypes);
        $record->allProducts = $model->allProducts = empty($products);

        $record->validate();
        $model->addErrors($record->getErrors());

        Db::beginStackedTransaction();
        try {
            if (!$model->hasErrors()) {
                $record->save(false);
                $model->id = $record->id;

                DiscountUserGroupRecord::model()->deleteAllByAttributes(['discountId' => $model->id]);
                DiscountProductRecord::model()->deleteAllByAttributes(['discountId' => $model->id]);
                DiscountProductTypeRecord::model()->deleteAllByAttributes(['discountId' => $model->id]);

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

                Db::commitStackedTransaction();

                return true;
            }
        } catch (\Exception $e) {
            Db::rollbackStackedTransaction();
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
        DiscountRecord::model()->deleteByPk($id);
    }

    public function clearCouponUsageHistory($id)
    {
        $discount = $this->getDiscountById($id);

        if ($discount) {
            CustomerDiscountUseRecord::model()->deleteAllByAttributes(['discountId' => $discount->id]);

            if ($discount->code) {
                $discount = DiscountRecord::model()->findByAttributes(['code' => $discount->code]);

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
        $result = DiscountRecord::model()->findById($id);

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
        $record = DiscountRecord::model()->findByAttributes(['code' => $order->couponCode]);
        if (!$record || !$record->id) {
            return;
        }

        if ($record->totalUseLimit) {
            $record->saveCounters(['totalUses' => 1]);
        }

        if ($record->perUserLimit && $order->customerId) {

            $customerDiscountUseRecord = CustomerDiscountUseRecord::model()->findByAttributes(['customerId' => $order->customerId, 'discountId' => $record->id]);

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

}
