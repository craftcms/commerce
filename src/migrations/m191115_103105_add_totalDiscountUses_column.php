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
 * m191115_103105_add_totalDiscountUses_column migration.
 */
class m191115_103105_add_totalDiscountUses_column extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->addColumn('{{%commerce_discounts}}', 'totalDiscountUses', $this->integer()->notNull()->defaultValue(0)->unsigned());
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m191115_103105_add_totalDiscountUses_column cannot be reverted.\n";
        return false;
    }
}
