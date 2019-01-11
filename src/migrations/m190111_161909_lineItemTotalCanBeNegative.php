<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\migrations;

use craft\db\Migration;
use yii\db\Expression;

/**
 * m190111_161909_lineItemTotalCanBeNegative migration.
 */
class m190111_161909_lineItemTotalCanBeNegative extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $tableName = '{{%commerce_lineitems}}';

        if ($this->db->getIsPgsql()) {
            // Manually construct the SQL for Postgres
            // (see https://github.com/yiisoft/yii2/issues/12077)
            $this->execute('alter table {{%commerce_lineitems}} alter column [[total]] type numeric(14,4) NOT NULL DEFAULT 0');
        } else {
            $this->alterColumn('{{%commerce_lineitems}}', 'total', $this->decimal(14, 4)->notNull()->defaultValue(0));
        }
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m190111_161909_lineItemTotalCanBeNegative cannot be reverted.\n";
        return false;
    }
}
