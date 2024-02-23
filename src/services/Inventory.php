<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\services;

use Craft;
use craft\commerce\base\Purchasable;
use craft\commerce\collections\InventoryMovementCollection;
use craft\commerce\collections\UpdateInventoryLevelCollection;
use craft\commerce\db\Table;
use craft\commerce\elements\Order;
use craft\commerce\enums\InventoryMovementType;
use craft\commerce\enums\InventoryUpdateQuantityType;
use craft\commerce\models\inventory\InventoryMovement;
use craft\commerce\models\inventory\UpdateInventoryLevel;
use craft\commerce\models\InventoryItem;
use craft\commerce\models\InventoryLevel;
use craft\commerce\models\InventoryLocation;
use craft\commerce\Plugin;
use craft\commerce\records\InventoryItem as InventoryItemRecord;
use craft\db\Query;
use craft\helpers\Db;
use Illuminate\Support\Collection;
use yii\base\Component;
use yii\base\InvalidConfigException;

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
        $inventoryItem = InventoryItemRecord::find()
            ->where(['id' => $id])
            ->one();

        if ($inventoryItem) {
            return $this->_populateInventoryItem($inventoryItem->toArray());
        }
    }

    /**
     * Returns an inventory level model which is the sum of all inventory movements types for an item in a location.
     *
     * @param InventoryItem $inventoryItem
     * @return ?InventoryLevel
     */
    public function getInventoryLevel(InventoryItem $inventoryItem, InventoryLocation $inventoryLocation): ?InventoryLevel
    {
        $result = $this->getInventoryLevelQuery()
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
        /** @var InventoryItemRecord $inventoryItemRecord */
        $inventoryItemRecord = InventoryItemRecord::find()
            ->where(['id' => $inventoryItem->id])
            ->one();

        if (!$inventoryItemRecord) {
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
     * @return InventoryLevel
     */
    private function _populateInventoryLevel(array $data): InventoryLevel
    {
        unset($data['purchasableId']);
        return new InventoryLevel($data);
    }

    /**
     * @param $inventoryLocation
     * @return Collection
     */
    public function getInventoryLocationLevels(InventoryLocation $inventoryLocation): Collection{
        $levels = $this->getInventoryLevelQuery()
            ->andWhere(['inventoryLocationId' => $inventoryLocation->id])
            ->all();


    }

    /**
     * Returns the totals for inventory items grouped by location and purchasable/inventoryItem.
     *
     * @param int|null $limit
     * @param int|null $offset
     * @return Query
     */
    public function getInventoryLevelQuery(?int $limit = null, ?int $offset = null): Query
    {
        $inventoryTotals = (new Query())
            ->select([
                'il.id as inventoryLocationId',
                'ii.id as inventoryItemId',
                'im.type',
                'COALESCE(SUM(im.quantity), 0) as quantity',
            ])
            ->from(['il' => Table::INVENTORYLOCATIONS]) // we want a record for every location and...
            ->join('JOIN', ['ii' => Table::INVENTORYITEMS]) //  we want a record for every location and item
            ->leftJoin(['im' => Table::INVENTORYMOVEMENTS], "im.inventoryLocationId = il.id and ii.id = im.inventoryItemId")
            ->groupBy(['il.id', 'ii.id', 'im.type']);

        $query = (new Query())
            ->select([
                'ii.id as inventoryItemId',
                'ii.purchasableId as purchasableId',
                'it.inventoryLocationId as inventoryLocationId',
                'SUM(CASE WHEN [[it.type]] = "available" THEN [[it.quantity]] ELSE 0 END) as availableTotal',
                'SUM(CASE WHEN [[it.type]] = "committed" THEN [[it.quantity]] ELSE 0 END) as committedTotal',
                'SUM(CASE WHEN [[it.type]] = "reserved" THEN [[it.quantity]] ELSE 0 END) as reservedTotal',
                'SUM(CASE WHEN [[it.type]] = "damaged" THEN [[it.quantity]] ELSE 0 END) as damagedTotal',
                'SUM(CASE WHEN [[it.type]] = "safety" THEN [[it.quantity]] ELSE 0 END) as safetyTotal',
                'SUM(CASE WHEN [[it.type]] = "qualityControl" THEN [[it.quantity]] ELSE 0 END) as qualityControlTotal',
                'SUM(CASE WHEN [[it.type]] = "incoming" THEN [[it.quantity]] ELSE 0 END) as incomingTotal',
                'SUM(CASE WHEN [[it.type]] IN ("qualityControl","safety","damaged","reserved") THEN [[it.quantity]] ELSE 0 END) as unavailableTotal',
                'SUM(CASE WHEN [[it.type]] IN ("qualityControl","safety","damaged","reserved", "available", "committed") THEN [[it.quantity]] ELSE 0 END) as onHandTotal',
            ])
            ->from(['ii' => Table::INVENTORYITEMS])
            ->leftJoin(['it' => $inventoryTotals], '[[it.inventoryItemId]] = [[ii.id]]')
            ->groupBy(['ii.id', 'it.inventoryLocationId'])
            ->limit($limit)
            ->offset($offset);

        return $query;
    }

    /**
     * @param UpdateInventoryLevelCollection $updateInventoryLevels
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
            return true;
        } catch (\Exception $e) {
            $transaction->rollBack();
            throw $e;
        }
    }

    /**
     * @param UpdateInventoryLevel $updateInventoryLevel
     * @return bool
     */
    private function _setInventoryLevel(UpdateInventoryLevel $updateInventoryLevel): bool
    {
        $tableName = Table::INVENTORYMOVEMENTS;

        if ($updateInventoryLevel->type === 'onHand') {
            $types = collect(InventoryMovementType::onHand())->pluck('value');
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
            $type = InventoryMovementType::AVAILABLE->value;
        }

        Craft::$app->db->createCommand()
            ->insert($tableName, [
                'quantity' => $quantityQuery,
                'type' => $type,
                'inventoryItemId' => $updateInventoryLevel->inventoryItem->id,
                'inventoryLocationId' => $updateInventoryLevel->inventoryLocation->id,
                'note' => $updateInventoryLevel->note,
                'movementHash' => $this->getMovementHash(),
                'dateCreated' => Db::prepareDateForDb(new \DateTime()),
                'userId' => Craft::$app->getUser()->getIdentity()?->id,
            ])->execute();

        return true;
    }

    /**
     * @param UpdateInventoryLevel $updateInventoryLevel
     * @return bool
     */
    private function _adjustInventoryLevel(UpdateInventoryLevel $updateInventoryLevel): bool
    {
        $tableName = Table::INVENTORYMOVEMENTS;

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
     * @param InventoryMovementCollection $inventoryMovement
     * @return bool
     * @throws \yii\db\Exception
     */
    public function executeInventoryMovements(InventoryMovementCollection $inventoryMovements): bool
    {
        $tableName = Table::INVENTORYMOVEMENTS;

        $db = Craft::$app->getDb();
        $transaction = $db->beginTransaction();

        try {
            /** @var InventoryMovement $inventoryMovement */
            foreach ($inventoryMovements as $inventoryMovement) {
                if (!$inventoryMovement->validate()) {
                    $transaction->rollBack();
                    return false;
                }

                $movementDate = Db::prepareDateForDb(new \DateTime());

                // First insert operation
                $fromInsertResult = $db->createCommand()
                    ->insert($tableName, [
                        'quantity' => -$inventoryMovement->quantity,
                        'type' => $inventoryMovement->fromInventoryMovementType->value,
                        'inventoryItemId' => $inventoryMovement->inventoryItem->id,
                        'inventoryLocationId' => $inventoryMovement->fromInventoryLocation->id,
                        'movementHash' => $inventoryMovement->getInventoryMovementHash(),
                        'dateCreated' => $movementDate,
                        'transferId' => $inventoryMovement->transferId,
                        'orderId' => $inventoryMovement->orderId,
                        'lineItemId' => $inventoryMovement->lineItemId,
                        'userId' => $inventoryMovement->userId,
                        'note' => $inventoryMovement->note,
                    ])
                    ->execute();

                if (!$fromInsertResult) {
                    $transaction->rollBack();
                    return false;
                }

                // Second insert operation
                $toInsertResult = $db->createCommand()
                    ->insert($tableName, [
                        'quantity' => $inventoryMovement->quantity,
                        'type' => $inventoryMovement->toInventoryMovementType->value,
                        'inventoryItemId' => $inventoryMovement->inventoryItem->id,
                        'inventoryLocationId' => $inventoryMovement->toInventoryLocation->id,
                        'movementHash' => $inventoryMovement->getInventoryMovementHash(),
                        'dateCreated' => $movementDate,
                        'transferId' => $inventoryMovement->transferId,
                        'orderId' => $inventoryMovement->orderId,
                        'lineItemId' => $inventoryMovement->lineItemId,
                        'userId' => $inventoryMovement->userId,
                        'note' => $inventoryMovement->note,
                    ])
                    ->execute();

                if (!$toInsertResult) {
                    $transaction->rollBack();
                    return false;
                }
            }

            $transaction->commit();
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
            ->leftJoin(['inventorymovements' => Table::INVENTORYMOVEMENTS], '[[inventorymovements.lineItemId]] = [[lineItems.id]]')
            ->where(['orders.isCompleted' => true])
            ->andWhere(['inventorymovements.inventoryItemId' => $inventoryItem->id])
            ->andWhere(['inventorymovements.inventoryLocationId' => $inventoryLocation->id])
            ->groupBy(['lineItems.orderId', 'lineItems.id'])
            ->having(['>=', 'SUM(inventorymovements.quantity)', 'lineItems.qty'])
            ->column();

        return Order::find()
            ->id($orderIds)
            ->all();
    }

    /**
     * @param InventoryItem $inventoryItem
     * @param InventoryLocation $inventoryLocation
     * @return Collection
     */
    public function getMovements(InventoryItem $inventoryItem, InventoryLocation $inventoryLocation): Collection
    {
        return (new Query())
            ->select(['type', 'movementHash', 'quantity', 'note', 'dateCreated'])
            ->from(Table::INVENTORYMOVEMENTS)
            ->where(['inventoryItemId' => $inventoryItem->id, 'inventoryLocationId' => $inventoryLocation->id])
            ->collect();
    }
}
