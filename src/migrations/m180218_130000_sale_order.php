<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\migrations;

use craft\db\Migration;
use craft\db\Query;

/**
 * m180218_130000_sale_order migration.
 */
class m180218_130000_sale_order extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->addColumn('{{%commerce_sales}}', 'sortOrder', $this->tinyInteger()->unsigned());

        $sales = (new Query())
            ->select(['id'])
            ->from(['{{%commerce_sales}}'])
            ->column();

        $count = 1;

        foreach ($sales as $sale) {
            $count++;
            $this->update('{{%commerce_sales}}', ['sortOrder' => $count], ['id' => $sale]);
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m180218_130000_sale_order cannot be reverted.\n";
        return false;
    }
}
