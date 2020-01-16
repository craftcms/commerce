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
 * m191015_194704_add_description_to_orderStatus migration.
 */
class m191015_194704_add_description_to_orderStatus extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->addColumn('{{%commerce_orderstatuses}}', 'description', $this->string()->after('color'));
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m191015_194704_add_description_to_orderStatus cannot be reverted.\n";
        return false;
    }
}
