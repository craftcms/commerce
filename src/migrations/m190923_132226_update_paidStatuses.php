<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\migrations;

use Craft;
use craft\db\Migration;
use yii\db\Expression;

/**
 * m190923_132226_update_paidStatuses migration.
 */
class m190923_132226_update_paidStatuses extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->update('{{%commerce_orders}}', [
            'paidStatus' => 'overPaid',
        ], [
            'and',
            ['isCompleted' => true],
            ['>', 'totalPaid', 0],
            new Expression('[[totalPaid]] > [[totalPrice]]'),
        ], [], false);
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m190923_132226_update_paidStatuses cannot be reverted.\n";
        return false;
    }
}
