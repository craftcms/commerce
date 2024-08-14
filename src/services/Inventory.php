<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\services;

use Craft;
use craft\commerce\base\InventoryMovementInterface;
use craft\commerce\base\Purchasable;
use craft\commerce\collections\InventoryMovementCollection;
use craft\commerce\collections\UpdateInventoryLevelCollection;
use craft\commerce\db\Table;
use craft\commerce\elements\Order;
use craft\commerce\enums\InventoryTransactionType;
use craft\commerce\enums\InventoryUpdateQuantityType;
use craft\commerce\models\inventory\InventoryCommittedMovement;
use craft\commerce\models\inventory\InventoryManualMovement;
use craft\commerce\models\inventory\UpdateInventoryLevel;
use craft\commerce\models\inventory\UpdateInventoryLevelInTransfer;
use craft\commerce\models\InventoryFulfillmentLevel;
use craft\commerce\models\InventoryItem;
use craft\commerce\models\InventoryLevel;
use craft\commerce\models\InventoryLocation;
use craft\commerce\models\InventoryTransaction;
use craft\commerce\Plugin;
use craft\commerce\records\InventoryItem as InventoryItemRecord;
use craft\db\Query;
use craft\db\Table as CraftTable;
use craft\helpers\Db;
use Illuminate\Support\Collection;
use yii\base\Component;
use yii\base\InvalidConfigException;
use yii\db\Exception;
use yii\db\Expression;

/**
 * Inventory service.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.0.0
 */
class Inventory extends Component
{
    /**
     * @param Purchasable $purchasable
     * @return Collection<InventoryLevel>
     */
    public function getInventoryLevelsForPurchasable(Purchasable $purchasable): Collection
    {
        $inventoryLevels = collect();

        if (!$purchasable->id || !$purchasable->inventoryItemId) {
            return $inventoryLevels; // empty collection
        }

        $storeId = $purchasable->getStore()->id;
        $storeInventoryLocations = Plugin::getInstance()->getInventoryLocations()->getInventoryLocations($storeId);

        foreach ($storeInventoryLocations as $inventoryLocation) {
            $inventoryLevel = $this->getInventoryLevel($purchasable->getInventoryItem(), $inventoryLocation);

            if (!$inventoryLevel) {
                continue;
            }
            $inventoryLevels->push($inventoryLevel);
        }

        return $inventoryLevels;
    }

    /**
     * @param Purchasable $purchasable
     * @return InventoryItem
     */
    public function getInventoryItemByPurchasable(Purchasable $purchasable): InventoryItem
    {
        return $this->getInventoryItemById($purchasable->inventoryItemId);
    }

    /**
     * @param int $id
     * @return InventoryItem
     */
    public function getInventoryItemById(int $id): InventoryItem
    {
        $inventoryItem = $this->getInventoryItemQuery()
            ->where(['id' => $id])
            ->one();

        return $this->_populateInventoryItem($inventoryItem);
    }

    /**
     * @param array<int> $ids
     * @return Collection<InventoryItem>
     */
    public function getInventoryItemsByIds(array $ids): Collection
    {
        $inventoryItemsResults = $this->getInventoryItemQuery()
            ->where(['id' => $ids])
            ->all();

        $inventoryItems = collect();
        foreach ($inventoryItemsResults as $inventoryItem) {
            $inventoryItems->push($this->_populateInventoryItem($inventoryItem));
        }

        return $inventoryItems;
    }

    /**
     * Returns an inventory level model which is the sum of all inventory movements types for an item in a location.
     *
     * @param InventoryItem $inventoryItem
     * @param InventoryLocation $inventoryLocation
     * @param bool $withTrashed
     * @return ?InventoryLevel
     */
    public function getInventoryLevel(InventoryItem $inventoryItem, InventoryLocation $inventoryLocation, bool $withTrashed = false): ?InventoryLevel
    {
        $result = $this->getInventoryLevelQuery(withTrashed: $withTrashed)
            ->andWhere([
                'inventoryLocationId' => $inventoryLocation->id,
                'inventoryItemId' => $inventoryItem->id,
            ])->one();

        if (!$result) {
            return null;
        }

        return $this->_populateInventoryLevel($result);
    }

