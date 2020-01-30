<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\migrations;

use craft\commerce\elements\Order;
use craft\commerce\elements\Product;
use craft\commerce\elements\Variant;
use craft\commerce\fields\Customer;
use craft\commerce\fields\Products;
use craft\commerce\widgets\Orders;
use craft\commerce\widgets\Revenue;
use craft\db\Migration;
use craft\helpers\MigrationHelper;

/**
 * m160531_154500_craft3_upgrade migration.
 */
class m160531_154500_craft3_upgrade extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        // Update all the Element references
        $this->update('{{%elements}}', ['type' => Order::class], ['type' => 'Commerce_Order']);
        $this->update('{{%elements}}', ['type' => Product::class], ['type' => 'Commerce_Product']);

        $this->update('{{%elements}}', ['type' => Variant::class], ['type' => 'Commerce_Variant']);

        // Fields
        $this->update('{{%fields}}', ['type' => 'craft\commerce\fields\Customer'], ['type' => 'Commerce_Customer']);
        $this->update('{{%fields}}', ['type' => Products::class], ['type' => 'Commerce_Products']);

        // Widgets
        $this->update('{{%widgets}}', ['type' => Orders::class], ['type' => 'Commerce_Orders']);
        $this->update('{{%widgets}}', ['type' => Revenue::class], ['type' => 'Commerce_Revenue']);

        // Before messing with columns, it's much safer to drop all the FKs and indexes
        MigrationHelper::dropAllForeignKeysOnTable('{{%commerce_producttypes_i18n}}');
        MigrationHelper::dropAllIndexesOnTable('{{%commerce_producttypes_i18n}}');

        // Drop the old locale FK column and rename the new siteId FK column
        $this->dropColumn('{{%commerce_producttypes_i18n}}', 'locale');
        MigrationHelper::renameColumn('{{%commerce_producttypes_i18n}}', 'locale__siteId', 'siteId', $this);

        // And then just recreate them.
        $this->createIndex($this->db->getIndexName('{{%commerce_producttypes_i18n}}', 'productTypeId,siteId', true), '{{%commerce_producttypes_i18n}}', 'productTypeId,siteId', true);
        $this->createIndex($this->db->getIndexName('{{%commerce_producttypes_i18n}}', 'siteId', false), '{{%commerce_producttypes_i18n}}', 'siteId', false);
        $this->addForeignKey($this->db->getForeignKeyName('{{%commerce_producttypes_i18n}}', 'siteId'), '{{%commerce_producttypes_i18n}}', 'siteId', '{{%sites}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey($this->db->getForeignKeyName('{{%commerce_producttypes_i18n}}', 'productTypeId'), '{{%commerce_producttypes_i18n}}', 'productTypeId', '{{%commerce_producttypes}}', 'id', 'CASCADE', null);

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m160531_154500_craft3_upgrade cannot be reverted.\n";

        return false;
    }
}
