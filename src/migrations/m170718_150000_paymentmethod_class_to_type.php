<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\migrations;

use craft\db\Migration;
use craft\helpers\MigrationHelper;

/**
 * m170718_150000_paymentmethod_class_to_type
 */
class m170718_150000_paymentmethod_class_to_type extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        MigrationHelper::renameColumn('{{%commerce_paymentmethods}}', 'class', 'type', $this);

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m170718_150000_paymentmethod_class_to_type cannot be reverted.\n";

        return false;
    }
}
