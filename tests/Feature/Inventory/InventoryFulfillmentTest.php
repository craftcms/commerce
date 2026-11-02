<?php

declare(strict_types=1);

use CraftCms\Cms\Address\Elements\Address;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Inventory\Collections\InventoryMovementCollection;
use CraftCms\Commerce\Inventory\Collections\UpdateInventoryLevelCollection;
use CraftCms\Commerce\Inventory\Data\InventoryCommittedMovement;
use CraftCms\Commerce\Inventory\Data\InventoryFulfillmentLevel;
use CraftCms\Commerce\Inventory\Data\InventoryFulfillMovement;
use CraftCms\Commerce\Inventory\Data\InventoryLocation;
use CraftCms\Commerce\Inventory\Data\InventoryManualMovement;
use CraftCms\Commerce\Inventory\Data\InventoryTransferMovement;
use CraftCms\Commerce\Inventory\Data\UpdateInventoryLevel;
use CraftCms\Commerce\Inventory\Enums\InventoryTransactionType;
use CraftCms\Commerce\Inventory\Enums\InventoryUpdateQuantityType;
use CraftCms\Commerce\Inventory\Inventory;
use CraftCms\Commerce\Inventory\InventoryLocations;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\LineItem\Data\LineItem;
use CraftCms\Commerce\Order\LineItem\LineItems;
use CraftCms\Commerce\Tests\Support\ProductConditionsFixture;
use CraftCms\Commerce\Transfer\Elements\Transfer;
use CraftCms\Commerce\Transfer\Transfers;
use Illuminate\Support\Facades\DB;

beforeEach(function() {
    $this->variant = ProductConditionsFixture::seed()->hoodieVariant;
    $this->itemId = $this->variant->inventoryItemId;
});

function fulfillmentTestLocation(string $handle): InventoryLocation
{
    $address = new Address();
    $address->countryCode = 'US';
    $address->title = $handle;
    Elements::saveElement($address);

    $location = new InventoryLocation();
    $location->name = ucfirst($handle);
    $location->handle = $handle;
    $location->setAddress($address);

    if (!app(InventoryLocations::class)->saveInventoryLocation($location)) {
        throw new RuntimeException('Could not save inventory location: ' . json_encode($location->errors()->all()));
    }

    return $location;
}

function fulfillmentTestLineItem(int $purchasableId, int $qty = 1, bool $completeOrder = false): LineItem
{
    $order = new Order();
    Elements::saveElement($order, false);

    $lineItem = app(LineItems::class)->create($order, [
        'purchasableId' => $purchasableId,
        'qty' => $qty,
    ]);
    $order->setLineItems([$lineItem]);
    Elements::saveElement($order, false);

    if ($completeOrder) {
        // Marked complete directly, so order completion doesn't make its own inventory movements
        DB::table(Table::ORDERS)->where('id', $order->id)->update(['isCompleted' => true, 'dateOrdered' => now()->toDateTimeString()]);
    }

    return $order->getLineItems()[0];
}

function fulfillmentTestUpdate(int $itemId, int $locationId, InventoryTransactionType $type, InventoryUpdateQuantityType $action, int $quantity, ?int $lineItemId = null): void
{
    app(Inventory::class)->executeUpdateInventoryLevels(
        (new UpdateInventoryLevelCollection())->push(new UpdateInventoryLevel([
            'type' => $type->value,
            'updateAction' => $action,
            'inventoryItemId' => $itemId,
            'inventoryLocationId' => $locationId,
            'quantity' => $quantity,
            'lineItemId' => $lineItemId,
        ]))
    );
}

function fulfillmentTestSetAvailable(int $itemId, int $locationId, int $quantity): void
{
    fulfillmentTestUpdate($itemId, $locationId, InventoryTransactionType::AVAILABLE, InventoryUpdateQuantityType::SET, $quantity);
}

function fulfillmentTestAdjust(int $itemId, int $locationId, InventoryTransactionType $type, int $quantity): void
{
    fulfillmentTestUpdate($itemId, $locationId, $type, InventoryUpdateQuantityType::ADJUST, $quantity);
}

