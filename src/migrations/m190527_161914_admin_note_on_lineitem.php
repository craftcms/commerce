<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\migrations;

use craft\db\Migration;

/**
 * m190527_161914_admin_note_on_lineitem migration.
 */
class m190527_161914_admin_note_on_lineitem extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->addColumn('{{%commerce_lineitems}}', 'privateNote', $this->text());
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m190527_161914_admin_note_on_lineitem cannot be reverted.\n";
        return false;
    }
}
