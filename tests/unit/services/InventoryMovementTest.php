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
use craft\commerce\models\inventory\InventoryManualMovement;
use craft\commerce\models\inventory\InventoryTransferMovement;
use craft\commerce\models\inventory\UpdateInventoryLevel;
use craft\commerce\models\InventoryFulfillmentLevel;
use craft\commerce\models\InventoryLocation;
use craft\commerce\models\LineItem;
use craft\commerce\models\TransferDetail;
use craft\commerce\Plugin;
use craft\db\Query;
use craft\helpers\Db;
use craftcommercetests\fixtures\ProductFixture;

/**
 * InventoryMovementTest.
 *
 * Covers the fix for craftcms/commerce#4268: fulfillment can't exceed the committed and
 * available stock at a location, and transfers into a location that's short for an order
 * draw down that order's RESERVED stock at the origin before plain AVAILABLE stock.
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

    // Fulfillment guard
    // =========================================================================

    /**
     * A fulfillment can't take more stock than the location can fulfill: committed + available.
     *
     * Location has 10 units, 6 of them committed to orders (committed 6, available 4). Fulfilling 11 must
     * fail, and fulfilling exactly 10 must pass.
     */
    public function testFulfillMovement_failsWhenExceedsFulfillable(): void
    {
        $itemId = $this->_getInventoryItemId();
        $location = $this->_createInventoryLocation('fulfillGuardA');

        // available=10, then 6 committed out of it (available=4, committed=6), fulfillable=10
        $this->_setAvailable($itemId, $location->id, 10);
        $this->_commit($itemId, $location, 6);

        $movement = $this->_fulfillMovement($itemId, $location, 11);
        self::assertFalse($movement->isValid());

        $movement = $this->_fulfillMovement($itemId, $location, 10);
        self::assertTrue($movement->isValid());
    }

    /**
     * The guard uses the net committed + available total, so a negative committed balance still lowers the limit
     * rather than breaking the check.
     *
     * Location has available 10 and committed -3, so 7 can be fulfilled: 8 must fail, 7 must pass.
     */
    public function testFulfillMovement_allowsDespiteNegativeCommitted(): void
    {
        $itemId = $this->_getInventoryItemId();
        $location = $this->_createInventoryLocation('fulfillGuardB');

        // available=10, committed adjusted straight to -3: fulfillable = 10 + -3 = 7.
        $this->_setAvailable($itemId, $location->id, 10);
        $this->_adjust($itemId, $location->id, InventoryTransactionType::COMMITTED, -3);

        $movement = $this->_fulfillMovement($itemId, $location, 8);
        self::assertFalse($movement->isValid(), 'Fulfilling beyond the fulfillable stock should fail even though committed is already negative.');

        $movement = $this->_fulfillMovement($itemId, $location, 7);
        self::assertTrue($movement->isValid(), 'Fulfilling up to the fulfillable stock should succeed despite committed being negative.');
    }

    /**
     * Stock that's physically on hand but unavailable (reserved for another order, damaged) can't be fulfilled.
     *
     * Location has 10 on hand: 5 committed, 3 reserved for a different order, 2 damaged, 0 available. Only the
     * 5 committed units can be fulfilled: 6 must fail, 5 must pass. Before #4268 the guard used on-hand (10).
     */
    public function testFulfillMovement_excludesUnavailableStock(): void
    {
        $itemId = $this->_getInventoryItemId();
        $location = $this->_createInventoryLocation('fulfillGuardC');
        $otherLineItem = $this->_createLineItem();

        // 10 on hand, but 3 of them are reserved for another order and 2 are damaged.
        $this->_setAvailable($itemId, $location->id, 10);
        $this->_commit($itemId, $location, 5);
        $this->_reserveFromAvailable($itemId, $location, 3, $otherLineItem->id);
        $this->_adjust($itemId, $location->id, InventoryTransactionType::AVAILABLE, -2);
        $this->_adjust($itemId, $location->id, InventoryTransactionType::DAMAGED, 2);

        self::assertFalse($this->_fulfillMovement($itemId, $location, 6)->isValid());
        self::assertTrue($this->_fulfillMovement($itemId, $location, 5)->isValid());
    }

    /**
     * When one fulfillment request has several rows for the same item and location (e.g. two line items for the
     * same variant), each row is validated against the stock the other rows will also use.
     *
     * Location can fulfill 10. A row of 6 alongside another 6 (12 total) must fail; alongside 4 (10 total) must pass.
     * OrdersController::actionFulfill() sets `otherPendingQuantity`.
     */
    public function testFulfillMovement_accountsForOtherPendingQuantity(): void
    {
        $itemId = $this->_getInventoryItemId();
        $location = $this->_createInventoryLocation('fulfillGuardD');

        $this->_setAvailable($itemId, $location->id, 10);
        $this->_commit($itemId, $location, 10);

        // Two line items for the same purchasable, fulfilled from the same location in one request.
        $movement = $this->_fulfillMovement($itemId, $location, 6);
        $movement->otherPendingQuantity = 6;
        self::assertFalse($movement->isValid());

        $movement->otherPendingQuantity = 4;
        self::assertTrue($movement->isValid());
    }

    /**
     * A negative quantity un-fulfills (returns fulfilled stock to committed), which never needs stock, so the guard
     * must let it through even at a location with no stock at all.
     */
    public function testFulfillMovement_allowsUnfulfilling(): void
    {
        $itemId = $this->_getInventoryItemId();
        $location = $this->_createInventoryLocation('fulfillGuardE');

        self::assertTrue($this->_fulfillMovement($itemId, $location, -2)->isValid());
    }

    /**
     * The fulfillment modal's per-row limit and warning, which come from InventoryFulfillmentLevel.
     *
     * An order for 15 is committed at a location that only has 10 units. The row's max must be 10 (not 15) and the
     * "Only 10 in stock at this location" warning must show. Once 11 are fulfilled, 4 are outstanding: max is 4
     * and there's no warning, since the location can cover them.
     */
    public function testFulfillmentLevel_maxFulfillQuantityIsLimitedByLocationStock(): void
    {
        $itemId = $this->_getInventoryItemId();
        $location = $this->_createInventoryLocation('fulfillmentLevelA');
        $lineItem = $this->_createLineItem();

        // An order for 15 committed at a location with only 10.
        $this->_setAvailable($itemId, $location->id, 10);
        $this->_commit($itemId, $location, 15, $lineItem->id);

        $level = new InventoryFulfillmentLevel([
            'inventoryItemId' => $itemId,
            'inventoryLocationId' => $location->id,
            'lineItemId' => $lineItem->id,
            'committedQuantity' => 15,
            'outstandingCommittedQuantity' => 15,
            'fulfilledQuantity' => 0,
        ]);

        self::assertSame(10, $level->getFulfillableQuantity());
        self::assertSame(10, $level->getMaxFulfillQuantity());
        self::assertTrue($level->getIsShortOfStock());

        $level = new InventoryFulfillmentLevel([
            'inventoryItemId' => $itemId,
            'inventoryLocationId' => $location->id,
            'lineItemId' => $lineItem->id,
            'committedQuantity' => 15,
            'outstandingCommittedQuantity' => 4,
            'fulfilledQuantity' => 11,
        ]);

        self::assertSame(4, $level->getMaxFulfillQuantity());
        self::assertFalse($level->getIsShortOfStock());
    }

    // getReservedQuantitiesByLineItem()
    // =========================================================================

    /**
     * Reservations are returned per order line item, oldest first, so transfers use them up in a fair order.
     * Reservations with no line item (added manually, not from an order shortfall) aren't returned, so a
     * transfer never consumes them.
     */
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

    /**
     * Passing a committed location returns only the reservations whose line item still has outstanding committed
     * stock there, i.e. the reservations that back that location's shortfall.
     *
     * Two line items are committed at the primary location and both have stock reserved at the secondary location.
     * One is fully fulfilled (committed nets to 0), so only the other's reservation is returned. A location neither
     * was committed at returns nothing.
     */
    public function testGetReservedQuantitiesByLineItem_committedLocationFilterExcludesFulfilledLineItems(): void
    {
        $itemId = $this->_getInventoryItemId();
        $primary = $this->_createInventoryLocation('filterPrimary');
        $secondary = $this->_createInventoryLocation('filterSecondary');
        $fulfilledLineItem = $this->_createLineItem();
        $outstandingLineItem = $this->_createLineItem();

        $this->_setAvailable($itemId, $primary->id, 10);
        $this->_commit($itemId, $primary, 5, $fulfilledLineItem->id);
        $this->_commit($itemId, $primary, 3, $outstandingLineItem->id);

        // Fully fulfil the first line item, so its committed total at the primary location nets to 0.
        $fulfillMovement = $this->_fulfillMovement($itemId, $primary, 5, $fulfilledLineItem->id);
        self::assertTrue(Plugin::getInstance()->getInventory()->executeInventoryMovements(
            (new InventoryMovementCollection())->push($fulfillMovement)
        ));

        $this->_insertRawTransaction($itemId, $secondary->id, InventoryTransactionType::RESERVED, 2, $fulfilledLineItem->id, null, '2024-01-01 00:00:00');
        $this->_insertRawTransaction($itemId, $secondary->id, InventoryTransactionType::RESERVED, 2, $outstandingLineItem->id, null, '2024-02-01 00:00:00');

        $inventory = Plugin::getInstance()->getInventory();

        self::assertCount(2, $inventory->getReservedQuantitiesByLineItem($itemId, $secondary->id));

        $filtered = $inventory->getReservedQuantitiesByLineItem($itemId, $secondary->id, $primary->id)->values();
        self::assertCount(1, $filtered);
        self::assertSame($outstandingLineItem->id, $filtered[0]['lineItemId']);

        // A location the line items were never committed to matches nothing.
        self::assertCount(0, $inventory->getReservedQuantitiesByLineItem($itemId, $secondary->id, $secondary->id));
    }

    // Issue #4268 end-to-end scenario
    // =========================================================================

    /**
     * The scenario from issue #4268, end to end.
     *
     * Two locations with 10 units each; an order for 15 is completed. All 15 are committed at the primary location
     * (available -5) and the 5 unit shortfall is reserved at the secondary location. Then:
     * - Fulfilling 15 at the primary location fails, since only 10 are there.
     * - Transferring 5 from secondary to primary takes them from the reservation, not from available stock.
     * - Receiving them fills the primary location's shortfall: available 0, committed still 15 (not 20, the
     *   double count the first version of this fix had), on hand 15.
     * - Fulfilling all 15 is now allowed.
     */
    public function testIssue4268_transferIntoCommittedLocationResolvesShortfall(): void
    {
        $itemId = $this->_getInventoryItemId();
        $primary = $this->_createInventoryLocation('issuePrimary');
        $secondary = $this->_createInventoryLocation('issueSecondary');
        $lineItem = $this->_createLineItem();

        // 10 at each location, then an order for 15 is completed: all 15 committed at the
        // primary location (pushing its available to -5) and the 5 unit shortfall reserved
        // at the secondary location, exactly as Inventory::orderCompleteHandler() does.
        $this->_setAvailable($itemId, $primary->id, 10);
        $this->_setAvailable($itemId, $secondary->id, 10);
        $this->_commit($itemId, $primary, 15, $lineItem->id);
        $this->_reserveFromAvailable($itemId, $secondary, 5, $lineItem->id);

        // Only 10 are physically at the primary location.
        self::assertFalse($this->_fulfillMovement($itemId, $primary, 15, $lineItem->id)->isValid());
        self::assertTrue($this->_fulfillMovement($itemId, $primary, 10, $lineItem->id)->isValid());

        $detail = new TransferDetail(['inventoryItemId' => $itemId, 'quantity' => 5]);
        $transfer = $this->_createDraftTransfer($secondary->id, $primary->id, [$detail]);
        $transfer->setTransferStatus(TransferStatusType::PENDING);
        Craft::$app->getElements()->saveElement($transfer, false);

        $secondaryLevel = Plugin::getInstance()->getInventory()->getInventoryLevel($itemId, $secondary->id);
        self::assertSame(0, $secondaryLevel->reservedTotal, 'The reservation should travel with the transfer.');
        self::assertSame(5, $secondaryLevel->availableTotal, 'Available stock at the origin should be untouched.');

        $this->_receive($transfer, $detail, 5);

        $primaryLevel = Plugin::getInstance()->getInventory()->getInventoryLevel($itemId, $primary->id);
        self::assertSame(0, $primaryLevel->incomingTotal);
        self::assertSame(15, $primaryLevel->committedTotal, 'The order is still only committed for 15.');
        self::assertSame(0, $primaryLevel->availableTotal, 'Received stock should fill the primary location shortfall.');
        self::assertSame(15, $primaryLevel->onHandTotal);

        self::assertTrue($this->_fulfillMovement($itemId, $primary, 15, $lineItem->id)->isValid());
    }

    /**
     * A reservation only backs the location the order is committed at, so moving stock to any other location
     * must not consume it.
     *
     * Same order as above (committed at primary, 5 reserved at secondary). Transferring 3 from secondary to a third
     * location must leave the 5 reserved and take the 3 from available (10 - 5 - 3 = 2).
     */
    public function testIssue4268_transferElsewhereLeavesReservationInPlace(): void
    {
        $itemId = $this->_getInventoryItemId();
        $primary = $this->_createInventoryLocation('issuePrimary2');
        $secondary = $this->_createInventoryLocation('issueSecondary2');
        $other = $this->_createInventoryLocation('issueOther2');
        $lineItem = $this->_createLineItem();

        $this->_setAvailable($itemId, $primary->id, 10);
        $this->_setAvailable($itemId, $secondary->id, 10);
        $this->_commit($itemId, $primary, 15, $lineItem->id);
        $this->_reserveFromAvailable($itemId, $secondary, 5, $lineItem->id);

        // Moving stock somewhere that isn't short for the order mustn't consume the order's reservation.
        $detail = new TransferDetail(['inventoryItemId' => $itemId, 'quantity' => 3]);
        $transfer = $this->_createDraftTransfer($secondary->id, $other->id, [$detail]);
        $transfer->setTransferStatus(TransferStatusType::PENDING);
        Craft::$app->getElements()->saveElement($transfer, false);

        $secondaryLevel = Plugin::getInstance()->getInventory()->getInventoryLevel($itemId, $secondary->id);
        self::assertSame(5, $secondaryLevel->reservedTotal);
        self::assertSame(2, $secondaryLevel->availableTotal);
    }

    // Transfers drawing down reservations
    // =========================================================================

    /**
     * When a transfer is bigger than the reservation, it takes the reservation first, then available stock for
     * the rest.
     *
     * 5 are reserved at the secondary location for an order committed at the primary location. Transferring 8 must
     * write two debits at the origin: reserved -5 tagged with the line item, and available -3 with no line item.
     * After receiving, the primary location has its 5 unit shortfall filled plus 3 spare (available 3).
     */
    public function testTransfer_debitsReservationThenAvailable(): void
    {
        $itemId = $this->_getInventoryItemId();
        $primary = $this->_createInventoryLocation('splitPrimary');
        $secondary = $this->_createInventoryLocation('splitSecondary');
        $lineItem = $this->_createLineItem();

        $this->_setAvailable($itemId, $primary->id, 10);
        $this->_setAvailable($itemId, $secondary->id, 10);
        $this->_commit($itemId, $primary, 15, $lineItem->id);
        $this->_reserveFromAvailable($itemId, $secondary, 5, $lineItem->id);

        // 8 is more than the 5 reserved, so 5 should come from reserved and 3 from available.
        $detail = new TransferDetail(['inventoryItemId' => $itemId, 'quantity' => 8]);
        $transfer = $this->_createDraftTransfer($secondary->id, $primary->id, [$detail]);
        $transfer->setTransferStatus(TransferStatusType::PENDING);
        Craft::$app->getElements()->saveElement($transfer, false);

        $secondaryLevel = Plugin::getInstance()->getInventory()->getInventoryLevel($itemId, $secondary->id);
        self::assertSame(0, $secondaryLevel->reservedTotal);
        self::assertSame(2, $secondaryLevel->availableTotal);

        $transferDebits = (new Query())
            ->select(['type', 'quantity', 'lineItemId'])
            ->from(Table::INVENTORYTRANSACTIONS)
            ->where(['transferId' => $transfer->id, 'inventoryLocationId' => $secondary->id])
            ->orderBy(['type' => SORT_ASC])
            ->all();

        self::assertCount(2, $transferDebits);
        self::assertSame(InventoryTransactionType::AVAILABLE->value, $transferDebits[0]['type']);
        self::assertSame(-3, (int)$transferDebits[0]['quantity']);
        self::assertNull($transferDebits[0]['lineItemId']);
        self::assertSame(InventoryTransactionType::RESERVED->value, $transferDebits[1]['type']);
        self::assertSame(-5, (int)$transferDebits[1]['quantity']);
        self::assertSame($lineItem->id, (int)$transferDebits[1]['lineItemId']);

        self::assertSame(8, Plugin::getInstance()->getInventory()->getInventoryLevel($itemId, $primary->id)->incomingTotal);

        $this->_receive($transfer, $detail, 8);

        $primaryLevel = Plugin::getInstance()->getInventory()->getInventoryLevel($itemId, $primary->id);
        self::assertSame(0, $primaryLevel->incomingTotal);
        self::assertSame(15, $primaryLevel->committedTotal);
        self::assertSame(3, $primaryLevel->availableTotal);
    }

    /**
     * With reservations for several orders at the origin, a transfer uses them up oldest first.
     *
     * Two orders committed at the primary location have stock reserved at the secondary location: the older one 3,
     * the newer one 4. Transferring 5 must use all 3 of the older reservation, then 2 of the newer one, leaving 2
     * reserved for the newer order and available stock untouched.
     */
    public function testTransfer_consumesReservationsOldestFirst(): void
    {
        $itemId = $this->_getInventoryItemId();
        $primary = $this->_createInventoryLocation('fifoPrimary');
        $secondary = $this->_createInventoryLocation('fifoSecondary');
        $newerLineItem = $this->_createLineItem();
        $olderLineItem = $this->_createLineItem();

        $this->_setAvailable($itemId, $primary->id, 2);
        $this->_commit($itemId, $primary, 6, $newerLineItem->id);
        $this->_commit($itemId, $primary, 5, $olderLineItem->id);

        // Explicit timestamps, inserted newest first, so the order comes from dateCreated rather than insertion.
        $this->_setAvailable($itemId, $secondary->id, 10);
        $this->_insertRawTransaction($itemId, $secondary->id, InventoryTransactionType::RESERVED, 4, $newerLineItem->id, null, '2024-06-01 00:00:00');
        $this->_insertRawTransaction($itemId, $secondary->id, InventoryTransactionType::RESERVED, 3, $olderLineItem->id, null, '2024-01-01 00:00:00');

        $detail = new TransferDetail(['inventoryItemId' => $itemId, 'quantity' => 5]);
        $transfer = $this->_createDraftTransfer($secondary->id, $primary->id, [$detail]);
        $transfer->setTransferStatus(TransferStatusType::PENDING);
        Craft::$app->getElements()->saveElement($transfer, false);

        // The older reservation (3) is used up entirely, then 2 of the newer one (4).
        $remaining = Plugin::getInstance()->getInventory()->getReservedQuantitiesByLineItem($itemId, $secondary->id)->values();
        self::assertCount(1, $remaining);
        self::assertSame($newerLineItem->id, $remaining[0]['lineItemId']);
        self::assertSame(2, $remaining[0]['reservedQty']);

        self::assertSame(10, Plugin::getInstance()->getInventory()->getInventoryLevel($itemId, $secondary->id)->availableTotal, 'Available stock at the origin should be untouched.');
    }

    // Helpers
    // =========================================================================

    /**
     * Mirrors TransfersController::actionReceiveTransfer() for a single detail.
     */
    private function _receive(Transfer $transfer, TransferDetail $detail, int $acceptedAmount): void
    {
        $movement = new InventoryTransferMovement();
        $movement->quantity = $acceptedAmount;
        $movement->transferId = $transfer->id;
        $movement->inventoryItemId = $detail->inventoryItemId;
        $movement->toInventoryLocation = $transfer->getDestinationLocation();
        $movement->fromInventoryLocation = $transfer->getDestinationLocation();
        $movement->toInventoryTransactionType = InventoryTransactionType::AVAILABLE;
        $movement->fromInventoryTransactionType = InventoryTransactionType::INCOMING;

        Plugin::getInstance()->getInventory()->executeInventoryMovements(
            (new InventoryMovementCollection())->push($movement)
        );
    }

    private function _reserveFromAvailable(int $inventoryItemId, InventoryLocation $location, int $quantity, int $lineItemId): void
    {
        $movement = new InventoryManualMovement();
        $movement->inventoryItemId = $inventoryItemId;
        $movement->fromInventoryLocation = $location;
        $movement->toInventoryLocation = $location;
        $movement->fromInventoryTransactionType = InventoryTransactionType::AVAILABLE;
        $movement->toInventoryTransactionType = InventoryTransactionType::RESERVED;
        $movement->quantity = $quantity;
        $movement->lineItemId = $lineItemId;

        Plugin::getInstance()->getInventory()->executeInventoryMovements(
            (new InventoryMovementCollection())->push($movement)
        );
    }

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

    private function _fulfillMovement(int $inventoryItemId, InventoryLocation $location, int $quantity, ?int $lineItemId = null): InventoryFulfillMovement
    {
        $movement = new InventoryFulfillMovement();
        $movement->lineItemId = $lineItemId;
        $movement->inventoryItemId = $inventoryItemId;
        $movement->fromInventoryLocation = $location;
        $movement->toInventoryLocation = $location;
        $movement->fromInventoryTransactionType = InventoryTransactionType::COMMITTED;
        $movement->toInventoryTransactionType = InventoryTransactionType::FULFILLED;
        $movement->quantity = $quantity;

        return $movement;
    }

    private function _commit(int $inventoryItemId, InventoryLocation $location, int $quantity, ?int $lineItemId = null): void
    {
        $movement = new InventoryCommittedMovement();
        $movement->lineItemId = $lineItemId;
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