function fulfillmentTestMove(string $class, int $itemId, InventoryLocation $location, InventoryTransactionType $from, InventoryTransactionType $to, int $quantity, ?int $lineItemId = null): void
{
    $movement = new $class();
    $movement->lineItemId = $lineItemId;
    $movement->inventoryItemId = $itemId;
    $movement->fromInventoryLocation = $location;
    $movement->toInventoryLocation = $location;
    $movement->fromInventoryTransactionType = $from;
    $movement->toInventoryTransactionType = $to;
    $movement->quantity = $quantity;

    app(Inventory::class)->executeInventoryMovements((new InventoryMovementCollection())->push($movement));
}

function fulfillmentTestCommit(int $itemId, InventoryLocation $location, int $quantity, ?int $lineItemId = null): void
{
    fulfillmentTestMove(InventoryCommittedMovement::class, $itemId, $location, InventoryTransactionType::AVAILABLE, InventoryTransactionType::COMMITTED, $quantity, $lineItemId);
}

function fulfillmentTestReserve(int $itemId, InventoryLocation $location, int $quantity, int $lineItemId): void
{
    fulfillmentTestMove(InventoryManualMovement::class, $itemId, $location, InventoryTransactionType::AVAILABLE, InventoryTransactionType::RESERVED, $quantity, $lineItemId);
}

function fulfillmentTestMovement(int $itemId, InventoryLocation $location, int $quantity, ?int $lineItemId = null): InventoryFulfillMovement
{
    $movement = new InventoryFulfillMovement();
    $movement->lineItemId = $lineItemId;
    $movement->inventoryItemId = $itemId;
    $movement->fromInventoryLocation = $location;
    $movement->toInventoryLocation = $location;
    $movement->fromInventoryTransactionType = InventoryTransactionType::COMMITTED;
    $movement->toInventoryTransactionType = InventoryTransactionType::FULFILLED;
    $movement->quantity = $quantity;

    return $movement;
}

function fulfillmentTestFulfill(int $itemId, InventoryLocation $location, int $quantity, int $lineItemId): void
{
    expect(app(Inventory::class)->executeInventoryMovements(
        (new InventoryMovementCollection())->push(fulfillmentTestMovement($itemId, $location, $quantity, $lineItemId))
    ))->toBeTrue();
}

function fulfillmentTestInsertTransaction(int $itemId, int $locationId, InventoryTransactionType $type, int $quantity, ?int $lineItemId, string $dateCreated): void
{
    DB::table(Table::INVENTORYTRANSACTIONS)->insert([
        'inventoryItemId' => $itemId,
        'inventoryLocationId' => $locationId,
        'movementHash' => app(Inventory::class)->getMovementHash(),
        'quantity' => $quantity,
        'type' => $type->value,
        'lineItemId' => $lineItemId,
        'dateCreated' => $dateCreated,
    ]);
}

function fulfillmentTestPendingTransfer(InventoryLocation $origin, InventoryLocation $destination, int $itemId, int $quantity): Transfer
{
    $transfer = new Transfer();
    $transfer->originLocationId = $origin->id;
    $transfer->destinationLocationId = $destination->id;
    $transfer->setDetails([['inventoryItemId' => $itemId, 'quantity' => $quantity]]);
    Elements::saveElement($transfer, false);

    $transfer = Transfer::find()->id($transfer->id)->status(null)->one();
    expect(app(Transfers::class)->markAsPending($transfer))->toBeTrue();

    return $transfer;
}

function fulfillmentTestReceive(Transfer $transfer, int $itemId, int $quantity): void
{
    $movement = new InventoryTransferMovement();
    $movement->quantity = $quantity;
    $movement->transferId = $transfer->id;
    $movement->inventoryItemId = $itemId;
    $movement->toInventoryLocation = $transfer->getDestinationLocation();
    $movement->fromInventoryLocation = $transfer->getDestinationLocation();
    $movement->toInventoryTransactionType = InventoryTransactionType::AVAILABLE;
    $movement->fromInventoryTransactionType = InventoryTransactionType::INCOMING;

    app(Inventory::class)->executeInventoryMovements((new InventoryMovementCollection())->push($movement));
}

test('a fulfill movement fails when it exceeds the committed and available stock', function() {
    $location = fulfillmentTestLocation('fulfillGuardA');

    // available=10, then 6 committed out of it (available=4, committed=6): fulfillable=10
    fulfillmentTestSetAvailable($this->itemId, $location->id, 10);
    fulfillmentTestCommit($this->itemId, $location, 6);

    expect(fulfillmentTestMovement($this->itemId, $location, 11)->isValid())->toBeFalse()
        ->and(fulfillmentTestMovement($this->itemId, $location, 10)->isValid())->toBeTrue();
});