    /**
     * @param InventoryItem $inventoryItem
     * @param bool $validate
     * @return bool
     * @throws InvalidConfigException
     */
    public function saveInventoryItem(InventoryItem $inventoryItem, bool $validate = true): bool
    {
        /** @var ?InventoryItemRecord $inventoryItemRecord */
        $inventoryItemRecord = InventoryItemRecord::find()
            ->where(['id' => $inventoryItem->id])
            ->one();

        if ($inventoryItemRecord === null) {
            throw new InvalidConfigException('No inventory item exists with the ID “' . $inventoryItem->id . '”');
        }

        $inventoryItemRecord->purchasableId = $inventoryItem->purchasableId;
        $inventoryItemRecord->countryCodeOfOrigin = $inventoryItem->countryCodeOfOrigin;
        $inventoryItemRecord->administrativeAreaCodeOfOrigin = $inventoryItem->administrativeAreaCodeOfOrigin;
        $inventoryItemRecord->harmonizedSystemCode = $inventoryItem->harmonizedSystemCode;

        return $inventoryItemRecord->save();
    }

    /**
     * @param array $data
     * @return InventoryItem
     */
    private function _populateInventoryItem(array $data): InventoryItem
    {
        return new InventoryItem($data);
    }

    /**
     * @param array $data
     * @return InventoryTransaction
     */
    private function _populateInventoryTransaction(array $data): InventoryTransaction
    {
        return new InventoryTransaction($data);
    }

    /**
     * @param array $data
     * @return InventoryLevel
     */
    private function _populateInventoryLevel(array $data): InventoryLevel
    {
        unset($data['purchasableId']);
        return new InventoryLevel($data);
    }

    /**
     * @param array $data
     * @return InventoryFulfillmentLevel
     */
    private function _populateInventoryFulfillmentLevel(array $data): InventoryFulfillmentLevel
    {
        return new InventoryFulfillmentLevel($data);
    }

    /**
     * @param InventoryLocation $inventoryLocation
     * @param bool $withTrashed
     * @return Collection
     * @throws InvalidConfigException
     */
    public function getInventoryLocationLevels(InventoryLocation $inventoryLocation, bool $withTrashed = false): Collection
    {
        $levels = $this->getInventoryLevelQuery(withTrashed: $withTrashed)
            ->andWhere(['inventoryLocationId' => $inventoryLocation->id])
            ->collect();

        $inventoryItems = Plugin::getInstance()->getInventory()->getInventoryItemsByIds($levels->pluck('inventoryItemId')->unique()->toArray());
        return $levels->map(function($level) use ($inventoryItems) {
            $inventoryLevel = $this->_populateInventoryLevel($level);
            if ($item = $inventoryItems->firstWhere('id', $level['inventoryItemId'])) {
                $inventoryLevel->setInventoryItem($item);
            }
            return $inventoryLevel;
        });
    }

