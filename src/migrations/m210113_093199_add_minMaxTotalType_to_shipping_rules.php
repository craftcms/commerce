<?php

namespace craft\commerce\migrations;

use craft\db\Migration;

/**
 * m210113_093199_add_minMaxTotalType_to_shipping_rules migration.
 */
class m210113_093199_add_minMaxTotalType_to_shipping_rules extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        if (!$this->db->columnExists('{{%commerce_shippingrules}}', 'minMaxTotalType')) {
            $this->addColumn(
                '{{%commerce_shippingrules}}',
                'minMaxTotalType',
                $this->enum('minMaxTotalType', ['salePrice', 'salePriceWithDiscounts'])->notNull()->defaultValue('salePrice')
            );
        }
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m210113_093199_add_minMaxTotalType_to_shipping_rules cannot be reverted.\n";
        return false;
    }
}
