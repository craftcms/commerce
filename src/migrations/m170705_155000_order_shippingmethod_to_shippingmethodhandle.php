<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\migrations;

use craft\commerce\records\Order;
use craft\db\Migration;
use craft\helpers\MigrationHelper;

/**
 * m170705_155000_order_shippingmethod_to_shippingmethodhandle
 */
class m170705_155000_order_shippingmethod_to_shippingmethodhandle extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        MigrationHelper::renameColumn(Order::tableName(), 'shippingMethod', 'shippingMethodHandle', $this);

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m170705_155000_order_shippingmethod_to_shippingmethodhandle cannot be reverted.\n";

        return false;
    }
}
