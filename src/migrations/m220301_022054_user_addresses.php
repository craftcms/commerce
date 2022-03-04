<?php

namespace craft\commerce\migrations;

use Craft;
use craft\commerce\db\Table;
use craft\db\Table as CraftTable;
use craft\db\Migration;

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

        /**
         * Order Addresses
         */
        $this->addColumn(Table::ORDERS, 'sourceShippingAddressId', $this->integer()->after('estimatedShippingAddressId')); // no need for index as not queryable
        $this->addColumn(Table::ORDERS, 'sourceBillingAddressId', $this->integer()->after('estimatedBillingAddressId')); // no need for index as not queryable

        /**
         * Zones
         */
        $this->renameColumn(Table::SHIPPINGZONES, 'isCountryBased', 'v3isCountryBased');
        $this->renameColumn(Table::TAXZONES, 'isCountryBased', 'v3isCountryBased');
        $this->renameColumn(Table::SHIPPINGZONES, 'zipCodeConditionFormula', 'v3zipCodeConditionFormula');
        $this->renameColumn(Table::TAXZONES, 'zipCodeConditionFormula', 'v3zipCodeConditionFormula');
        $this->addColumn(Table::TAXZONES, 'condition', $this->text()->after('id'));
        $this->addColumn(Table::SHIPPINGZONES, 'condition', $this->text()->after('id'));

        /*
         * Orders
         */
        // Move the customerId to a temporary column, and relate the new customerId FK to the user element
        $this->dropForeignKeyIfExists(Table::ORDERS, ['customerId']);
        $this->dropIndexIfExists(Table::ORDERS, ['customerId']);
        $this->renameColumn(Table::ORDERS, 'customerId', 'v3customerId'); // move the data
        $this->addColumn(Table::ORDERS, 'customerId', $this->integer()->after('v3customerId'));
        $this->createIndex(null, Table::ORDERS, 'customerId', false);
        $this->addForeignKey(null, Table::ORDERS, ['customerId'], CraftTable::ELEMENTS, ['id'], 'SET NULL');

        // Move the billingAddressId to a temporary column, and relate the new billingAddressId FK to the address element
        $this->dropForeignKeyIfExists(Table::ORDERS, ['billingAddressId']);
        $this->dropIndexIfExists(Table::ORDERS, ['billingAddressId']);
        $this->renameColumn(Table::ORDERS, 'billingAddressId', 'v3billingAddressId');  // move the data
        $this->addColumn(Table::ORDERS, 'billingAddressId', $this->integer()->after('v3billingAddressId'));
        $this->addForeignKey(null, Table::ORDERS, ['billingAddressId'], CraftTable::ELEMENTS, ['id'], 'SET NULL');

        // Move the shippingAddressId to a temporary column, and relate the new shippingAddressId FK to the address element
        $this->dropForeignKeyIfExists(Table::ORDERS, ['shippingAddressId']);
        $this->dropIndexIfExists(Table::ORDERS, ['shippingAddressId']);
        $this->renameColumn(Table::ORDERS, 'shippingAddressId', 'v3shippingAddressId');  // move the data
        $this->addColumn(Table::ORDERS, 'shippingAddressId', $this->integer()->after('v3shippingAddressId'));
        $this->addForeignKey(null, Table::ORDERS, ['shippingAddressId'], CraftTable::ELEMENTS, ['id'], 'SET NULL');

        // Move the estimatedBillingAddressId to a temporary column, and relate the new estimatedBillingAddressId FK to the address element
        $this->dropForeignKeyIfExists(Table::ORDERS, ['estimatedBillingAddressId']);
        $this->dropIndexIfExists(Table::ORDERS, ['estimatedBillingAddressId']);
        $this->renameColumn(Table::ORDERS, 'estimatedBillingAddressId', 'v3estimatedBillingAddressId');  // move the data
        $this->addColumn(Table::ORDERS, 'estimatedBillingAddressId', $this->integer()->after('v3estimatedBillingAddressId'));
        $this->addForeignKey(null, Table::ORDERS, ['estimatedBillingAddressId'], CraftTable::ELEMENTS, ['id'], 'SET NULL');

        // Move the estimatedShippingAddressId to a temporary column, and relate the new estimatedShippingAddressId FK to the address element
        $this->dropForeignKeyIfExists(Table::ORDERS, ['estimatedShippingAddressId']);
        $this->dropIndexIfExists(Table::ORDERS, ['estimatedShippingAddressId']);
        $this->renameColumn(Table::ORDERS, 'estimatedShippingAddressId', 'v3estimatedShippingAddressId'); // move the data
        $this->addColumn(Table::ORDERS, 'estimatedShippingAddressId', $this->integer()->after('v3estimatedShippingAddressId'));
        $this->addForeignKey(null, Table::ORDERS, ['estimatedShippingAddressId'], CraftTable::ELEMENTS, ['id'], 'SET NULL');

        /*
         * Customers
         */
        // Move the userId and ID to a temporary column, add the customerId column.
        $this->dropIndexIfExists(Table::CUSTOMERS, ['id']);
        $this->dropForeignKeyIfExists(Table::CUSTOMERS, ['id']);
        $this->dropForeignKeyIfExists(Table::CUSTOMERS, ['primaryShippingAddressId']);
        $this->dropForeignKeyIfExists(Table::CUSTOMERS, ['primaryBillingAddressId']);
        $this->renameColumn(Table::CUSTOMERS, 'id', 'v3id'); // move the data
        $this->dropIndexIfExists(Table::CUSTOMERS, ['userId']);
        $this->dropForeignKeyIfExists(Table::CUSTOMERS, ['userId']);
        $this->dropIndexIfExists(Table::CUSTOMERS, ['userId']);
        $this->renameColumn(Table::CUSTOMERS, 'userId', 'v3userId'); // move the data
        $this->addForeignKey(null, Table::CUSTOMERS, ['primaryBillingAddressId'], CraftTable::ELEMENTS, ['id'], 'SET NULL');
        $this->addForeignKey(null, Table::CUSTOMERS, ['primaryShippingAddressId'], CraftTable::ELEMENTS, ['id'], 'SET NULL');

        // Add the new primary customerId column with will share the same ID the user element ID
        $this->addColumn(Table::CUSTOMERS, 'customerId', $this->integer());
        $this->createIndex(null, Table::CUSTOMERS, 'customerId', true);
        $this->addForeignKey(null, Table::CUSTOMERS, ['customerId'], CraftTable::ELEMENTS, ['id'], 'SET NULL');

        /**
         * Customer Discount Uses
         */
        $this->dropAllForeignKeysToTable(Table::CUSTOMER_DISCOUNTUSES);
        $this->dropIndexIfExists(Table::CUSTOMER_DISCOUNTUSES, ['customerId', 'discountId']);
        $this->dropForeignKeyIfExists(Table::CUSTOMER_DISCOUNTUSES, ['customerId']);
        $this->renameColumn(Table::CUSTOMER_DISCOUNTUSES, 'customerId', 'v3customerId'); // move the data
        $this->alterColumn(Table::CUSTOMER_DISCOUNTUSES, 'v3customerId', $this->integer()->null());
        $this->addColumn(Table::CUSTOMER_DISCOUNTUSES, 'customerId', $this->integer()->notNull());
        $this->addForeignKey(null, Table::CUSTOMER_DISCOUNTUSES, ['customerId'], CraftTable::ELEMENTS, ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, Table::CUSTOMER_DISCOUNTUSES, ['customerId'], CraftTable::ELEMENTS, ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, Table::CUSTOMER_DISCOUNTUSES, ['discountId'], Table::DISCOUNTS, ['id'], 'CASCADE', 'CASCADE');
        $this->createIndex(null, Table::CUSTOMER_DISCOUNTUSES, ['customerId', 'discountId'], true);
        $this->createIndex(null, Table::CUSTOMER_DISCOUNTUSES, 'discountId', false);

        /**
         * Payment Sources
         */
        $this->dropIndexIfExists(Table::PAYMENTSOURCES, ['userId']);
        $this->dropForeignKeyIfExists(Table::PAYMENTSOURCES, ['userId']);
        $this->renameColumn(Table::PAYMENTSOURCES, 'userId', 'customerId'); // was already a user ID
        $this->addForeignKey(null, Table::PAYMENTSOURCES, ['customerId'], CraftTable::ELEMENTS, ['id'], 'CASCADE');

        /**
         * Order Histories
         */
        $this->dropIndexIfExists(Table::ORDERHISTORIES, ['customerId']);
        $this->dropForeignKeyIfExists(Table::ORDERHISTORIES, ['customerId']);
        $this->renameColumn(Table::ORDERHISTORIES, 'customerId', 'v3customerId'); // move the data
        $this->alterColumn(Table::ORDERHISTORIES, 'v3customerId', $this->integer()->null());
        $this->addColumn(Table::ORDERHISTORIES, 'userId', $this->integer()->null());
        $this->createIndex(null, Table::ORDERHISTORIES, 'userId', false);
        $this->addForeignKey(null, Table::ORDERHISTORIES, ['userId'], CraftTable::ELEMENTS, ['id'], 'CASCADE', 'CASCADE');

        // Add new Store table
        if (!Craft::$app->getDb()->tableExists(Table::STORES)) {
            $this->createTable(Table::STORES, [
                'id' => $this->primaryKey(),
                'locationAddressId' => $this->integer(),
                'enabledCountries' => $this->text(),
                'enabledAdministrativeAreas' => $this->text(),
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
