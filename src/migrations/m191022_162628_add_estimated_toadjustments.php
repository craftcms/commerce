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
 * m191022_162628_add_estimated_toadjustments migration.
 */
class m191022_162628_add_estimated_toadjustments extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->addColumn('{{%commerce_orderadjustments}}', 'isEstimated', $this->boolean()->notNull()->defaultValue(false));
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m191022_162628_add_estimated_toadjustments cannot be reverted.\n";
        return false;
    }
}
