<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\services;

use Codeception\Attribute\Group;
use Codeception\Test\Unit;
use Craft;
use craft\commerce\collections\InventoryMovementCollection;
use craft\commerce\collections\UpdateInventoryLevelCollection;
use craft\commerce\db\Table;
use craft\commerce\elements\Order;
use craft\commerce\elements\Transfer;
use craft\commerce\elements\Variant;
use craft\commerce\enums\InventoryTransactionType;
use craft\commerce\enums\InventoryUpdateQuantityType;
use craft\commerce\enums\TransferStatusType;
use craft\commerce\models\inventory\InventoryCommittedMovement;
use craft\commerce\models\inventory\InventoryFulfillMovement;
use craft\commerce\models\inventory\InventoryTransferMovement;
use craft\commerce\models\inventory\UpdateInventoryLevel;
use craft\commerce\models\InventoryLocation;
use craft\commerce\models\LineItem;
use craft\commerce\models\TransferDetail;
use craft\commerce\Plugin;
use craft\helpers\Db;
use craftcommercetests\fixtures\ProductFixture;

/**
 * InventoryMovementTest.
 *
 * Covers the fix for craftcms/commerce#4268: fulfillment can no longer exceed physical
 * on-hand stock, and inventory transfers draw down order-linked RESERVED stock before
 * plain AVAILABLE stock, resolving the corresponding order's shortfall into COMMITTED
 * stock at the destination location once received.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.0.0
 */
#[Group('inventory')]
class InventoryMovementTest extends Unit
{
    /**
     * @return array
     */
    public function _fixtures(): array
    {
        return [
            'products' => [
                'class' => ProductFixture::class,
            ],
        ];
    }

    public function testGetInventoryItems()
    {
        Plugin::getInstance()->getInventory();
    }

    // Fulfillment on-hand guard
    // =========================================================================

    public function testFulfillMovement_failsWhenExceedsOnHand(): void
    {
        $itemId = $this->_getInventoryItemId();
        $location = $this->_createInventoryLocation('fulfillGuardA');

        // available=10, then 6 committed out of it (available=4, committed=6), onHand=10
        $this->_setAvailable($itemId, $location->id, 10);
        $this->_commit($itemId, $location, 6);

        $movement = $this->_fulfillMovement($itemId, $location, 11);
        self::assertFalse($movement->isValid());

        $movement = $this->_fulfillMovement($itemId, $location, 10);
        self::assertTrue($movement->isValid());
    }

    public function testFulfillMovement_allowsDespiteNegativeCommitted(): void
    {
        $itemId = $this->_getInventoryItemId();
        $location = $this->_createInventoryLocation('fulfillGuardB');

        // available=10; committed pushed to -3 directly (as happens today when
        // orderCompleteHandler() over-commits a location beyond what's available).
        // onHand = 10 + -3 = 7, which is what fulfillment should actually be bound by.
        $this->_setAvailable($itemId, $location->id, 10);
        $this->_adjust($itemId, $location->id, InventoryTransactionType::COMMITTED, -3);

        $movement = $this->_fulfillMovement($itemId, $location, 8);
        self::assertFalse($movement->isValid(), 'Fulfilling beyond true on-hand stock should fail even though committed is already negative.');

        $movement = $this->_fulfillMovement($itemId, $location, 7);
        self::assertTrue($movement->isValid(), 'Fulfilling up to true on-hand stock should succeed despite committed being negative.');
    }

    // getReservedQuantitiesByLineItem()
    // =========================================================================

