<?php

namespace craft\commerce\migrations;

use craft\commerce\db\Table;
use craft\commerce\elements\conditions\addresses\DiscountAddressCondition;
use craft\commerce\elements\conditions\customers\DiscountCustomerCondition;
use craft\commerce\elements\conditions\orders\DiscountOrderCondition;
use craft\db\Migration;
use craft\helpers\Json;

/**
 * m230719_082348_discount_nullable_conditions migration.
 */
class m230719_082348_discount_nullable_conditions extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $orderCondition = new DiscountOrderCondition();
        $orderConditionConfig = Json::encode($orderCondition->getConfig());

        $this->update(Table::DISCOUNTS, ['orderCondition' => null], ['orderCondition' => $orderConditionConfig], [], false);

        $customerCondition = new DiscountCustomerCondition();
        $customerConditionConfig = Json::encode($customerCondition->getConfig());

        $this->update(Table::DISCOUNTS, ['customerCondition' => null], ['customerCondition' => $customerConditionConfig], [], false);

        $addressCondition = new DiscountAddressCondition();
        $addressConditionConfig = Json::encode($addressCondition->getConfig());

        $this->update(Table::DISCOUNTS, ['billingAddressCondition' => null], ['billingAddressCondition' => $addressConditionConfig], [], false);
        $this->update(Table::DISCOUNTS, ['shippingAddressCondition' => null], ['shippingAddressCondition' => $addressConditionConfig], [], false);

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m230719_082348_discount_nullable_conditions cannot be reverted.\n";
        return false;
    }
}