    /**
     * Returns the totals for inventory items grouped by location and purchasable/inventoryItem.
     *
     * @param int|null $limit
     * @param int|null $offset
     * @param bool $withTrashed
     * @return Query
     */
    public function getInventoryLevelQuery(?int $limit = null, ?int $offset = null, bool $withTrashed = false): Query
    {
        $inventoryTotals = (new Query())
            ->select([
                'inventoryLocationId' => '[[il.id]]',
                'inventoryItemId' => '[[ii.id]]',
                'type' => '[[it.type]]',
                'quantity' => (new Expression('COALESCE(SUM([[it.quantity]]), 0)')),
            ])
            ->from(['il' => Table::INVENTORYLOCATIONS]) // we want a record for every location and...
            ->join('CROSS JOIN', ['ii' => Table::INVENTORYITEMS]) // ...every inventory item
            ->leftJoin(['it' => Table::INVENTORYTRANSACTIONS], "[[il.id]] = [[it.inventoryLocationId]] AND [[ii.id]] = [[it.inventoryItemId]]")
            ->groupBy(['[[il.id]]', '[[ii.id]]', '[[it.type]]']);

        $query = (new Query())
            ->select([
                '[[ii.id]] as inventoryItemId',
                '[[ii.purchasableId]] as purchasableId',
                '[[it.inventoryLocationId]] as inventoryLocationId',
                'SUM(CASE WHEN [[it.type]] = \'available\' THEN [[it.quantity]] ELSE 0 END) as availableTotal',
                'SUM(CASE WHEN [[it.type]] = \'committed\' THEN [[it.quantity]] ELSE 0 END) as committedTotal',
                'SUM(CASE WHEN [[it.type]] = \'reserved\' THEN [[it.quantity]] ELSE 0 END) as reservedTotal',
                'SUM(CASE WHEN [[it.type]] = \'damaged\' THEN [[it.quantity]] ELSE 0 END) as damagedTotal',
                'SUM(CASE WHEN [[it.type]] = \'safety\' THEN [[it.quantity]] ELSE 0 END) as safetyTotal',
                'SUM(CASE WHEN [[it.type]] = \'qualityControl\' THEN [[it.quantity]] ELSE 0 END) as qualityControlTotal',
                'SUM(CASE WHEN [[it.type]] = \'incoming\' THEN [[it.quantity]] ELSE 0 END) as incomingTotal',
                'SUM(CASE WHEN [[it.type]] IN (\'qualityControl\',\'safety\',\'damaged\',\'reserved\') THEN [[it.quantity]] ELSE 0 END) as unavailableTotal',
                'SUM(CASE WHEN [[it.type]] IN (\'qualityControl\',\'safety\',\'damaged\',\'reserved\', \'available\', \'committed\') THEN [[it.quantity]] ELSE 0 END) as onHandTotal',
            ])
            ->from(['ii' => Table::INVENTORYITEMS])
            ->leftJoin(['it' => $inventoryTotals], '[[it.inventoryItemId]] = [[ii.id]]')
            ->groupBy(["[[ii.id]]", "[[ii.purchasableId]]", "[[it.inventoryLocationId]]"])
            ->limit($limit)
            ->offset($offset);

        if (!$withTrashed) {
            $query->leftJoin(['elements' => CraftTable::ELEMENTS], '[[ii.purchasableId]] = [[elements.id]]');
            $query->andWhere(['elements.dateDeleted' => null]);
        }

        return $query;
    }

    /**
     * @return Query
     */
    public function getInventoryItemQuery(): Query
    {
        return (new Query())
            ->select([
                'id',
                'purchasableId',
                'countryCodeOfOrigin',
                'administrativeAreaCodeOfOrigin',
                'harmonizedSystemCode',
            ])
            ->from(Table::INVENTORYITEMS);
    }

    /**
     * @param UpdateInventoryLevelCollection $updateInventoryLevels
     * @return bool
     * @throws Exception
     */
    public function executeUpdateInventoryLevels(UpdateInventoryLevelCollection $updateInventoryLevels): bool
    {
        $transaction = Craft::$app->getDb()->beginTransaction();

        try {
            foreach ($updateInventoryLevels as $updateInventoryLevel) {
                if ($updateInventoryLevel->updateAction === InventoryUpdateQuantityType::SET) {
                    $this->_setInventoryLevel($updateInventoryLevel);
                } else {
                    $this->_adjustInventoryLevel($updateInventoryLevel);
                }
            }

            $transaction->commit();

            // TODO: Update stock value on purchasable stores
            // Craft::$app->getElements()->invalidateCachesForElement($this);

            return true;
        } catch (\Exception $e) {
            $transaction->rollBack();
            throw $e;
        }
    }