test('a fulfill movement uses the net committed total even when committed is negative', function() {
    $location = fulfillmentTestLocation('fulfillGuardB');

    fulfillmentTestSetAvailable($this->itemId, $location->id, 10);
    fulfillmentTestAdjust($this->itemId, $location->id, InventoryTransactionType::COMMITTED, -3);

    expect(fulfillmentTestMovement($this->itemId, $location, 8)->isValid())->toBeFalse()
        ->and(fulfillmentTestMovement($this->itemId, $location, 7)->isValid())->toBeTrue();
});

test('a fulfill movement excludes unavailable stock', function() {
    $location = fulfillmentTestLocation('fulfillGuardC');
    $otherLineItem = fulfillmentTestLineItem($this->variant->id);

    // 10 on hand, but 3 are reserved for another order and 2 are damaged
    fulfillmentTestSetAvailable($this->itemId, $location->id, 10);
    fulfillmentTestCommit($this->itemId, $location, 5);
    fulfillmentTestReserve($this->itemId, $location, 3, $otherLineItem->id);
    fulfillmentTestAdjust($this->itemId, $location->id, InventoryTransactionType::AVAILABLE, -2);
    fulfillmentTestAdjust($this->itemId, $location->id, InventoryTransactionType::DAMAGED, 2);

    expect(fulfillmentTestMovement($this->itemId, $location, 6)->isValid())->toBeFalse()
        ->and(fulfillmentTestMovement($this->itemId, $location, 5)->isValid())->toBeTrue();
});

test('a fulfill movement accounts for the other pending quantity of the same item and location', function() {
    $location = fulfillmentTestLocation('fulfillGuardD');

    fulfillmentTestSetAvailable($this->itemId, $location->id, 10);
    fulfillmentTestCommit($this->itemId, $location, 10);

    $movement = fulfillmentTestMovement($this->itemId, $location, 6);
    $movement->otherPendingQuantity = 6;
    expect($movement->isValid())->toBeFalse();

    $movement->otherPendingQuantity = 4;
    expect($movement->isValid())->toBeTrue();
});

test('a fulfill movement with a negative quantity un-fulfills without needing stock', function() {
    $location = fulfillmentTestLocation('fulfillGuardE');

    expect(fulfillmentTestMovement($this->itemId, $location, -2)->isValid())->toBeTrue();
});

test('a fulfillment level\'s max fulfill quantity is limited by the location\'s stock', function() {
    $location = fulfillmentTestLocation('fulfillmentLevelA');
    $lineItem = fulfillmentTestLineItem($this->variant->id);

    fulfillmentTestSetAvailable($this->itemId, $location->id, 10);
    fulfillmentTestCommit($this->itemId, $location, 15, $lineItem->id);

    $level = new InventoryFulfillmentLevel([
        'inventoryItemId' => $this->itemId,
        'inventoryLocationId' => $location->id,
        'lineItemId' => $lineItem->id,
        'committedQuantity' => 15,
        'outstandingCommittedQuantity' => 15,
        'fulfilledQuantity' => 0,
    ]);

    expect($level->getFulfillableQuantity())->toBe(10)
        ->and($level->getMaxFulfillQuantity())->toBe(10)
        ->and($level->getIsShortOfStock())->toBeTrue();

    $level = new InventoryFulfillmentLevel([
        'inventoryItemId' => $this->itemId,
        'inventoryLocationId' => $location->id,
        'lineItemId' => $lineItem->id,
        'committedQuantity' => 15,
        'outstandingCommittedQuantity' => 4,
        'fulfilledQuantity' => 11,
    ]);

    expect($level->getMaxFulfillQuantity())->toBe(4)
        ->and($level->getIsShortOfStock())->toBeFalse();
});

