<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\migrations;

use craft\commerce\db\Table;
use craft\db\Migration;

class m261102_000000_add_save_version_to_orders extends Migration
{
    public function safeUp(): bool
    {
        if (!$this->db->columnExists(Table::ORDERS, 'saveVersion')) {
            $this->addColumn(Table::ORDERS, 'saveVersion', $this->integer()->unsigned()->notNull()->defaultValue(0));
        }

        return true;
    }

    public function safeDown(): bool
    {
        if ($this->db->columnExists(Table::ORDERS, 'saveVersion')) {
            $this->dropColumn(Table::ORDERS, 'saveVersion');
        }

        return true;
    }
}