    /**
     * @param UpdateInventoryLevel|UpdateInventoryLevelInTransfer $updateInventoryLevel
     * @return bool
     */
    private function _setInventoryLevel(UpdateInventoryLevel|UpdateInventoryLevelInTransfer $updateInventoryLevel): bool
    {
        $tableName = Table::INVENTORYTRANSACTIONS;

        if ($updateInventoryLevel->type === 'onHand') {
            $types = collect(InventoryTransactionType::onHand())->pluck('value');
        } else {
            $types = [$updateInventoryLevel->type];
        }
        $quantityQuery = (new Query())
            ->select([':quantity - COALESCE(SUM(quantity), 0)'])
            ->from($tableName)
            ->where([
                'type' => $types,
                'inventoryItemId' => $updateInventoryLevel->inventoryItem->id,
                'inventoryLocationId' => $updateInventoryLevel->inventoryLocation->id,
            ])
            ->params([':quantity' => $updateInventoryLevel->quantity])
            ->scalar();

        $type = $updateInventoryLevel->type;
        if ($updateInventoryLevel->type === 'onHand') {
            $type = InventoryTransactionType::AVAILABLE->value;
        }

        Craft::$app->db->createCommand()
            ->insert($tableName, [
                'quantity' => $quantityQuery,
                'type' => $type,
                'inventoryItemId' => $updateInventoryLevel->inventoryItem->id,
                'inventoryLocationId' => $updateInventoryLevel->inventoryLocation->id,
                'note' => $updateInventoryLevel->note,
                'transfer' => $updateInventoryLevel->transferId,
                'movementHash' => $this->getMovementHash(),
                'dateCreated' => Db::prepareDateForDb(new \DateTime()),
                'userId' => Craft::$app->getUser()->getIdentity()?->id,
            ])->execute();

        return true;
    }

    /**
     * @param UpdateInventoryLevel|UpdateInventoryLevelInTransfer $updateInventoryLevel
     * @return bool
     */
    private function _adjustInventoryLevel(UpdateInventoryLevel|UpdateInventoryLevelInTransfer $updateInventoryLevel): bool
    {
        $tableName = Table::INVENTORYTRANSACTIONS;

        $type = $updateInventoryLevel->type;
        if ($updateInventoryLevel->type === 'onHand') {
            $type = 'available';
        }

        Craft::$app->db->createCommand()
            ->insert($tableName, [
                'quantity' => $updateInventoryLevel->quantity,
                'type' => $type,
                'inventoryItemId' => $updateInventoryLevel->inventoryItem->id,
                'inventoryLocationId' => $updateInventoryLevel->inventoryLocation->id,
                'movementHash' => $this->getMovementHash(),
                'dateCreated' => Db::prepareDateForDb(new \DateTime()),
                'note' => $updateInventoryLevel->note,
            ])
            ->execute();

        return true;
    }

    /**
     *
     * @param InventoryMovementCollection $inventoryMovements
     * @return bool
     * @throws \yii\db\Exception
     */
    public function executeInventoryMovements(InventoryMovementCollection $inventoryMovements): bool
    {
        $tableName = Table::INVENTORYTRANSACTIONS;

        $db = Craft::$app->getDb();
        $transaction = $db->beginTransaction();

        try {
            /** @var InventoryMovementInterface $inventoryMovement */
            foreach ($inventoryMovements as $inventoryMovement) {
                if (!$inventoryMovement->isValid()) {
                    $transaction->rollBack();
                    return false;
                }

                $movementDate = Db::prepareDateForDb(new \DateTime());

                // First insert operation
                $fromInsertResult = $db->createCommand()
                    ->insert($tableName, [
                        'quantity' => -$inventoryMovement->getQuantity(),
                        'type' => $inventoryMovement->getFromInventoryTransactionType()->value,
                        'inventoryItemId' => $inventoryMovement->getInventoryItem()->id,
                        'inventoryLocationId' => $inventoryMovement->getFromInventoryLocation()->id,
                        'movementHash' => $inventoryMovement->getInventoryMovementHash(),
                        'dateCreated' => $movementDate,
                        'transferId' => $inventoryMovement->getTransferId(),
                        'lineItemId' => $inventoryMovement->getLineItemId(),
                        'userId' => $inventoryMovement->getUserId(),
                        'note' => $inventoryMovement->getNote(),
                    ])
                    ->execute();

                if (!$fromInsertResult) {
                    $transaction->rollBack();
                    return false;
                }

                // Second insert operation
                $toInsertResult = $db->createCommand()
                    ->insert($tableName, [
                        'quantity' => $inventoryMovement->getQuantity(),
                        'type' => $inventoryMovement->getToInventoryTransactionType()->value,
                        'inventoryItemId' => $inventoryMovement->getInventoryItem()->id,
                        'inventoryLocationId' => $inventoryMovement->getToInventoryLocation()->id,
                        'movementHash' => $inventoryMovement->getInventoryMovementHash(),
                        'dateCreated' => $movementDate,
                        'transferId' => $inventoryMovement->getTransferId(),
                        'lineItemId' => $inventoryMovement->getLineItemId(),
                        'userId' => $inventoryMovement->getUserId(),
                        'note' => $inventoryMovement->getNote(),
                    ])
                    ->execute();

                if (!$toInsertResult) {
                    $transaction->rollBack();
                    return false;
                }
            }

            $transaction->commit();

            // TODO: Update stock value on purchasable stores
            //  Craft::$app->getElements()->invalidateCachesForElement($this);

            return true;
        } catch (\Exception $e) {
            $transaction->rollBack();
            throw $e;
        }
    }


