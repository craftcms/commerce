<?php

namespace craft\commerce\migrations;

use craft\commerce\db\Table;
use craft\commerce\records\CatalogPricingRule;
use craft\db\Migration;
use craft\db\Query;
use craft\helpers\StringHelper;

/**
 * m221026_105212_add_catalog_pricing_table migration.
 */
class m221026_105212_add_catalog_pricing_table extends Migration
{
    private string $_tableName = '{{%commerce_catalogpricing}}';
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        if (!$this->db->tableExists('{{%commerce_catalogpricingrules}}')) {
            $this->createTable('{{%commerce_catalogpricingrules}}', [
                'id' => $this->primaryKey(),
                'name' => $this->string()->notNull(),
                'description' => $this->text(),
                'storeId' => $this->integer()->notNull(),
                'dateFrom' => $this->dateTime(),
                'dateTo' => $this->dateTime(),
                'apply' => $this->enum('apply', ['toPercent', 'toFlat', 'byPercent', 'byFlat'])->notNull(),
                'applyAmount' => $this->decimal(14, 4)->notNull(),
                'applyPriceType' => $this->enum('applyPriceType', [CatalogPricingRule::APPLY_PRICE_TYPE_PRICE, CatalogPricingRule::APPLY_PRICE_TYPE_PROMOTIONAL_PRICE])->notNull(),
                'purchasableCondition' => $this->text(),
                'customerCondition' => $this->text(),
                'enabled' => $this->boolean()->notNull()->defaultValue(true),
                'isPromotionalPrice' => $this->boolean()->notNull()->defaultValue(false),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);

            $this->createIndex(null, '{{%commerce_catalogpricingrules}}', 'storeId', false);
            $this->addForeignKey(null, '{{%commerce_catalogpricingrules}}', ['storeId'], Table::STORES, ['id'], 'CASCADE');
        }

        if (!$this->db->tableExists('{{%commerce_catalogpricingrules_users}}')) {
            $this->createTable('{{%commerce_catalogpricingrules_users}}', [
                'id' => $this->primaryKey(),
                'catalogPricingRuleId' => $this->integer()->notNull(),
                'userId' => $this->integer()->notNull(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);

            $this->createIndex(null, '{{%commerce_catalogpricingrules_users}}', 'catalogPricingRuleId', false);
            $this->createIndex(null, '{{%commerce_catalogpricingrules_users}}', 'userId', false);
            $this->addForeignKey(null, '{{%commerce_catalogpricingrules_users}}', ['userId'], \craft\db\Table::USERS, ['id'], 'CASCADE', 'CASCADE');
            $this->addForeignKey(null, '{{%commerce_catalogpricingrules_users}}', ['catalogPricingRuleId'], '{{%commerce_catalogpricingrules}}', ['id'], 'CASCADE', 'CASCADE');
        }

        if (!$this->db->tableExists($this->_tableName)) {
            $this->createTable($this->_tableName, [
                'id' => $this->primaryKey(),
                'price' => $this->decimal(14, 4), // TODO probably store as string?
                'purchasableId' => $this->integer()->notNull(),
                'storeId' => $this->integer(),
                'catalogPricingRuleId' => $this->integer(),
                'userId' => $this->integer(),
                'dateFrom' => $this->dateTime(),
                'dateTo' => $this->dateTime(),
                'isPromotionalPrice' => $this->boolean()->defaultValue(false),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);

            $this->createIndex(null, $this->_tableName, 'purchasableId', false);
            $this->createIndex(null, $this->_tableName, 'storeId', false);
            $this->createIndex(null, $this->_tableName, 'catalogPricingRuleId', false);
            $this->createIndex(null, $this->_tableName, 'userId', false);

            $this->addForeignKey(null, $this->_tableName, ['purchasableId'], Table::PURCHASABLES, ['id'], 'CASCADE', 'CASCADE');
            $this->addForeignKey(null, $this->_tableName, ['storeId'], Table::STORES, ['id'], 'CASCADE');
            $this->addForeignKey(null, $this->_tableName, ['catalogPricingRuleId'], Table::CATALOG_PRICING_RULES, ['id'], 'CASCADE');
            $this->addForeignKey(null, $this->_tableName, ['userId'], \craft\db\Table::USERS, ['id'], 'CASCADE');
        }

        if ($this->db->columnExists('{{%commerce_purchasables}}', 'price')) {
            $purchasablePrices = (new Query())
                ->select(['id as purchasableId', 'price', 'dateCreated', 'dateUpdated'])
                ->from('{{%commerce_purchasables}}')
                ->all();

            if (!empty($purchasablePrices)) {
                $storeId = (new Query())
                    ->select(['id'])
                    ->from('{{%commerce_stores}}')
                    ->orderBy(['id' => SORT_ASC])
                    ->scalar();

                array_walk($purchasablePrices, function(&$purchasablePrice) use ($storeId) {
                    $purchasablePrice['storeId'] = $storeId;
                    $purchasablePrice['uid'] = StringHelper::UUID();
                });

                $this->batchInsert($this->_tableName, ['purchasableId', 'price', 'dateCreated', 'dateUpdated', 'storeId', 'uid'], $purchasablePrices);
            }
            $this->dropColumn('{{%commerce_purchasables}}', 'price');
        }

        if ($this->db->columnExists('{{%commerce_variants}}', 'price')) {
            $this->dropColumn('{{%commerce_variants}}', 'price');
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m221026_105212_add_catalog_pricing_table cannot be reverted.\n";
        return false;
    }
}