    public function testGetReservedQuantitiesByLineItem_excludesAdHocAndOrdersOldestFirst(): void
    {
        $itemId = $this->_getInventoryItemId();
        $location = $this->_createInventoryLocation('reservedByLineItem');

        $lineItem1 = $this->_createLineItem();
        $lineItem2 = $this->_createLineItem();

        // Inserted out of chronological order, with explicit timestamps, to prove the
        // query orders by the reservation's own dateCreated rather than insertion order.
        $this->_insertRawTransaction($itemId, $location->id, InventoryTransactionType::RESERVED, 3, $lineItem1->id, null, '2024-06-01 00:00:00');
        $this->_insertRawTransaction($itemId, $location->id, InventoryTransactionType::RESERVED, 2, $lineItem2->id, null, '2024-01-01 00:00:00');
        // Ad-hoc manual reservation, not linked to any order — must be excluded.
        $this->_insertRawTransaction($itemId, $location->id, InventoryTransactionType::RESERVED, 5, null, null, '2023-01-01 00:00:00');

        $reservations = Plugin::getInstance()->getInventory()->getReservedQuantitiesByLineItem($itemId, $location->id)->values();

        self::assertCount(2, $reservations);
        self::assertSame($lineItem2->id, $reservations[0]['lineItemId']);
        self::assertSame(2, $reservations[0]['reservedQty']);
        self::assertSame($lineItem1->id, $reservations[1]['lineItemId']);
        self::assertSame(3, $reservations[1]['reservedQty']);
    }

    // getOutstandingTransferReservations()
    // =========================================================================

    public function testGetOutstandingTransferReservations_netsEarmarkedAgainstAlreadyCommitted(): void
    {
        $itemId = $this->_getInventoryItemId();
        $origin = $this->_createInventoryLocation('warehouseA1');
        $destination = $this->_createInventoryLocation('warehouseB');
        $transfer = $this->_createDraftTransfer($origin->id, $destination->id);

        $lineItem1 = $this->_createLineItem();
        $lineItem2 = $this->_createLineItem();

        // Earmarked (RESERVED debit) at the origin, tagged to this transfer.
        $this->_insertRawTransaction($itemId, $origin->id, InventoryTransactionType::RESERVED, -3, $lineItem1->id, $transfer->id, '2024-01-01 00:00:00');
        $this->_insertRawTransaction($itemId, $origin->id, InventoryTransactionType::RESERVED, -2, $lineItem2->id, $transfer->id, '2024-02-01 00:00:00');

        // Simulate a prior partial receive that already resolved 1 unit of lineItem1's reservation.
        $this->_insertRawTransaction($itemId, $destination->id, InventoryTransactionType::COMMITTED, 1, $lineItem1->id, $transfer->id, '2024-03-01 00:00:00');

        $outstanding = Plugin::getInstance()->getInventory()->getOutstandingTransferReservations(
            $transfer->id,
            $itemId,
            $origin->id,
            $destination->id
        )->keyBy('lineItemId');

        self::assertSame(2, $outstanding[$lineItem1->id]['remainingQty']);
        self::assertSame(2, $outstanding[$lineItem2->id]['remainingQty']);
    }

    // Full transfer lifecycle
    // =========================================================================

