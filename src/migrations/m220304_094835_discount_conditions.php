<?php

namespace craft\commerce\migrations;

use Craft;
use craft\commerce\db\Table;
use craft\commerce\elements\conditions\addresses\DiscountAddressCondition;
use craft\commerce\elements\conditions\customers\DiscountCustomerCondition;
use craft\commerce\elements\conditions\orders\DiscountOrderCondition;
use craft\commerce\elements\conditions\users\DiscountGroupConditionRule;
use craft\db\Migration;
use craft\db\Query;
use craft\helpers\Json;

/**
 * m220304_094835_discount_conditions migration.
 */
class m220304_094835_discount_conditions extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $orderCondition = new DiscountOrderCondition();
        // TODO migrate some discount conditions to the builder
        $customerCondition = new DiscountCustomerCondition();
        // TODO migrate the user groups condition to the builder
        $shippingAddressCondition = new DiscountAddressCondition();
        $billingAddressCondition = new DiscountAddressCondition();

        $discounts = (new \craft\db\Query())
            ->select(['id', 'userGroupsCondition'])
            ->from(['{{%commerce_discounts}}'])
            ->indexBy('id')
            ->all();

        foreach ($discounts as $id => $discount) {

            /**
             * Order condition
             */
            $this->update('{{%commerce_discounts}}', [
                'orderCondition' => Json::encode($orderCondition->getConfig()),
            ], ['id' => $id]);


            /**
             * User condition
             */
            $discountsUserGroupIds = (new Query())->select(['dug.userGroupId'])
                ->from('{{%commerce_discounts}} discounts')
                ->leftJoin('{{%commerce_discount_usergroups}} dug', '[[dug.discountId]] = [[discounts.id]]')
                ->where(['discounts.id' => $id])
                ->column();

            $userRules = [];
            if ($discount['userGroupsCondition'] == 'userGroupsAnyOrNone') {
                // do nothing
            } elseif ($discount['userGroupsCondition'] == 'userGroupsIncludeAll') {
                foreach ($discountsUserGroupIds as $userGroupId) {
                    $conditionRule = new DiscountGroupConditionRule();
                    $conditionRule->setValues($userGroupId);
                    $conditionRule->operator = 'in';
                    $userRules[] = $conditionRule;
                }
            } elseif ($discount['userGroupsCondition'] == 'userGroupsIncludeAny') {
                $conditionRule = new DiscountGroupConditionRule();
                $conditionRule->setValues($discountsUserGroupIds);
                $conditionRule->operator = 'in';
                $userRules[] = $conditionRule;
            } elseif ($discount['userGroupsCondition'] == 'userGroupsExcludeAny') {
                $conditionRule = new DiscountGroupConditionRule();
                $conditionRule->setValues($discountsUserGroupIds);
                $conditionRule->operator = 'ni';
                $userRules[] = $conditionRule;
            }
            $customerCondition->setConditionRules($userRules);
            $this->update('{{%commerce_discounts}}', [
                'customerCondition' => Json::encode($customerCondition->getConfig()),
            ], ['id' => $id]);

            /**
             * Shipping Address condition
             */
            $this->update('{{%commerce_discounts}}', [
                'shippingAddressCondition' => Json::encode($shippingAddressCondition->getConfig()),
            ], ['id' => $id]);

            /**
             * Billing Address condition
             */
            $this->update('{{%commerce_discounts}}', [
                'billingAddressCondition' => Json::encode($billingAddressCondition->getConfig()),
            ], ['id' => $id]);
        }

        $this->dropTableIfExists('{{%commerce_discount_usergroups}}');
        $this->dropColumn('{{%commerce_discounts}}', 'userGroupsCondition');
        
        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m220304_094835_discount_conditions cannot be reverted.\n";
        return false;
    }
}