    /**
     * @return string
     */
    public function getMovementHash(): string
    {
        return md5(uniqid((string)mt_rand(), true));
    }

    /**
     * @param InventoryItem $inventoryItem
     * @param InventoryLocation $inventoryLocation
     * @return array
     */
    public function getUnfulfilledOrders(InventoryItem $inventoryItem, InventoryLocation $inventoryLocation): array
    {
        $inventoryLevel = $this->getInventoryLevel($inventoryItem, $inventoryLocation);

        if ($inventoryLevel->committedTotal <= 0) {
            return [];
        }

        // Get orders that have line items for this inventory level item
        $orderIds = (new Query())
            ->select(['orders.id'])
            ->from(['lineItems' => Table::LINEITEMS])
            ->leftJoin(['orders' => Table::ORDERS], '[[lineItems.orderId]] = [[orders.id]]')
            ->leftJoin(['it' => Table::INVENTORYTRANSACTIONS], '[[it.lineItemId]] = [[lineItems.id]]')
            ->where(['orders.isCompleted' => true])
            ->andWhere(['it.inventoryItemId' => $inventoryItem->id])
            ->andWhere(['it.inventoryLocationId' => $inventoryLocation->id])
            ->andWhere(['it.type' => InventoryTransactionType::COMMITTED->value])
            ->groupBy(['lineItems.orderId', 'lineItems.id'])
            ->having(['>=', 'SUM(it.quantity)', 'lineItems.qty'])
            ->column();

        return Order::find()
            ->id($orderIds)
            ->all();
    }

    /**
     * @return Query
     */
    public function getTransactionQuery(): Query
    {
        return (new Query())
            ->select([
                'inventoryLocationId',
                'inventoryItemId',
                'movementHash',
                'quantity',
                'type',
                'note',
                'transferId',
                'lineItemId',
                'userId',
                'dateCreated',
            ])
            ->orderBy(['dateCreated' => SORT_DESC])
            ->from(Table::INVENTORYTRANSACTIONS);
    }

    /**
     * @param InventoryItem $inventoryItem
     * @param InventoryLocation $inventoryLocation
     * @return Collection
     */
    public function getInventoryTransactions(InventoryItem $inventoryItem, InventoryLocation $inventoryLocation): Collection
    {
        $transactions = $this->getTransactionQuery()
            ->where(['inventoryItemId' => $inventoryItem->id, 'inventoryLocationId' => $inventoryLocation->id])
            ->all();

        foreach ($transactions as $key => $transaction) {
            $transactions[$key] = $this->_populateInventoryTransaction($transaction);
        }

        return collect($transactions);
    }