test('getReservedQuantitiesByLineItem excludes ad-hoc reservations and orders oldest first', function() {
    $location = fulfillmentTestLocation('reservedByLineItem');
    $lineItem1 = fulfillmentTestLineItem($this->variant->id);
    $lineItem2 = fulfillmentTestLineItem($this->variant->id);

    fulfillmentTestInsertTransaction($this->itemId, $location->id, InventoryTransactionType::RESERVED, 3, $lineItem1->id, '2024-06-01 00:00:00');
    fulfillmentTestInsertTransaction($this->itemId, $location->id, InventoryTransactionType::RESERVED, 2, $lineItem2->id, '2024-01-01 00:00:00');
    // Ad-hoc manual reservation, not linked to any order
    fulfillmentTestInsertTransaction($this->itemId, $location->id, InventoryTransactionType::RESERVED, 5, null, '2023-01-01 00:00:00');

    $reservations = app(Inventory::class)->getReservedQuantitiesByLineItem($this->itemId, $location->id)->values();

    expect($reservations)->toHaveCount(2)
        ->and($reservations[0]['lineItemId'])->toBe($lineItem2->id)
        ->and($reservations[0]['reservedQty'])->toBe(2)
        ->and($reservations[1]['lineItemId'])->toBe($lineItem1->id)
        ->and($reservations[1]['reservedQty'])->toBe(3);
});

test('getReservedQuantitiesByLineItem with a committed location excludes fulfilled line items', function() {
    $primary = fulfillmentTestLocation('filterPrimary');
    $secondary = fulfillmentTestLocation('filterSecondary');
    $fulfilledLineItem = fulfillmentTestLineItem($this->variant->id);
    $outstandingLineItem = fulfillmentTestLineItem($this->variant->id);

    fulfillmentTestSetAvailable($this->itemId, $primary->id, 10);
    fulfillmentTestCommit($this->itemId, $primary, 5, $fulfilledLineItem->id);
    fulfillmentTestCommit($this->itemId, $primary, 3, $outstandingLineItem->id);
    fulfillmentTestFulfill($this->itemId, $primary, 5, $fulfilledLineItem->id);

    fulfillmentTestInsertTransaction($this->itemId, $secondary->id, InventoryTransactionType::RESERVED, 2, $fulfilledLineItem->id, '2024-01-01 00:00:00');
    fulfillmentTestInsertTransaction($this->itemId, $secondary->id, InventoryTransactionType::RESERVED, 2, $outstandingLineItem->id, '2024-02-01 00:00:00');

    $inventory = app(Inventory::class);
    $filtered = $inventory->getReservedQuantitiesByLineItem($this->itemId, $secondary->id, $primary->id)->values();

    expect($inventory->getReservedQuantitiesByLineItem($this->itemId, $secondary->id))->toHaveCount(2)
        ->and($filtered)->toHaveCount(1)
        ->and($filtered[0]['lineItemId'])->toBe($outstandingLineItem->id)
        ->and($inventory->getReservedQuantitiesByLineItem($this->itemId, $secondary->id, $secondary->id))->toHaveCount(0);
});

test('getOutstandingInventoryFulfillmentLevels includes partially fulfilled line items', function() {
    $location = fulfillmentTestLocation('outstandingA');
    $lineItem = fulfillmentTestLineItem($this->variant->id, qty: 10, completeOrder: true);

    fulfillmentTestSetAvailable($this->itemId, $location->id, 20);
    fulfillmentTestCommit($this->itemId, $location, 10, $lineItem->id);
    fulfillmentTestFulfill($this->itemId, $location, 1, $lineItem->id);

    $levels = app(Inventory::class)->getOutstandingInventoryFulfillmentLevels($this->itemId, $location->id)->values();

    expect($levels)->toHaveCount(1)
        ->and($levels[0]->lineItemId)->toBe($lineItem->id)
        ->and($levels[0]->orderId)->toBe($lineItem->orderId)
        ->and($levels[0]->outstandingCommittedQuantity)->toBe(9)
        ->and($levels[0]->fulfilledQuantity)->toBe(1)
        ->and($levels[0]->getOrder()->id)->toBe($lineItem->orderId)
        ->and(app(Inventory::class)->getUnfulfilledOrders($this->itemId, $location->id))->toHaveCount(1);
});

