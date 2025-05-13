<?php

namespace craft\commerce\migrations;

use craft\commerce\db\Table;
use craft\db\Migration;

/**
 * m230217_095845_remove_shipping_rules_columns migration.
 */
class m230217_095845_remove_shipping_rules_columns extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->dropColumn(Table::SHIPPINGRULES, 'minQty');
        $this->dropColumn(Table::SHIPPINGRULES, 'maxQty');
        $this->dropColumn(Table::SHIPPINGRULES, 'minTotal');
        $this->dropColumn(Table::SHIPPINGRULES, 'maxTotal');
        $this->dropColumn(Table::SHIPPINGRULES, 'minMaxTotalType');
        $this->dropColumn(Table::SHIPPINGRULES, 'minWeight');
        $this->dropColumn(Table::SHIPPINGRULES, 'maxWeight');

        $this->dropForeignKeyIfExists(Table::SHIPPINGRULES, 'shippingZoneId');
        $this->dropIndexIfExists(Table::SHIPPINGRULES, 'shippingZoneId');

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m230217_095845_remove_shipping_rules_columns cannot be reverted.\n";
        return false;
    }
}