    /**
     * @param Order $order
     * @return Collection<InventoryFulfillmentLevel>
     * @throws InvalidConfigException
     * @throws \craft\errors\DeprecationException
     */
    public function getInventoryFulfillmentLevels(Order $order): Collection
    {
        // We don’t limit this to the orders store locations since we want to show all locations that have historical inventory for the order.
        $locations = Plugin::getInstance()->getInventoryLocations()->getAllInventoryLocations();

        $inventoryFulfillmentLevels = [];
        foreach ($locations as $location) {
            $data = (new Query())
                ->select([
                    '[[it.lineItemId]]',
                    '[[it.inventoryItemId]]',
                    '[[it.inventoryLocationId]]',

                    'SUM(CASE WHEN (([[it.type]] = :committedType AND quantity > 0) OR ([[it.type]] = :fulfilledType AND quantity < 0)) THEN [[quantity]] ELSE 0 END) AS committedQuantity',

                    'SUM(CASE WHEN [[it.type]] = :committedType THEN [[quantity]] ELSE 0 END) AS outstandingCommittedQuantity',
                    'SUM(CASE WHEN [[it.type]] = :fulfilledType THEN [[quantity]] ELSE 0 END) AS fulfilledQuantity',
                ])
                ->from(['it' => Table::INVENTORYTRANSACTIONS])
                ->andWhere([
                    '[[li.orderId]]' => $order->id,
                    '[[it.inventoryLocationId]]' => $location->id,
                ])
                ->andWhere(['or',
                    ['it.type' => InventoryTransactionType::COMMITTED->value],
                    ['it.type' => InventoryTransactionType::FULFILLED->value],
                ])
                ->groupBy([
                    '[[it.lineItemId]]',
                    '[[it.inventoryItemId]]',
                    '[[it.inventoryLocationId]]',
                ])
                ->params([
                    ':committedType' => InventoryTransactionType::COMMITTED->value,
                    ':fulfilledType' => InventoryTransactionType::FULFILLED->value,
                ])
                ->innerJoin(['li' => Table::LINEITEMS], '[[li.id]] = [[it.lineItemId]]')
                ->all();

            foreach ($data as $row) {
                $inventoryFulfillmentLevels[] = $this->_populateInventoryFulfillmentLevel($row);
            }
        }

        return collect($inventoryFulfillmentLevels);
    }

