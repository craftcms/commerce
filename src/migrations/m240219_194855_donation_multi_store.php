<?php

namespace craft\commerce\migrations;

use craft\commerce\db\Table;
use craft\db\Migration;
use craft\db\Query;

/**
 * m240219_194855_donation_multi_store migration.
 */
class m240219_194855_donation_multi_store extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $storeIds = (new Query())
            ->select('id')
            ->from(Table::STORES)
            ->column();

        // Get current donation data
        $donations = (new Query())
            ->select('*')
            ->from(Table::DONATIONS)
            ->all();

        foreach ($donations as $donation) {
            foreach ($storeIds as $storeId) {
                $this->upsert(Table::PURCHASABLES_STORES, [
                    'purchasableId' => $donation['id'],
                    'storeId' => $storeId,
                    'basePrice' => 0,
                    'basePromotionalPrice' => null,
                    'stock' => null,
                    'inventoryTracked' => false,
                    'minQty' => null,
                    'maxQty' => null,
                    'promotable' => false,
                    'availableForPurchase' => $donation['availableForPurchase'],
                    'freeShipping' => true,
                    'shippingCategoryId' => null,
                ], ['purchasableId', 'storeId']);
            }
        }

        // Remove `availableForPurchase` column from `commerce_donations` table
        $this->dropColumn(Table::DONATIONS, 'availableForPurchase');

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m240219_194855_donation_multi_store cannot be reverted.\n";
        return false;
    }
}
