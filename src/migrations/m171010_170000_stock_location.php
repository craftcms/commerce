<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\migrations;

use craft\db\Migration;

/**
 * m171010_170000_stock_location
 */
class m171010_170000_stock_location extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->addColumn('{{%commerce_addresses}}', 'stockLocation', $this->boolean()->notNull()->defaultValue(false));

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m171010_170000_stock_location cannot be reverted.\n";

        return false;
    }
}
