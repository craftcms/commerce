<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\migrations;

use craft\db\Migration;

/**
 * m180220_130000_sale_can_ignore_previous migration.
 */
class m180220_130000_sale_can_ignore_previous extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->addColumn('{{%commerce_sales}}', 'ignorePrevious', $this->boolean());

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m180220_130000_sale_can_ignore_previous cannot be reverted.\n";
        return false;
    }
}
