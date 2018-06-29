<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\migrations;

use craft\db\Migration;

/**
 * m180217_130000_sale_migration migration.
 */
class m180217_130000_sale_migration extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->alterColumn('{{%commerce_sales}}', 'discountType', $this->string());

        $this->update('{{%commerce_sales}}', ['discountType' => 'byPercent'], ['discountType' => 'percent']);
        $this->update('{{%commerce_sales}}', ['discountType' => 'byFlat'], ['discountType' => 'flat']);

        $this->renameColumn('{{%commerce_sales}}', 'discountType', 'apply');
        $this->renameColumn('{{%commerce_sales}}', 'discountAmount', 'applyAmount');

        $this->alterColumn('{{%commerce_sales}}', 'apply', $this->enum('apply', ['toPercent', 'toFlat', 'byPercent', 'byFlat'])->notNull());

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m180217_130000_sale_migration cannot be reverted.\n";
        return false;
    }
}
