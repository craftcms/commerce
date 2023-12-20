<?php

namespace craft\commerce\migrations;

use Craft;
use craft\commerce\Plugin;
use craft\db\Migration;

/**
 * m231220_103739_ensure_discount_sort_order migration.
 */
class m231220_103739_ensure_discount_sort_order extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        Plugin::getInstance()->getDiscounts()->ensureSortOrder();

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m231220_103739_ensure_discount_sort_order cannot be reverted.\n";
        return false;
    }
}
