<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\migrations;

use craft\db\Migration;
use craft\helpers\MigrationHelper;

/**
 * m190213_000858_discount_free_shipping_changes migration.
 */
class m190213_000858_discount_free_shipping_changes extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $table = '{{%commerce_discounts}}';

        // Rename the per product free shipping
        MigrationHelper::renameColumn($table, 'freeShipping', 'hasFreeShippingForMatchingItems', $this);

        // Add the order level free shipping
        $this->addColumn($table, 'hasFreeShippingForOrder', $this->boolean());
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m190213_000858_discount_free_shipping_changes cannot be reverted.\n";
        return false;
    }
}
