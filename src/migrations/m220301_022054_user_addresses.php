<?php

namespace craft\commerce\migrations;

use Craft;
use craft\db\Migration;
use craft\db\Table as CraftTable;

/**
 * m220301_022054_user_addresses migration.
 */
class m220301_022054_user_addresses extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $isPgsql = $this->db->getIsPgsql();

        /**
         * Order Addresses
         */
        $this->addColumn('{{%commerce_orders}}', 'sourceShippingAddressId', $this->integer()->after('estimatedShippingAddressId')); // no need for index as not queryable
        $this->addColumn('{{%commerce_orders}}', 'sourceBillingAddressId', $this->integer()->after('estimatedBillingAddressId')); // no need for index as not queryable

        /**
         * Zones
         */
        $this->renameColumn('{{%commerce_taxzones}}', 'isCountryBased', 'v3isCountryBased');
        $this->renameColumn('{{%commerce_shippingzones}}', 'isCountryBased', 'v3isCountryBased');
        $this->renameColumn('{{%commerce_taxzones}}', 'zipCodeConditionFormula', 'v3zipCodeConditionFormula');
        $this->renameColumn('{{%commerce_shippingzones}}', 'zipCodeConditionFormula', 'v3zipCodeConditionFormula');
        $this->addColumn('{{%commerce_taxzones}}', 'condition', $this->text());
        $this->addColumn('{{%commerce_shippingzones}}', 'condition', $this->text());

        /*
         * Orders
         */
        // Move the customerId to a temporary column, and relate the new customerId FK to the user element
        $this->dropForeignKeyIfExists('{{%commerce_orders}}', ['customerId']);
        $this->dropIndexIfExists('{{%commerce_orders}}', ['customerId']);
        $this->renameColumn('{{%commerce_orders}}', 'customerId', 'v3customerId'); // move the data
        
        $this->addColumn('{{%commerce_orders}}', 'customerId', $this->integer());
        $this->createIndex(null, '{{%commerce_orders}}', 'customerId', false);
        $this->addForeignKey(null, '{{%commerce_orders}}', ['customerId'], CraftTable::ELEMENTS, ['id'], 'SET NULL');

        // Move the billingAddressId to a temporary column, and relate the new billingAddressId FK to the address element
        $this->dropForeignKeyIfExists('{{%commerce_orders}}', ['billingAddressId']);
        $this->dropIndexIfExists('{{%commerce_orders}}', ['billingAddressId']);
        $this->renameColumn('{{%commerce_orders}}', 'billingAddressId', 'v3billingAddressId');  // move the data
        
        $this->addColumn('{{%commerce_orders}}', 'billingAddressId', $this->integer());
        $this->addForeignKey(null, '{{%commerce_orders}}', ['billingAddressId'], CraftTable::ELEMENTS, ['id'], 'SET NULL');

        // Move the shippingAddressId to a temporary column, and relate the new shippingAddressId FK to the address element
        $this->dropForeignKeyIfExists('{{%commerce_orders}}', ['shippingAddressId']);
        $this->dropIndexIfExists('{{%commerce_orders}}', ['shippingAddressId']);
        $this->renameColumn('{{%commerce_orders}}', 'shippingAddressId', 'v3shippingAddressId');  // move the data
        
        $this->addColumn('{{%commerce_orders}}', 'shippingAddressId', $this->integer());
        $this->addForeignKey(null, '{{%commerce_orders}}', ['shippingAddressId'], CraftTable::ELEMENTS, ['id'], 'SET NULL');

        // Move the estimatedBillingAddressId to a temporary column, and relate the new estimatedBillingAddressId FK to the address element
        $this->dropForeignKeyIfExists('{{%commerce_orders}}', ['estimatedBillingAddressId']);
        $this->dropIndexIfExists('{{%commerce_orders}}', ['estimatedBillingAddressId']);
        $this->renameColumn('{{%commerce_orders}}', 'estimatedBillingAddressId', 'v3estimatedBillingAddressId');  // move the data
        
        $this->addColumn('{{%commerce_orders}}', 'estimatedBillingAddressId', $this->integer());
        $this->addForeignKey(null, '{{%commerce_orders}}', ['estimatedBillingAddressId'], CraftTable::ELEMENTS, ['id'], 'SET NULL');

        // Move the estimatedShippingAddressId to a temporary column, and relate the new estimatedShippingAddressId FK to the address element
        $this->dropForeignKeyIfExists('{{%commerce_orders}}', ['estimatedShippingAddressId']);
        $this->dropIndexIfExists('{{%commerce_orders}}', ['estimatedShippingAddressId']);
        $this->renameColumn('{{%commerce_orders}}', 'estimatedShippingAddressId', 'v3estimatedShippingAddressId'); // move the data
        
        $this->addColumn('{{%commerce_orders}}', 'estimatedShippingAddressId', $this->integer());
        $this->addForeignKey(null, '{{%commerce_orders}}', ['estimatedShippingAddressId'], CraftTable::ELEMENTS, ['id'], 'SET NULL');

        /*
         * Customers
         */
        // Move the userId and ID to a temporary column, add the customerId column.
        $this->dropForeignKeyIfExists('{{%commerce_customers}}', ['userId']);
        $this->dropIndexIfExists('{{%commerce_customers}}', ['userId']);
        $this->renameColumn('{{%commerce_customers}}', 'userId', 'v3userId'); // move the data
        $this->dropForeignKeyIfExists('{{%commerce_customers}}', ['primaryBillingAddressId']);
        $this->dropForeignKeyIfExists('{{%commerce_customers}}', ['primaryShippingAddressId']);
        $this->renameColumn('{{%commerce_customers}}', 'primaryBillingAddressId', 'v3primaryBillingAddressId'); // move the data
        $this->renameColumn('{{%commerce_customers}}', 'primaryShippingAddressId', 'v3primaryShippingAddressId'); // move the data
        $this->addColumn('{{%commerce_customers}}', 'primaryBillingAddressId', $this->integer());
        $this->addColumn('{{%commerce_customers}}', 'primaryShippingAddressId', $this->integer());
        $this->addColumn('{{%commerce_customers}}', 'customerId', $this->integer()->null());

        $this->addForeignKey(null, '{{%commerce_customers}}', ['primaryBillingAddressId'], CraftTable::ELEMENTS, ['id'], 'SET NULL');
        $this->addForeignKey(null, '{{%commerce_customers}}', ['primaryShippingAddressId'], CraftTable::ELEMENTS, ['id'], 'SET NULL');
        // Add the new primary customerId column with will share the same ID the user element ID
        //$this->addColumn('{{%commerce_customers}}', 'customerId', $this->integer());
        $this->addForeignKey(null, '{{%commerce_customers}}', ['customerId'], CraftTable::ELEMENTS, ['id'], 'CASCADE', 'CASCADE');
        $this->createIndex(null, '{{%commerce_customers}}', 'customerId', true);


        /**
         * Customer Discount Uses
         */
        $this->dropAllForeignKeysToTable('{{%commerce_customer_discountuses}}');
        $this->dropForeignKeyIfExists('{{%commerce_customer_discountuses}}', ['customerId']);
        $this->dropForeignKeyIfExists('{{%commerce_customer_discountuses}}', ['discountId']);
        $this->dropIndexIfExists('{{%commerce_customer_discountuses}}', ['customerId', 'discountId'], true);
        $this->dropIndexIfExists('{{%commerce_customer_discountuses}}', ['discountId']);
        $this->renameColumn('{{%commerce_customer_discountuses}}', 'customerId', 'v3customerId'); // move the data

        if ($isPgsql) {
            // Manually construct the SQL for Postgres
            // (see https://github.com/yiisoft/yii2/issues/12077)
            $this->execute('alter table {{%commerce_customer_discountuses}} alter column [[v3customerId]] type integer, alter column [[v3customerId]] drop not null');
        } else {
            $this->alterColumn('{{%commerce_customer_discountuses}}', 'v3customerId', $this->integer()->null());
        }

        $this->addColumn('{{%commerce_customer_discountuses}}', 'customerId', $this->integer());
        $this->addForeignKey(null, '{{%commerce_customer_discountuses}}', ['customerId'], CraftTable::ELEMENTS, ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, '{{%commerce_customer_discountuses}}', ['discountId'], '{{%commerce_discounts}}', ['id'], 'CASCADE', 'CASCADE');
        $this->createIndex(null, '{{%commerce_customer_discountuses}}', ['customerId', 'discountId'], true);
        $this->createIndex(null, '{{%commerce_customer_discountuses}}', 'discountId', false);

        /**
         * Payment Sources
         */
        $this->dropForeignKeyIfExists('{{%commerce_paymentsources}}', ['userId']);
        $this->dropIndexIfExists('{{%commerce_paymentsources}}', ['userId']);
        $this->renameColumn('{{%commerce_paymentsources}}', 'userId', 'customerId'); // was already a user ID
        $this->addForeignKey(null, '{{%commerce_paymentsources}}', ['customerId'], CraftTable::ELEMENTS, ['id'], 'CASCADE');

        /**
         * Order Histories
         */
        $this->dropForeignKeyIfExists('{{%commerce_orderhistories}}', ['customerId']);
        $this->dropIndexIfExists('{{%commerce_orderhistories}}', ['customerId']);
        $this->renameColumn('{{%commerce_orderhistories}}', 'customerId', 'v3customerId'); // move the data


        if ($isPgsql) {
            // Manually construct the SQL for Postgres
            // (see https://github.com/yiisoft/yii2/issues/12077)
            $this->execute('alter table {{%commerce_orderhistories}} alter column [[v3customerId]] type integer, alter column [[v3customerId]] drop not null');
        } else {
            $this->alterColumn('{{%commerce_orderhistories}}', 'v3customerId', $this->integer()->null());
        }

        $this->addColumn('{{%commerce_orderhistories}}', 'userId', $this->integer()->null());
        $this->addForeignKey(null, '{{%commerce_orderhistories}}', ['userId'], CraftTable::ELEMENTS, ['id'], 'CASCADE', 'CASCADE');
        $this->createIndex(null, '{{%commerce_orderhistories}}', 'userId', false);

        $this->addColumn('{{%commerce_addresses}}', 'v4addressId', $this->integer()->null());

        // Add new Store table
        if (!Craft::$app->getDb()->tableExists('{{%commerce_stores}}')) {
            $this->createTable('{{%commerce_stores}}', [
                'id' => $this->primaryKey(),
                'locationAddressId' => $this->integer(),
                'countries' => $this->text(),
                'marketAddressCondition' => $this->text(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m220222_134640_address_user_schema_changes cannot be reverted.\n";
        return false;
    }
}
