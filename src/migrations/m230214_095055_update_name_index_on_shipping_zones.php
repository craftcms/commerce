<?php

namespace craft\commerce\migrations;

use Craft;
use craft\commerce\db\Table;
use craft\db\Migration;
use craft\helpers\Db;

/**
 * m230214_095055_update_name_index_on_shipping_zones migration.
 */
class m230214_095055_update_name_index_on_shipping_zones extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        Db::dropIndexIfExists(Table::SHIPPINGZONES, ['name'], true, $this->getDb());
        $this->createIndex(null, Table::SHIPPINGZONES, ['name'], false);

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m230214_095055_update_name_index_on_shipping_zones cannot be reverted.\n";
        return false;
    }
}