    /**
     * @param Order $order
     * @return void
     * @throws InvalidConfigException
     * @throws \yii\db\Exception
     */
    public function orderCompleteHandler(Order $order)
    {
        $allInventoryLevels = [];
        $qtyLineItem = [];
        foreach ($order->getLineItems() as $lineItem) {
            $purchasable = $lineItem->getPurchasable();
            // Don't reduce stock of unlimited items.
            if ($purchasable->inventoryTracked) {
                if (!isset($qtyLineItem[$purchasable->id])) {
                    $qtyLineItem[$purchasable->id] = 0;
                }
                $qtyLineItem[$purchasable->id] += $lineItem->qty;
                $allInventoryLevels[$purchasable->id] = $purchasable->getInventoryLevels();
            }
        }

        $selectedInventoryLevelForItem = [];
        /**
         * @var  int $purchasableId
         * @var  InventoryLevel $inventoryLevels
         */
        foreach ($allInventoryLevels as $purchasableId => $inventoryLevels) {
            foreach ($inventoryLevels as $level) {
                if (!isset($selectedInventoryLevelForItem[$purchasableId])) {
                    $selectedInventoryLevelForItem[$purchasableId] = $level;

                    if ($level->availableTotal >= $qtyLineItem[$purchasableId]) {
                        break;
                    }
                    continue;
                }

                if ($level->availableTotal >= $qtyLineItem[$purchasableId]) {
                    $selectedInventoryLevelForItem[$purchasableId] = $level;
                    break;
                }
            }
        }

        $movements = InventoryMovementCollection::make();

        $reserveAmountByPurchasableId = [];
        $availableTotalByPurchasableIdAndLocationId = [];

        // Loop through line items and create committed movements for the selected inventory location
        foreach ($order->getLineItems() as $lineItem) {
            if (isset($selectedInventoryLevelForItem[$lineItem->purchasableId])) {
                $level = $selectedInventoryLevelForItem[$lineItem->purchasableId];

                if (!isset($reserveAmountByPurchasableId[$lineItem->purchasableId])) {
                    $availableTotalByPurchasableIdAndLocationId[$lineItem->purchasableId . '-' . $level->inventoryLocationId] = $level->availableTotal;
                    $reserveAmountByPurchasableId[$lineItem->purchasableId] = [];
                }

                if ($lineItem->qty > $availableTotalByPurchasableIdAndLocationId[$lineItem->purchasableId . '-' . $level->inventoryLocationId]) {
                    $totalToReserveForLineItem = $lineItem->qty - $availableTotalByPurchasableIdAndLocationId[$lineItem->purchasableId . '-' . $level->inventoryLocationId];
                    $reserveAmountByPurchasableId[$lineItem->purchasableId][$lineItem->id] = $totalToReserveForLineItem;
                    $availableTotalByPurchasableIdAndLocationId[$lineItem->purchasableId . '-' . $level->inventoryLocationId] = 0;
                } else {
                    $availableTotalByPurchasableIdAndLocationId[$lineItem->purchasableId . '-' . $level->inventoryLocationId] -= $lineItem->qty;
                }

                $movements->push(new InventoryCommittedMovement([
                    'inventoryItem' => $level->getInventoryItem(),
                    'fromInventoryLocation' => $level->getInventoryLocation(),
                    'toInventoryLocation' => $level->getInventoryLocation(),
                    'fromInventoryTransactionType' => InventoryTransactionType::AVAILABLE,
                    'toInventoryTransactionType' => InventoryTransactionType::COMMITTED,
                    'quantity' => $lineItem->qty,
                    'lineItemId' => $lineItem->id,
                ]));
            }
        }

        // Loop through reserve amounts to reserve the remaining stock in the other inventory locations
        foreach ($reserveAmountByPurchasableId as $purchasableId => $r) {
            foreach ($r as $lineItemId => $qty) {
                foreach ($allInventoryLevels[$purchasableId] as $level) {
                    if ($level === $selectedInventoryLevelForItem[$purchasableId]) {
                        continue;
                    }

                    if (!isset($availableTotalByPurchasableIdAndLocationId[$purchasableId . '-' . $level->inventoryLocationId])) {
                        $availableTotalByPurchasableIdAndLocationId[$purchasableId . '-' . $level->inventoryLocationId] = $level->availableTotal;
                    }

                    $canReserveFullQty = $qty <= $availableTotalByPurchasableIdAndLocationId[$purchasableId . '-' . $level->inventoryLocationId];
                    $qtyToReserve = $canReserveFullQty ? $qty : $availableTotalByPurchasableIdAndLocationId[$purchasableId . '-' . $level->inventoryLocationId];

                    if ($qtyToReserve < 1) {
                        break;
                    }

                    $availableTotalByPurchasableIdAndLocationId[$purchasableId . '-' . $level->inventoryLocationId] -= $qtyToReserve;

                    $movements->push(new InventoryManualMovement([
                        'inventoryItem' => $level->getInventoryItem(),
                        'fromInventoryLocation' => $level->getInventoryLocation(),
                        'toInventoryLocation' => $level->getInventoryLocation(),
                        'fromInventoryTransactionType' => InventoryTransactionType::AVAILABLE,
                        'toInventoryTransactionType' => InventoryTransactionType::RESERVED,
                        'quantity' => $qtyToReserve,
                        'lineItemId' => $lineItemId,
                    ]));

                    $qty -= $qtyToReserve;
                    if ($qty <= 0) {
                        break;
                    }
                }
            }
        }

        $this->executeInventoryMovements($movements);

        foreach ($selectedInventoryLevelForItem as $inventoryLevel) {
            $purchasable = $inventoryLevel->getPurchasable();
            Plugin::getInstance()->getPurchasables()->updateStoreStockCache($purchasable);
        }
    }
}
