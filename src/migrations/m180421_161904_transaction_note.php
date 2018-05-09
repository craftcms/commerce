<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\migrations;

use craft\db\Migration;

/**
 * m180421_161904_transaction_note migration.
 */
class m180421_161904_transaction_note extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->addColumn('{{%commerce_transactions}}', 'note', $this->mediumText());

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m180421_161904_transaction_note cannot be reverted.\n";
        return false;
    }
}