<?php

namespace craft\commerce\migrations;

use craft\commerce\db\Table;
use craft\db\Migration;

/**
 * m250701_054128_add_defaultVariant_idex_to_products migration.
 */
class m250701_054128_add_defaultVariant_idex_to_products extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->addForeignKey(
            null,
            Table::PRODUCTS,
            'defaultVariantId',
            \craft\db\Table::ELEMENTS,
            'id',
            'SET NULL',
            null
        );

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m250701_054128_add_defaultVariant_idex_to_products.php cannot be reverted.\n";

        return true;
    }
}
