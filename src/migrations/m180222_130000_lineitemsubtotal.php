<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\migrations;

use craft\db\Migration;

/**
 * m180222_130000_lineitemsubtotal migration.
 */
class m180222_130000_lineitemsubtotal extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        if (!$this->db->columnExists('{{%commerce_lineitems}}', 'subtotal')) {
            $this->addColumn('{{%commerce_lineitems}}', 'subtotal', $this->decimal(14, 4)->notNull()->defaultValue(0)->unsigned());
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m180222_130000_lineitemsubtotal cannot be reverted.\n";
        return false;
    }
}
