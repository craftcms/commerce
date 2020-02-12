<?php

namespace craft\commerce\migrations;

use Craft;
use craft\db\Migration;
use yii\db\Expression;

/**
 * m190523_150000_fix_lineItems_dateCreated migration.
 */
class m190523_150000_fix_lineItems_dateCreated extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->update('{{%commerce_lineitems}}', ['[[dateCreated]]' => new Expression('[[dateUpdated]]')], ['[[dateCreated]]' => null], [], false);
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m190523_150000_fix_lineItems_dateCreated cannot be reverted.\n";
        return false;
    }
}
