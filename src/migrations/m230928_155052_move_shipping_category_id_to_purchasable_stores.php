<?php

namespace craft\commerce\migrations;

use Craft;
use craft\commerce\db\Table;
use craft\db\Migration;
use craft\db\Query;
use yii\db\Expression;

/**
 * m230928_155052_move_shipping_category_id_to_purchasable_stores migration.
 */
class m230928_155052_move_shipping_category_id_to_purchasable_stores extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        // Move data across
        $purchasablesShipping = (new Query())->select(['id', 'shippingCategoryId'])->from(Table::PURCHASABLES)->all();

        $this->dropForeignKeyIfExists(Table::PURCHASABLES, ['shippingCategoryId']);
        $this->addColumn(Table::PURCHASABLES_STORES, 'shippingCategoryId', $this->integer()->null());

        $cases = [];
        foreach ($purchasablesShipping as $row) {
            if (!$row['shippingCategoryId']) {
                continue;
            }
            $cases[] = 'WHEN purchasableId = ' . $row['id'] . ' THEN ' . $row['shippingCategoryId'];
        }

        foreach ($purchasablesShipping as $item) {
            $this->update(Table::PURCHASABLES_STORES, ['shippingCategoryId' => $item['shippingCategoryId']], ['purchasableId' => $item['id']], [], false);
        }

        // if (!empty($cases)) {
        //     $batches = array_chunk($cases, 5);
        //     foreach ($batches as $batch) {
        //         $this->update(
        //             Table::PURCHASABLES_STORES,
        //             ['shippingCategoryId' => new Expression(sprintf('(CASE %s END)', implode(' ', $batch)))],
        //             [],
        //             [],
        //             false,
        //         );
        //     }
        // }

        $this->addForeignKey(null, Table::PURCHASABLES_STORES, ['shippingCategoryId'], Table::SHIPPINGCATEGORIES, ['id']);
        $this->dropColumn(Table::PURCHASABLES, 'shippingCategoryId');

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m230928_155052_move_shipping_category_id_to_purchasable_stores cannot be reverted.\n";
        return false;
    }
}
