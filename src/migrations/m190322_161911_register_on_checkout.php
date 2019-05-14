<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\migrations;

use craft\db\Migration;

/**
 * m190322_161911_register_on_checkout migration.
 */
class m190322_161911_register_on_checkout extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->addColumn('{{%commerce_orders}}', 'registerUserOnOrderComplete', $this->boolean());
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m190322_161911_register_on_checkout cannot be reverted.\n";
        return false;
    }
}
