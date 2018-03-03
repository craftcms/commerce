<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\migrations;

use craft\db\Migration;

/**
 * m180221_130000_sale_fixSort migration.
 */
class m180221_130000_sale_fixSort extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        if (!$this->db->columnExists('{{%commerce_sales}}', 'sortOrder')) {
            $this->addColumn('{{%commerce_sales}}', 'sortOrder', $this->integer());
        }

        if (!$this->db->columnExists('{{%commerce_sales}}', 'ignorePrevious')) {
            $this->addColumn('{{%commerce_sales}}', 'ignorePrevious', $this->boolean());
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m180221_130000_sale_fixSort cannot be reverted.\n";
        return false;
    }
}
