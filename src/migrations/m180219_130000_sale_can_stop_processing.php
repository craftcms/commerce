<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\migrations;

use craft\db\Migration;

/**
 * m180219_130000_sale_can_stop_processing migration.
 */
class m180219_130000_sale_can_stop_processing extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->addColumn('{{%commerce_sales}}', 'stopProcessing', $this->boolean());

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m180219_130000_sale_can_stop_processing cannot be reverted.\n";
        return false;
    }
}
