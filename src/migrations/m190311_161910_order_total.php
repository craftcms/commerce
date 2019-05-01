<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\migrations;

use craft\db\Migration;

/**
 * m190311_161910_order_total migration.
 */
class m190311_161910_order_total extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
            $this->addColumn('{{%commerce_orders}}', 'total', $this->decimal(14, 4)->defaultValue(0));
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m190311_161910_order_total cannot be reverted.\n";
        return false;
    }
}
