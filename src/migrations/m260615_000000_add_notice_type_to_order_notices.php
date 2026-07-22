<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\migrations;

use craft\commerce\db\Table;
use craft\db\Migration;

class m260615_000000_add_notice_type_to_order_notices extends Migration
{
    public function safeUp(): bool
    {
        if (!$this->db->columnExists(Table::ORDERNOTICES, 'noticeType')) {
            $this->addColumn(Table::ORDERNOTICES, 'noticeType', $this->string()->notNull()->defaultValue('customer'));
        }

        return true;
    }

    public function safeDown(): bool
    {
        if ($this->db->columnExists(Table::ORDERNOTICES, 'noticeType')) {
            $this->dropColumn(Table::ORDERNOTICES, 'noticeType');
        }

        return true;
    }
}
