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
 * m181221_120000_sort_order_for_plans migration.
 */
class m181221_120000_sort_order_for_plans extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->addColumn('{{%commerce_plans}}', 'sortOrder', $this->smallInteger()->unsigned()->after('dateArchived'));

        $planIds = (new Query())
            ->select(['id'])
            ->from(['{{%commerce_plans}}'])
            ->column();

        $counter = 1;

        foreach ($planIds as $planId) {
            $this->update('{{%commerce_plans}}', ['sortOrder' => $counter++], ['id' => $planId]);
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m181221_120000_sort_order_for_plans cannot be reverted.\n";
        return false;
    }
}
