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
        $this->addColumn(Table::SHIPPINGZONES, 'countryCode', $this->string()->after('isCountryBased'));
        $this->addColumn(Table::TAXZONES, 'countryCode', $this->string()->after('isCountryBased'));
        $this->addColumn(Table::SHIPPINGZONES, 'countries', $this->text()->after('countryCode'));
        $this->addColumn(Table::SHIPPINGZONES, 'administrativeAreas', $this->text()->after('countryCode'));
        $this->addColumn(Table::TAXZONES, 'countries', $this->text()->after('countryCode'));
        $this->addColumn(Table::TAXZONES, 'administrativeAreas', $this->text()->after('countryCode'));

        /*
         * Orders
         */

        // Move the customerId to a temporary column, and relate the new customerId FK to the user element
        $this->dropIndexIfExists(Table::ORDERS, ['customerId']);
        $this->dropForeignKeyIfExists(Table::ORDERS, ['customerId']);
        $this->renameColumn(Table::ORDERS, 'customerId', 'v3CustomerId'); // move the data
        $this->addColumn(Table::ORDERS, 'customerId', $this->integer()->after('v3CustomerId'));
        $this->createIndex(null, Table::ORDERS, 'customerId', false);
        $this->addForeignKey(null, Table::ORDERS, ['customerId'], CraftTable::ELEMENTS, ['id'], 'SET NULL');

        // Move the billingAddressId to a temporary column, and relate the new billingAddressId FK to the address element
        $this->dropForeignKeyIfExists(Table::ORDERS, ['billingAddressId']);
        $this->dropIndexIfExists(Table::ORDERS, ['billingAddressId']);
        $this->renameColumn(Table::ORDERS, 'billingAddressId', 'v3BillingAddressId');  // move the data
        $this->addColumn(Table::ORDERS, 'billingAddressId', $this->integer()->after('v3BillingAddressId'));
        $this->addForeignKey(null, Table::ORDERS, ['billingAddressId'], CraftTable::ELEMENTS, ['id'], 'SET NULL');

        // Move the shippingAddressId to a temporary column, and relate the new shippingAddressId FK to the address element
        $this->dropForeignKeyIfExists(Table::ORDERS, ['shippingAddressId']);
        $this->dropIndexIfExists(Table::ORDERS, ['shippingAddressId']);
        $this->renameColumn(Table::ORDERS, 'shippingAddressId', 'v3ShippingAddressId');  // move the data
        $this->addColumn(Table::ORDERS, 'shippingAddressId', $this->integer()->after('v3ShippingAddressId'));
        $this->addForeignKey(null, Table::ORDERS, ['shippingAddressId'], CraftTable::ELEMENTS, ['id'], 'SET NULL');


        // Move the estimatedBillingAddressId to a temporary column, and relate the new estimatedBillingAddressId FK to the address element
        $this->dropForeignKeyIfExists(Table::ORDERS, ['estimatedBillingAddressId']);
        $this->dropIndexIfExists(Table::ORDERS, ['estimatedBillingAddressId']);
        $this->renameColumn(Table::ORDERS, 'estimatedBillingAddressId', 'v3EstimatedBillingAddressId');  // move the data
        $this->addColumn(Table::ORDERS, 'estimatedBillingAddressId', $this->integer()->after('v3EstimatedBillingAddressId'));
        $this->addForeignKey(null, Table::ORDERS, ['estimatedBillingAddressId'], CraftTable::ELEMENTS, ['id'], 'SET NULL');

        // Move the estimatedShippingAddressId to a temporary column, and relate the new estimatedShippingAddressId FK to the address element
        $this->dropForeignKeyIfExists(Table::ORDERS, ['estimatedShippingAddressId']);
        $this->dropIndexIfExists(Table::ORDERS, ['estimatedShippingAddressId']);
        $this->renameColumn(Table::ORDERS, 'estimatedShippingAddressId', 'v3EstimatedShippingAddressId'); // move the data
        $this->addColumn(Table::ORDERS, 'estimatedShippingAddressId', $this->integer()->after('v3EstimatedShippingAddressId'));
        $this->addForeignKey(null, Table::ORDERS, ['estimatedShippingAddressId'], CraftTable::ELEMENTS, ['id'], 'SET NULL');

        /*
         * Customers
         */

        // Move the userId and ID to a temporary column, add the customerId column.
        $this->dropAllForeignKeysToTable(Table::CUSTOMERS);
        $this->dropIndexIfExists(Table::CUSTOMERS, ['id']);
        $this->dropForeignKeyIfExists(Table::CUSTOMERS, ['id']);
        $this->renameColumn(Table::CUSTOMERS, 'id', 'v3Id'); // move the data
        $this->dropIndexIfExists(Table::CUSTOMERS, ['userId']);
        $this->dropForeignKeyIfExists(Table::CUSTOMERS, ['userId']);
        $this->dropIndexIfExists(Table::CUSTOMERS, ['userId']);
        $this->renameColumn(Table::CUSTOMERS, 'userId', 'v3UserId'); // move the data

        // Add the new primary customerId column with will share the same ID the user element ID
        $this->addColumn(Table::CUSTOMERS, 'customerId', $this->integer());
        $this->createIndex(null, Table::CUSTOMERS, 'customerId', true);
        $this->addForeignKey(null, Table::CUSTOMERS, ['customerId'], CraftTable::ELEMENTS, ['id'], 'SET NULL');

        /**
         * Customer Discount Uses
         */
        $this->dropIndexIfExists(Table::CUSTOMER_DISCOUNTUSES, ['customerId']);
        $this->dropForeignKeyIfExists(Table::CUSTOMER_DISCOUNTUSES, ['customerId']);
        $this->renameColumn(Table::CUSTOMER_DISCOUNTUSES, 'customerId', 'v3CustomerId'); // move the data
        $this->addColumn(Table::CUSTOMER_DISCOUNTUSES, 'customerId', $this->integer());
        $this->createIndex(null, Table::CUSTOMER_DISCOUNTUSES, 'customerId', false);
        $this->addForeignKey(null, Table::ORDERS, ['customerId'], CraftTable::ELEMENTS, ['id'], 'CASCADE', 'CASCADE');

        /**
         * Payment Sources
         */
        $this->dropIndexIfExists(Table::PAYMENTSOURCES, ['customerId']);
        $this->dropForeignKeyIfExists(Table::PAYMENTSOURCES, ['customerId']);
        $this->renameColumn(Table::PAYMENTSOURCES, 'userId', 'customerId');
        $this->addForeignKey(null, Table::PAYMENTSOURCES, ['customerId'], CraftTable::ELEMENTS, ['id'], 'CASCADE');

        /**
         * Order Histories
         */
        $this->dropIndexIfExists(Table::ORDERHISTORIES, ['customerId']);
        $this->dropForeignKeyIfExists(Table::ORDERHISTORIES, ['customerId']);
        $this->renameColumn(Table::ORDERHISTORIES, 'customerId', 'v3CustomerId'); // move the data
        $this->addColumn(Table::ORDERHISTORIES, 'userId', $this->integer());
        $this->createIndex(null, Table::ORDERHISTORIES, 'userId', false);
        $this->addForeignKey(null, Table::ORDERHISTORIES, ['userId'], CraftTable::ELEMENTS, ['id'], 'CASCADE', 'CASCADE');

        // Add new Store table
        if (!Craft::$app->getDb()->tableExists(Table::STORES)) {
            $this->createTable(Table::STORES, [
                'id' => $this->primaryKey(),
                'locationAddressId' => $this->integer(),
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
