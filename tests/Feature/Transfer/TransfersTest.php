<?php

declare(strict_types=1);

use CraftCms\Cms\Address\Elements\Address;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Inventory\Data\InventoryLocation;
use CraftCms\Commerce\Inventory\Inventory;
use CraftCms\Commerce\Inventory\InventoryLocations;
use CraftCms\Commerce\Product\Variant\Elements\Variant;
use CraftCms\Commerce\Tests\Support\ProductConditionsFixture;
use CraftCms\Commerce\Transfer\Elements\Transfer;
use CraftCms\Commerce\Transfer\Enums\TransferStatusType;
use CraftCms\Commerce\Transfer\Transfers;
use Illuminate\Support\Facades\DB;

function createTransferInventoryLocation(string $handle): InventoryLocation
{
    $address = new Address();
    $address->countryCode = 'US';
    $address->title = $handle;
    Elements::saveElement($address);

    $inventoryLocation = new InventoryLocation();
    $inventoryLocation->name = ucfirst($handle);
    $inventoryLocation->handle = $handle;
    $inventoryLocation->setAddress($address);

    if (!app(InventoryLocations::class)->saveInventoryLocation($inventoryLocation)) {
        throw new RuntimeException('Could not save inventory location: ' . json_encode($inventoryLocation->errors()->all()));
    }

    return $inventoryLocation;
}

/**
 * @param array<int, array{variant: Variant, quantity: int}> $lines
 */
function createDraftTransfer(InventoryLocation $origin, InventoryLocation $destination, array $lines): Transfer
{
    $transfer = new Transfer();
    $transfer->originLocationId = $origin->id;
    $transfer->destinationLocationId = $destination->id;
    $transfer->setDetails(array_map(fn(array $line) => [
        'inventoryItemId' => $line['variant']->inventoryItemId,
        'quantity' => $line['quantity'],
    ], $lines));

    if (!Elements::saveElement($transfer)) {
        throw new RuntimeException('Could not save transfer: ' . json_encode($transfer->errors()->all()));
    }

    return Transfer::find()->id($transfer->id)->one();
}

beforeEach(function() {
    $fixture = ProductConditionsFixture::seed();
    $this->hoodieVariant = $fixture->hoodieVariant;
    $this->tShirtVariant = $fixture->tShirtVariant;
    $this->origin = app(InventoryLocations::class)->getAllInventoryLocations()->first();
    $this->destination = createTransferInventoryLocation('warehouse');

    foreach ([$this->hoodieVariant, $this->tShirtVariant] as $variant) {
        app(Inventory::class)->updatePurchasableInventoryLevel($variant, 10);
    }
});

test('each saved transfer detail gets its own uid', function() {
    $transfer = createDraftTransfer($this->origin, $this->destination, [
        ['variant' => $this->hoodieVariant, 'quantity' => 2],
        ['variant' => $this->tShirtVariant, 'quantity' => 3],
    ]);

    $uids = DB::table(Table::TRANSFERDETAILS)->where('transferId', $transfer->id)->pluck('uid')->all();

    expect($uids)->toHaveCount(2)
        ->and(array_unique($uids))->toHaveCount(2)
        ->and($uids)->not->toContain('0');
});

test('marking a transfer as pending moves stock to incoming and links the transactions to the transfer', function() {
    $transfer = createDraftTransfer($this->origin, $this->destination, [
        ['variant' => $this->hoodieVariant, 'quantity' => 4],
    ]);

    expect(app(Transfers::class)->markAsPending($transfer))->toBeTrue();

    $transactions = DB::table(Table::INVENTORYTRANSACTIONS)
        ->where('inventoryItemId', $this->hoodieVariant->inventoryItemId)
        ->where('transferId', $transfer->id)
        ->get(['inventoryLocationId', 'type', 'quantity']);

    expect(Transfer::find()->id($transfer->id)->one()->getTransferStatus())->toBe(TransferStatusType::PENDING)
        ->and($transactions)->toHaveCount(2)
        ->and($transactions->firstWhere('inventoryLocationId', $this->destination->id))
        ->toMatchArray(['type' => 'incoming', 'quantity' => 4])
        ->and($transactions->firstWhere('inventoryLocationId', $this->origin->id))
        ->toMatchArray(['type' => 'available', 'quantity' => -4]);
});

test('receiving a transfer applies each detail\'s own accepted and rejected quantities', function() {
    $transfer = createDraftTransfer($this->origin, $this->destination, [
        ['variant' => $this->hoodieVariant, 'quantity' => 4],
        ['variant' => $this->tShirtVariant, 'quantity' => 3],
    ]);
    app(Transfers::class)->markAsPending($transfer);

    $details = collect($transfer->getDetails())->keyBy('inventoryItemId');
    $hoodieDetail = $details[$this->hoodieVariant->inventoryItemId];
    $tShirtDetail = $details[$this->tShirtVariant->inventoryItemId];

    app(Transfers::class)->receive($transfer, [
        $hoodieDetail->uid => ['accept' => 3, 'reject' => 1],
        $tShirtDetail->uid => ['accept' => 1],
    ]);

    $savedTransfer = Transfer::find()->id($transfer->id)->one();
    $savedDetails = collect($savedTransfer->getDetails())->keyBy('id');

    expect($savedTransfer->getTransferStatus())->toBe(TransferStatusType::PARTIAL)
        ->and($savedDetails[$hoodieDetail->id])
        ->quantityAccepted->toBe(3)
        ->quantityRejected->toBe(1)
        ->and($savedDetails[$tShirtDetail->id])
        ->quantityAccepted->toBe(1)
        ->quantityRejected->toBe(0)
        ->and(DB::table(Table::INVENTORYTRANSACTIONS)
            ->where('transferId', $transfer->id)
            ->where('inventoryLocationId', $this->destination->id)
            ->where('type', 'incoming')
            ->sum('quantity'))->toEqual(7 - 3 - 1 - 1);
});

test('receiving everything marks the transfer as received', function() {
    $transfer = createDraftTransfer($this->origin, $this->destination, [
        ['variant' => $this->hoodieVariant, 'quantity' => 2],
    ]);
    app(Transfers::class)->markAsPending($transfer);
    $detail = $transfer->getDetails()[0];

    app(Transfers::class)->receive($transfer, [$detail->uid => ['accept' => 2]]);

    expect(Transfer::find()->id($transfer->id)->one()->getTransferStatus())->toBe(TransferStatusType::RECEIVED);
});

test('sort options do not include table attributes that have no column', function() {
    expect(Transfer::sortOptions())->not->toHaveKeys(['originLocation', 'destinationLocation', 'received']);
});
