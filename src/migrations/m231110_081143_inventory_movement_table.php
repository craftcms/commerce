<?php

namespace craft\commerce\migrations;

use craft\commerce\db\Table;
use craft\db\Migration;
use craft\db\Query;
use craft\helpers\Db;
use craft\helpers\StringHelper;
use yii\db\Expression;

/**
 * m231110_081143_inventory_movement_table migration.
 */
class m231110_081143_inventory_movement_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $primaryStoreId = (new Query())
            ->select(['id'])
            ->from(Table::STORES)
            ->where(['primary' => true])
            ->scalar();

        // Gather the current purchasable stock counts
        $stockCollection = collect((new Query())
            ->select([
                'p.id as purchasableId',
                new Expression('COALESCE([[ps.stock]], 0) as stock'),
                new Expression('COALESCE([[ps.hasUnlimitedStock]], false) as unlimitedStock'),
            ])
            ->from(['p' => Table::PURCHASABLES])
            ->leftJoin(['ps' => Table::PURCHASABLES_STORES], '[[p.id]] = [[ps.purchasableId]]')
            ->where(['ps.storeId' => $primaryStoreId])
            ->limit(null)
            ->all());

        // Create the inventory items table, indexes and FKs
        $this->createTable('{{%commerce_inventoryitems}}', [
            'id' => $this->primaryKey(),
            'purchasableId' => $this->integer()->notNull(),
            'countryCodeOfOrigin' => $this->string(),
            'administrativeAreaCodeOfOrigin' => $this->string(),
            'harmonizedSystemCode' => $this->string(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);
        $this->createIndex(null, '{{%commerce_inventoryitems}}', 'purchasableId', true);
        $this->addForeignKey(null, '{{%commerce_inventoryitems}}', 'purchasableId', '{{%commerce_purchasables}}', 'id', 'CASCADE', null);

        // Add the locations table
        $this->createTable('{{%commerce_inventorylocations}}', [
            'id' => $this->primaryKey(),
            'handle' => $this->string()->notNull(),
            'name' => $this->string()->notNull(),
            'addressId' => $this->integer(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'dateDeleted' => $this->dateTime(),
            'uid' => $this->uid(),
        ]);
        $this->addForeignKey(null, '{{%commerce_inventorylocations}}', 'addressId', '{{%addresses}}', 'id', 'CASCADE', null);

        // Create the transfers table
        $this->createTable('{{%commerce_transfers}}', [
            'id' => $this->primaryKey(),
            'transferStatus' => $this->enum('transferStatus', [
                'draft',
                'pending',
                'partial',
                'received',
            ])->notNull(),
            'originLocationId' => $this->integer(),
            'destinationLocationId' => $this->integer(),
            'uid' => $this->uid(),
        ]);

        $this->createIndex(null, '{{%commerce_transfers}}', 'originLocationId', false);
        $this->createIndex(null, '{{%commerce_transfers}}', 'destinationLocationId', false);

        // Create the commerce_inventory_movement table
        $this->createTable('{{%commerce_inventorymovements}}', [
            'id' => $this->primaryKey(),
            'inventoryLocationId' => $this->integer()->notNull(),
            'inventoryItemId' => $this->integer()->notNull(),
            'movementHash' => $this->string()->notNull(),
            'quantity' => $this->integer()->notNull(),
            'type' => $this->enum('type', [
                'incoming',
                'available',
                'committed',
                'reserved',
                'damaged',
                'safety',
                'qualityControl',
            ])->notNull(),
            'note' => $this->string(),
            'transferId' => $this->integer(), // Can be null
            'orderId' => $this->integer(), // Can be null
            'lineItemId' => $this->integer(), // Can be null
            'userId' => $this->integer(), // Can be null
            'dateCreated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);
        $this->createIndex(null, '{{%commerce_inventorymovements}}', 'inventoryItemId', false);
        $this->createIndex(null, '{{%commerce_inventorymovements}}', 'transferId', false);
        $this->createIndex(null, '{{%commerce_inventorymovements}}', 'orderId', false);
        $this->createIndex(null, '{{%commerce_inventorymovements}}', 'lineItemId', false);
        $this->createIndex(null, '{{%commerce_inventorymovements}}', 'userId', false);

        $this->addForeignKey(null, '{{%commerce_inventorymovements}}', 'inventoryItemId', '{{%commerce_inventoryitems}}', 'id', 'CASCADE', null);
        $this->addForeignKey(null, '{{%commerce_inventorymovements}}', 'inventoryLocationId', '{{%commerce_inventorylocations}}', 'id', 'CASCADE', null);
        $this->addForeignKey(null, '{{%commerce_inventorymovements}}', 'orderId', '{{%commerce_orders}}', 'id', 'SET NULL', null);
        $this->addForeignKey(null, '{{%commerce_inventorymovements}}', 'lineItemId', '{{%commerce_lineitems}}', 'id', 'SET NULL', null);
        $this->addForeignKey(null, '{{%commerce_inventorymovements}}', 'userId', '{{%users}}', 'id', 'SET NULL', null);
        $this->addForeignKey(null, '{{%commerce_inventorymovements}}', 'transferId', '{{%commerce_transfers}}', 'id', 'SET NULL', null);


        // get primary store
        $primaryStore = (new Query())
            ->select(['id'])
            ->from(Table::STORES)
            ->where(['primary' => true])
            ->one();

        // if no primaryStore found, use first one
        if (!$primaryStore) {
            $primaryStore = (new Query())
                ->select(['id'])
                ->from(Table::STORES)
                ->one();
        }

        // Get locationAddressId from store settings table
        $locationAddressId = (new Query())
            ->select(['locationAddressId'])
            ->from(Table::STORESETTINGS)
            ->where(['id' => $primaryStore['id']])
            ->scalar();

        // create default location
        $this->insert('{{%commerce_inventorylocations}}', [
            'name' => 'Default',
            'handle' => 'default',
            'addressId' => $locationAddressId ?: null,
            'dateCreated' => Db::prepareDateForDb(new \DateTime()),
            'dateUpdated' => Db::prepareDateForDb(new \DateTime()),
            'dateDeleted' => null,
            'uid' => StringHelper::UUID(),
        ]);
        $locationId = $this->db->getLastInsertID();

        // Create an inventory item for each SKU
        foreach ($stockCollection as $item) {
            $now = Db::prepareDateForDb(new \DateTime());
            $this->insert('{{%commerce_inventoryitems}}', [
                'purchasableId' => $item['purchasableId'],
                'dateCreated' => $now,
                'dateUpdated' => $now,
            ]);

            $this->insert('{{%commerce_inventorymovements}}', [
                'inventoryLocationId' => $locationId,
                'inventoryItemId' => $this->db->getLastInsertID(),
                'movementHash' => md5(uniqid((string)mt_rand(), true)),
                'quantity' => $item['stock'],
                'type' => 'available',
                'note' => 'count',
            ]);
        }

        // create inventory locations store relationship table
        $this->createTable('{{%commerce_inventorylocations_stores}}', [
            'id' => $this->primaryKey(),
            'inventoryLocationId' => $this->integer()->notNull(),
            'storeId' => $this->integer()->notNull(),
            'sortOrder' => $this->integer(), // per store
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->addForeignKey(null, '{{%commerce_inventorylocations_stores}}', 'inventoryLocationId', '{{%commerce_inventorylocations}}', 'id', 'CASCADE', null);
        $this->addForeignKey(null, '{{%commerce_inventorylocations_stores}}', 'storeId', '{{%commerce_stores}}', 'id', 'CASCADE', null);

        // insert default location into store relationship table
        $this->insert(Table::INVENTORYLOCATIONS_STORES, [
            'inventoryLocationId' => $locationId,
            'storeId' => $primaryStore['id'],
            'sortOrder' => 1,
            'dateCreated' => Db::prepareDateForDb(new \DateTime()),
            'dateUpdated' => Db::prepareDateForDb(new \DateTime()),
        ]);

        if ($this->db->columnExists('{{%commerce_purchasables_stores}}', 'hasUnlimitedStock')) {
            $this->renameColumn(Table::PURCHASABLES_STORES, 'hasUnlimitedStock', 'inventoryTracked');
        }

        // Flip `inventoryTracked` column in purchasables stores table
        $this->update('{{%commerce_purchasables_stores}}', ['inventoryTracked' => new Expression('NOT [[inventoryTracked]]')]);

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m231110_081143_inventory_movement_table cannot be reverted.\n";
        return false;
    }
}