test('getOutstandingInventoryFulfillmentLevels excludes fulfilled line items and incomplete orders', function() {
    $location = fulfillmentTestLocation('outstandingB');
    $fulfilledLineItem = fulfillmentTestLineItem($this->variant->id, qty: 3, completeOrder: true);
    $cartLineItem = fulfillmentTestLineItem($this->variant->id, qty: 2);

    fulfillmentTestSetAvailable($this->itemId, $location->id, 20);
    fulfillmentTestCommit($this->itemId, $location, 3, $fulfilledLineItem->id);
    fulfillmentTestFulfill($this->itemId, $location, 3, $fulfilledLineItem->id);
    fulfillmentTestCommit($this->itemId, $location, 2, $cartLineItem->id);

    expect(app(Inventory::class)->getOutstandingInventoryFulfillmentLevels($this->itemId, $location->id))->toHaveCount(0);
});

test('getOutstandingInventoryFulfillmentLevels finds the committed location for a reservation', function() {
    $primary = fulfillmentTestLocation('outstandingPrimary');
    $secondary = fulfillmentTestLocation('outstandingSecondary');
    $lineItem = fulfillmentTestLineItem($this->variant->id, qty: 15, completeOrder: true);

    fulfillmentTestSetAvailable($this->itemId, $primary->id, 10);
    fulfillmentTestSetAvailable($this->itemId, $secondary->id, 10);
    fulfillmentTestCommit($this->itemId, $primary, 15, $lineItem->id);
    fulfillmentTestReserve($this->itemId, $secondary, 5, $lineItem->id);

    $inventory = app(Inventory::class);
    $reservations = $inventory->getReservedQuantitiesByLineItem($this->itemId, $secondary->id)->values();
    $levels = $inventory->getOutstandingInventoryFulfillmentLevels($this->itemId, null, [$lineItem->id])->values();

    expect($reservations)->toHaveCount(1)
        ->and($reservations[0]['orderId'])->toBe($lineItem->orderId)
        ->and($levels)->toHaveCount(1)
        ->and($levels[0]->inventoryLocationId)->toBe($primary->id)
        ->and($levels[0]->outstandingCommittedQuantity)->toBe(15);
});

test('a transfer into the committed location resolves the shortfall from the order\'s reservation', function() {
    $primary = fulfillmentTestLocation('issuePrimary');
    $secondary = fulfillmentTestLocation('issueSecondary');
    $lineItem = fulfillmentTestLineItem($this->variant->id);

    // 10 at each location, then an order for 15 is committed at the primary location and the
    // 5 unit shortfall is reserved at the secondary location
    fulfillmentTestSetAvailable($this->itemId, $primary->id, 10);
    fulfillmentTestSetAvailable($this->itemId, $secondary->id, 10);
    fulfillmentTestCommit($this->itemId, $primary, 15, $lineItem->id);
    fulfillmentTestReserve($this->itemId, $secondary, 5, $lineItem->id);

    expect(fulfillmentTestMovement($this->itemId, $primary, 15, $lineItem->id)->isValid())->toBeFalse()
        ->and(fulfillmentTestMovement($this->itemId, $primary, 10, $lineItem->id)->isValid())->toBeTrue();

    $transfer = fulfillmentTestPendingTransfer($secondary, $primary, $this->itemId, 5);

    $secondaryLevel = app(Inventory::class)->getInventoryLevel($this->itemId, $secondary->id);
    expect($secondaryLevel->reservedTotal)->toBe(0)
        ->and($secondaryLevel->availableTotal)->toBe(5);

    fulfillmentTestReceive($transfer, $this->itemId, 5);

    $primaryLevel = app(Inventory::class)->getInventoryLevel($this->itemId, $primary->id);
    expect($primaryLevel->incomingTotal)->toBe(0)
        ->and($primaryLevel->committedTotal)->toBe(15)
        ->and($primaryLevel->availableTotal)->toBe(0)
        ->and($primaryLevel->onHandTotal)->toBe(15)
        ->and(fulfillmentTestMovement($this->itemId, $primary, 15, $lineItem->id)->isValid())->toBeTrue();
});

test('a transfer to another location leaves the order\'s reservation in place', function() {
    $primary = fulfillmentTestLocation('issuePrimary2');
    $secondary = fulfillmentTestLocation('issueSecondary2');
    $other = fulfillmentTestLocation('issueOther2');
    $lineItem = fulfillmentTestLineItem($this->variant->id);

    fulfillmentTestSetAvailable($this->itemId, $primary->id, 10);
    fulfillmentTestSetAvailable($this->itemId, $secondary->id, 10);
    fulfillmentTestCommit($this->itemId, $primary, 15, $lineItem->id);
    fulfillmentTestReserve($this->itemId, $secondary, 5, $lineItem->id);

    fulfillmentTestPendingTransfer($secondary, $other, $this->itemId, 3);

    $secondaryLevel = app(Inventory::class)->getInventoryLevel($this->itemId, $secondary->id);
    expect($secondaryLevel->reservedTotal)->toBe(5)
        ->and($secondaryLevel->availableTotal)->toBe(2);
});

