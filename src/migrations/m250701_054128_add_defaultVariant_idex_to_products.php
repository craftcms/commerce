<?php

namespace craft\commerce\migrations;

use Craft;
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
        // Add foreign key for defaultVariantId if it doesn't exist
        $this->addForeignKey(
            null,
            '{{%commerce_products}}',
            'defaultVariantId',
            '{{%elements}}',
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