    public function testTransferLifecycle_resolvesReservedStockIntoDestinationCommitted(): void
    {
        $itemId = $this->_getInventoryItemId();
        $origin = $this->_createInventoryLocation('warehouseA2');
        $destination = $this->_createInventoryLocation('warehouseC');

        $lineItem = $this->_createLineItem();

        // available=10, reserved=4 (earmarked for $lineItem) at the origin.
        $this->_setAvailable($itemId, $origin->id, 10);
        $this->_reserve($itemId, $origin->id, 4, $lineItem->id);

        $detail = new TransferDetail(['inventoryItemId' => $itemId, 'quantity' => 6]);
        $transfer = $this->_createDraftTransfer($origin->id, $destination->id, [$detail]);

        // Draft -> Pending: should debit the 4 reserved units first, then 2 from available.
        $transfer->setTransferStatus(TransferStatusType::PENDING);
        Craft::$app->getElements()->saveElement($transfer, false);

        $originLevel = Plugin::getInstance()->getInventory()->getInventoryLevel($itemId, $origin->id);
        self::assertSame(0, $originLevel->reservedTotal);
        self::assertSame(8, $originLevel->availableTotal);

        $destinationLevel = Plugin::getInstance()->getInventory()->getInventoryLevel($itemId, $destination->id);
        self::assertSame(6, $destinationLevel->incomingTotal);

        self::assertCount(0, Plugin::getInstance()->getInventory()->getReservedQuantitiesByLineItem($itemId, $origin->id));

        // Receive: mirrors TransfersController::actionReceiveTransfer()'s reservation-aware accept logic.
        $inventoryMovementCollection = new InventoryMovementCollection();
        $remainingAccepted = 6;
        foreach (Plugin::getInstance()->getInventory()->getOutstandingTransferReservations($transfer->id, $itemId, $origin->id, $destination->id) as $reservation) {
            $qty = min($remainingAccepted, $reservation['remainingQty']);

            $committedMovement = new InventoryTransferMovement();
            $committedMovement->quantity = $qty;
            $committedMovement->transferId = $transfer->id;
            $committedMovement->lineItemId = $reservation['lineItemId'];
            $committedMovement->inventoryItemId = $itemId;
            $committedMovement->toInventoryLocation = $destination;
            $committedMovement->fromInventoryLocation = $destination;
            $committedMovement->toInventoryTransactionType = InventoryTransactionType::COMMITTED;
            $committedMovement->fromInventoryTransactionType = InventoryTransactionType::INCOMING;

            $inventoryMovementCollection->push($committedMovement);
            $remainingAccepted -= $qty;
        }

        if ($remainingAccepted > 0) {
            $availableMovement = new InventoryTransferMovement();
            $availableMovement->quantity = $remainingAccepted;
            $availableMovement->transferId = $transfer->id;
            $availableMovement->inventoryItemId = $itemId;
            $availableMovement->toInventoryLocation = $destination;
            $availableMovement->fromInventoryLocation = $destination;
            $availableMovement->toInventoryTransactionType = InventoryTransactionType::AVAILABLE;
            $availableMovement->fromInventoryTransactionType = InventoryTransactionType::INCOMING;

            $inventoryMovementCollection->push($availableMovement);
        }

        Plugin::getInstance()->getInventory()->executeInventoryMovements($inventoryMovementCollection);

        $destinationLevel = Plugin::getInstance()->getInventory()->getInventoryLevel($itemId, $destination->id);
        self::assertSame(0, $destinationLevel->incomingTotal);
        self::assertSame(4, $destinationLevel->committedTotal);
        self::assertSame(2, $destinationLevel->availableTotal);
    }

    // Helpers
    // =========================================================================

    private function _getInventoryItemId(): int
    {
        return Variant::find()->sku('rad-hood')->one()->inventoryItemId;
    }

    private function _createInventoryLocation(string $handle): InventoryLocation
    {
        $location = new InventoryLocation(['name' => $handle, 'handle' => $handle]);
        Plugin::getInstance()->getInventoryLocations()->saveInventoryLocation($location);

        return $location;
    }

    private function _createLineItem(string $sku = 'rad-hood'): LineItem
    {
        $variant = Variant::find()->sku($sku)->one();

        $order = new Order();
        Craft::$app->getElements()->saveElement($order, false);

        $lineItem = Plugin::getInstance()->getLineItems()->create($order, [
            'purchasableId' => $variant->id,
        ]);
        $order->setLineItems([$lineItem]);
        Craft::$app->getElements()->saveElement($order, false);

        return $order->getLineItems()[0];
    }

    private function _createDraftTransfer(int $originLocationId, int $destinationLocationId, array $details = []): Transfer
    {
        $transfer = new Transfer();
        $transfer->originLocationId = $originLocationId;
        $transfer->destinationLocationId = $destinationLocationId;

        if ($details) {
            $transfer->setDetails($details);
        }

        Craft::$app->getElements()->saveElement($transfer, false);

        return $transfer;
    }

