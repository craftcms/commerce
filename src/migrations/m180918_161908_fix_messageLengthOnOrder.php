<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\migrations;

use craft\db\Migration;

/**
 * m180918_161908_fix_messageLengthOnOrder migration.
 */
class m180918_161908_fix_messageLengthOnOrder extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->alterColumn('{{%commerce_orders}}', 'message', $this->text());
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m180918_161908_fix_messageLengthOnOrder cannot be reverted.\n";
        return false;
    }
}
