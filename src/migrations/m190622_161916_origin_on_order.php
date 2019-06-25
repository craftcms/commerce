<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\migrations;

use craft\db\Migration;

/**
 * m190622_161916_origin_on_order migration.
 */
class m190622_161916_origin_on_order extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->addColumn('{{%commerce_orders}}', 'orderOrigin', $this->string());
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m190622_161916_origin_on_order cannot be reverted.\n";
        return false;
    }
}