    private function _fulfillMovement(int $inventoryItemId, InventoryLocation $location, int $quantity): InventoryFulfillMovement
    {
        $movement = new InventoryFulfillMovement();
        $movement->inventoryItemId = $inventoryItemId;
        $movement->fromInventoryLocation = $location;
        $movement->toInventoryLocation = $location;
        $movement->fromInventoryTransactionType = InventoryTransactionType::COMMITTED;
        $movement->toInventoryTransactionType = InventoryTransactionType::FULFILLED;
        $movement->quantity = $quantity;

        return $movement;
    }

    private function _commit(int $inventoryItemId, InventoryLocation $location, int $quantity): void
    {
        $movement = new InventoryCommittedMovement();
        $movement->inventoryItemId = $inventoryItemId;
        $movement->fromInventoryLocation = $location;
        $movement->toInventoryLocation = $location;
        $movement->fromInventoryTransactionType = InventoryTransactionType::AVAILABLE;
        $movement->toInventoryTransactionType = InventoryTransactionType::COMMITTED;
        $movement->quantity = $quantity;

        Plugin::getInstance()->getInventory()->executeInventoryMovements(
            (new InventoryMovementCollection())->push($movement)
        );
    }

    private function _setAvailable(int $inventoryItemId, int $inventoryLocationId, int $quantity): void
    {
        $update = new UpdateInventoryLevel();
        $update->type = InventoryTransactionType::AVAILABLE->value;
        $update->updateAction = InventoryUpdateQuantityType::SET;
        $update->inventoryItemId = $inventoryItemId;
        $update->inventoryLocationId = $inventoryLocationId;
        $update->quantity = $quantity;

        Plugin::getInstance()->getInventory()->executeUpdateInventoryLevels(
            (new UpdateInventoryLevelCollection())->push($update)
        );
    }

    private function _reserve(int $inventoryItemId, int $inventoryLocationId, int $quantity, int $lineItemId): void
    {
        $update = new UpdateInventoryLevel();
        $update->type = InventoryTransactionType::RESERVED->value;
        $update->updateAction = InventoryUpdateQuantityType::ADJUST;
        $update->inventoryItemId = $inventoryItemId;
        $update->inventoryLocationId = $inventoryLocationId;
        $update->quantity = $quantity;
        $update->lineItemId = $lineItemId;

        Plugin::getInstance()->getInventory()->executeUpdateInventoryLevels(
            (new UpdateInventoryLevelCollection())->push($update)
        );
    }

    private function _adjust(int $inventoryItemId, int $inventoryLocationId, InventoryTransactionType $type, int $quantity): void
    {
        $update = new UpdateInventoryLevel();
        $update->type = $type->value;
        $update->updateAction = InventoryUpdateQuantityType::ADJUST;
        $update->inventoryItemId = $inventoryItemId;
        $update->inventoryLocationId = $inventoryLocationId;
        $update->quantity = $quantity;

        Plugin::getInstance()->getInventory()->executeUpdateInventoryLevels(
            (new UpdateInventoryLevelCollection())->push($update)
        );
    }

    private function _insertRawTransaction(int $inventoryItemId, int $inventoryLocationId, InventoryTransactionType $type, int $quantity, ?int $lineItemId, ?int $transferId, string $dateCreated): void
    {
        Craft::$app->getDb()->createCommand()->insert(Table::INVENTORYTRANSACTIONS, [
            'inventoryItemId' => $inventoryItemId,
            'inventoryLocationId' => $inventoryLocationId,
            'movementHash' => Plugin::getInstance()->getInventory()->getMovementHash(),
            'quantity' => $quantity,
            'type' => $type->value,
            'lineItemId' => $lineItemId,
            'transferId' => $transferId,
            'dateCreated' => Db::prepareDateForDb(new \DateTime($dateCreated)),
        ])->execute();
    }
}