test('a transfer debits the reservation first, then available stock', function() {
    $primary = fulfillmentTestLocation('splitPrimary');
    $secondary = fulfillmentTestLocation('splitSecondary');
    $lineItem = fulfillmentTestLineItem($this->variant->id);

    fulfillmentTestSetAvailable($this->itemId, $primary->id, 10);
    fulfillmentTestSetAvailable($this->itemId, $secondary->id, 10);
    fulfillmentTestCommit($this->itemId, $primary, 15, $lineItem->id);
    fulfillmentTestReserve($this->itemId, $secondary, 5, $lineItem->id);

    $transfer = fulfillmentTestPendingTransfer($secondary, $primary, $this->itemId, 8);

    $secondaryLevel = app(Inventory::class)->getInventoryLevel($this->itemId, $secondary->id);
    expect($secondaryLevel->reservedTotal)->toBe(0)
        ->and($secondaryLevel->availableTotal)->toBe(2);

    $debits = DB::table(Table::INVENTORYTRANSACTIONS)
        ->where('transferId', $transfer->id)
        ->where('inventoryLocationId', $secondary->id)
        ->orderBy('type')
        ->get(['type', 'quantity', 'lineItemId']);

    expect($debits)->toHaveCount(2)
        ->and($debits[0]->type)->toBe(InventoryTransactionType::AVAILABLE->value)
        ->and((int)$debits[0]->quantity)->toBe(-3)
        ->and($debits[0]->lineItemId)->toBeNull()
        ->and($debits[1]->type)->toBe(InventoryTransactionType::RESERVED->value)
        ->and((int)$debits[1]->quantity)->toBe(-5)
        ->and((int)$debits[1]->lineItemId)->toBe($lineItem->id)
        ->and(app(Inventory::class)->getInventoryLevel($this->itemId, $primary->id)->incomingTotal)->toBe(8);

    fulfillmentTestReceive($transfer, $this->itemId, 8);

    $primaryLevel = app(Inventory::class)->getInventoryLevel($this->itemId, $primary->id);
    expect($primaryLevel->incomingTotal)->toBe(0)
        ->and($primaryLevel->committedTotal)->toBe(15)
        ->and($primaryLevel->availableTotal)->toBe(3);
});

test('a transfer consumes reservations oldest first', function() {
    $primary = fulfillmentTestLocation('fifoPrimary');
    $secondary = fulfillmentTestLocation('fifoSecondary');
    $newerLineItem = fulfillmentTestLineItem($this->variant->id);
    $olderLineItem = fulfillmentTestLineItem($this->variant->id);

    fulfillmentTestSetAvailable($this->itemId, $primary->id, 2);
    fulfillmentTestCommit($this->itemId, $primary, 6, $newerLineItem->id);
    fulfillmentTestCommit($this->itemId, $primary, 5, $olderLineItem->id);

    fulfillmentTestSetAvailable($this->itemId, $secondary->id, 10);
    fulfillmentTestInsertTransaction($this->itemId, $secondary->id, InventoryTransactionType::RESERVED, 4, $newerLineItem->id, '2024-06-01 00:00:00');
    fulfillmentTestInsertTransaction($this->itemId, $secondary->id, InventoryTransactionType::RESERVED, 3, $olderLineItem->id, '2024-01-01 00:00:00');

    fulfillmentTestPendingTransfer($secondary, $primary, $this->itemId, 5);

    $remaining = app(Inventory::class)->getReservedQuantitiesByLineItem($this->itemId, $secondary->id)->values();

    expect($remaining)->toHaveCount(1)
        ->and($remaining[0]['lineItemId'])->toBe($newerLineItem->id)
        ->and($remaining[0]['reservedQty'])->toBe(2)
        ->and(app(Inventory::class)->getInventoryLevel($this->itemId, $secondary->id)->availableTotal)->toBe(10);
});
