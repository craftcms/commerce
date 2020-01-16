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
 * m200102_185839_remove_totalUses_and_totalUseLimit migration.
 */
class m200102_185839_remove_totalUses_and_totalUseLimit extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->dropColumn('{{%commerce_discounts}}', '[[totalUses]]');
        $this->dropColumn('{{%commerce_discounts}}', '[[totalUseLimit]]');
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m200102_185839_remove_totalUses_and_totalUseLimit cannot be reverted.\n";
        return false;
    }
}
