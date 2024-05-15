<?php

namespace craft\commerce\migrations;

use craft\commerce\db\Table;
use craft\db\Migration;
use craft\db\Query;

/**
 * m221206_083940_add_purchasables_stores_table migration.
 */
class m221025_083940_add_purchasables_stores_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $storeId = (new Query())
            ->select(['id'])
            ->from([Table::STORES])
            ->limit(1)
            ->orderBy(['id' => SORT_ASC])
            ->scalar();

        // Variants
        $variantsToPurchasables = (new Query())
            ->select([
                'v.id',
                'v.width',
                'v.height',
                'v.length',
                'v.weight',
                'p.taxCategoryId',
                'p.shippingCategoryId',
            ])
            ->from([Table::VARIANTS . ' v'])
            ->innerJoin([Table::PRODUCTS . ' p'], '[[p.id]] = [[v.productId]]')
            ->all();

        $variantsToPurchasablesStores = collect((new Query())
            ->select([
                'v.id as purchasableId',
                'pur.price as basePrice',
                'v.stock',
                'v.hasUnlimitedStock',
                'v.minQty',
                'v.maxQty',
                'p.promotable',
                'p.availableForPurchase',
                'p.freeShipping',
                'v.dateUpdated',
                'v.dateCreated',
            ])
            ->from(['v' => Table::VARIANTS])
            ->innerJoin(['p' => Table::PRODUCTS], '[[p.id]] = [[v.productId]]')
            ->innerJoin(['pur' => Table::PURCHASABLES], '[[pur.id]] = [[v.id]]')
            ->all());

        $customPurchasablesToPurchasablesStores = collect((new Query())
            ->select([
                'id as purchasableId',
                'price as basePrice',
            ])
            ->from(Table::PURCHASABLES)
            ->where(['not', ['id' => (new Query())
                ->select(['id'])
                ->from(Table::VARIANTS), ],
            ])
            ->all());

        if (!$this->db->columnExists(Table::PURCHASABLES, 'width')) {
            $this->addColumn(Table::PURCHASABLES, 'width', $this->decimal(14, 4));
        }
        if (!$this->db->columnExists(Table::PURCHASABLES, 'height')) {
            $this->addColumn(Table::PURCHASABLES, 'height', $this->decimal(14, 4));
        }
        if (!$this->db->columnExists(Table::PURCHASABLES, 'length')) {
            $this->addColumn(Table::PURCHASABLES, 'length', $this->decimal(14, 4));
        }
        if (!$this->db->columnExists(Table::PURCHASABLES, 'weight')) {
            $this->addColumn(Table::PURCHASABLES, 'weight', $this->decimal(14, 4));
        }
        if (!$this->db->columnExists(Table::PURCHASABLES, 'taxCategoryId')) {
            $this->addColumn(Table::PURCHASABLES, 'taxCategoryId', $this->integer());
        }
        if (!$this->db->columnExists(Table::PURCHASABLES, 'shippingCategoryId')) {
            $this->addColumn(Table::PURCHASABLES, 'shippingCategoryId', $this->integer());
        }

        $this->addForeignKey(null, Table::PURCHASABLES, ['taxCategoryId'], Table::TAXCATEGORIES, ['id']);
        $this->addForeignKey(null, Table::PURCHASABLES, ['shippingCategoryId'], Table::SHIPPINGCATEGORIES, ['id']);

        $this->createTable(Table::PURCHASABLES_STORES, [
            'id' => $this->primaryKey(),
            'purchasableId' => $this->integer()->notNull(),
            'storeId' => $this->integer()->notNull(),
            'basePrice' => $this->decimal(14, 4), // @TODO - should this be a string?
            'basePromotionalPrice' => $this->decimal(14, 4), // @TODO - should this be a string?
            'promotable' => $this->boolean()->notNull()->defaultValue(false),
            'availableForPurchase' => $this->boolean()->notNull()->defaultValue(true),
            'freeShipping' => $this->boolean()->notNull()->defaultValue(true),
            'stock' => $this->integer(),
            'hasUnlimitedStock' => $this->boolean()->notNull()->defaultValue(false),
            'minQty' => $this->integer(),
            'maxQty' => $this->integer(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->addForeignKey(null, Table::PURCHASABLES_STORES, ['purchasableId'], Table::PURCHASABLES, ['id'], 'CASCADE');
        $this->addForeignKey(null, Table::PURCHASABLES_STORES, ['storeId'], Table::STORES, ['id'], 'CASCADE');

        if (!empty($variantsToPurchasables)) {
            foreach ($variantsToPurchasables as $variantsToPurchasable) {
                $this->update(Table::PURCHASABLES, $variantsToPurchasable, ['id' => $variantsToPurchasable['id']]);
            }
        }

        if ($variantsToPurchasablesStores->isNotEmpty()) {
            $variantsToPurchasablesStores->each(function($variantToPurchasableStore) use ($storeId) {
                $variantToPurchasableStore['storeId'] = $storeId;
                $this->insert(Table::PURCHASABLES_STORES, $variantToPurchasableStore);
            });
        }

        if ($customPurchasablesToPurchasablesStores->isNotEmpty()) {
            $customPurchasablesToPurchasablesStores->each(function($customPurchasableToPurchasableStore) use ($storeId) {
                $customPurchasableToPurchasableStore['storeId'] = $storeId;
                $this->insert(Table::PURCHASABLES_STORES, $customPurchasableToPurchasableStore);
            });
        }

        $this->dropIndexIfExists(Table::VARIANTS, 'sku', false);

        $this->dropColumn(Table::VARIANTS, 'price');
        $this->dropColumn(Table::VARIANTS, 'width');
        $this->dropColumn(Table::VARIANTS, 'height');
        $this->dropColumn(Table::VARIANTS, 'length');
        $this->dropColumn(Table::VARIANTS, 'weight');
        $this->dropColumn(Table::VARIANTS, 'stock');
        $this->dropColumn(Table::VARIANTS, 'hasUnlimitedStock');
        $this->dropColumn(Table::VARIANTS, 'minQty');
        $this->dropColumn(Table::VARIANTS, 'maxQty');
        $this->dropColumn(Table::VARIANTS, 'sku');

        $this->dropForeignKeyIfExists(Table::PRODUCTS, 'taxCategoryId');
        $this->dropForeignKeyIfExists(Table::PRODUCTS, 'shippingCategoryId');
        $this->dropIndexIfExists(Table::PRODUCTS, 'taxCategoryId', false);
        $this->dropIndexIfExists(Table::PRODUCTS, 'shippingCategoryId', false);

        $this->dropColumn(Table::PRODUCTS, 'promotable');
        $this->dropColumn(Table::PRODUCTS, 'taxCategoryId');
        $this->dropColumn(Table::PRODUCTS, 'shippingCategoryId');
        $this->dropColumn(Table::PRODUCTS, 'availableForPurchase');
        $this->dropColumn(Table::PRODUCTS, 'freeShipping');

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m221206_083940_add_purchasables_stores_table cannot be reverted.\n";
        return false;
    }
}
