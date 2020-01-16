<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\migrations;

use Craft;
use craft\db\Migration;

/**
 * m191018_183551_cc_replyTo_emails migration.
 */
class m191018_183551_cc_replyTo_emails extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->addColumn('{{%commerce_emails}}', 'cc', $this->string());
        $this->addColumn('{{%commerce_emails}}', 'replyTo', $this->string());
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m191018_183551_cc_replyTo_emails cannot be reverted.\n";
        return false;
    }
}
