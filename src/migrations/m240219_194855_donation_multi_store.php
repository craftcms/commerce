<?php

namespace craft\commerce\migrations;

use craft\commerce\db\Table;
use craft\commerce\records\PurchasableStore;
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
            $this->_ensureDonationPurchasable($donation);

            foreach ($storeIds as $storeId) {
                if (PurchasableStore::findOne(['purchasableId' => $donation['id'], 'storeId' => $storeId])) {
                    continue;
                }

                $this->insert(Table::PURCHASABLES_STORES, [
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
                ]);
            }
        }

        // Remove `availableForPurchase` column from `commerce_donations` table
        $this->dropColumn(Table::DONATIONS, 'availableForPurchase');

        return true;
    }

    private function _ensureDonationPurchasable(array $donation): void
    {
        $purchasableExists = (new Query())
            ->from(Table::PURCHASABLES)
            ->where(['id' => $donation['id']])
            ->exists();

        if ($purchasableExists) {
            return;
        }

        $purchasable = [
            'id' => $donation['id'],
            'sku' => $donation['sku'] ?: "DONATION-{$donation['id']}",
        ];

        if ($this->db->columnExists(Table::PURCHASABLES, 'description')) {
            $purchasable['description'] = null;
        }

        foreach (['width', 'height', 'length', 'weight'] as $column) {
            if ($this->db->columnExists(Table::PURCHASABLES, $column)) {
                $purchasable[$column] = 0;
            }
        }

        if ($this->db->columnExists(Table::PURCHASABLES, 'taxCategoryId')) {
            $purchasable['taxCategoryId'] = (new Query())
                ->select('id')
                ->from(Table::TAXCATEGORIES)
                ->orderBy(['id' => SORT_ASC])
                ->scalar();
        }

        $this->insert(Table::PURCHASABLES, $purchasable);
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
