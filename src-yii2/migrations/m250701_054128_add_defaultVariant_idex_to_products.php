<?php

namespace craft\commerce\migrations;

use craft\commerce\db\Table;
use craft\commerce\elements\Variant;
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
        $subQuery = (new \craft\db\Query())
            ->select(['id'])
            ->from(\craft\db\Table::ELEMENTS)
            ->where(['type' => Variant::class]);

        $this->update(
            Table::PRODUCTS,
            ['defaultVariantId' => null],
            ['not', ['defaultVariantId' => $subQuery]],
            [],
        );

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
