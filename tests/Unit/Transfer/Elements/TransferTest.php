<?php

declare(strict_types=1);

use CraftCms\Commerce\Transfer\Elements\Transfer;
use CraftCms\Commerce\Transfer\Enums\TransferStatusType;

function transferWithDetails(array $details): Transfer
{
    $transfer = new Transfer();
    $transfer->setDetails($details);

    return $transfer;
}

test('sumDetailsQuanity sums quantity across all details', function() {
    $transfer = transferWithDetails([
        ['inventoryItemId' => 1, 'quantity' => 3],
        ['inventoryItemId' => 2, 'quantity' => 5],
    ]);

    expect($transfer->sumDetailsQuanity())->toBe(8);
});

test('getTotalAccepted, getTotalRejected and getTotalReceived sum across details', function() {
    $transfer = transferWithDetails([
        ['inventoryItemId' => 1, 'quantity' => 5, 'quantityAccepted' => 2, 'quantityRejected' => 1],
        ['inventoryItemId' => 2, 'quantity' => 4, 'quantityAccepted' => 3, 'quantityRejected' => 0],
    ]);

    expect($transfer->getTotalAccepted())->toBe(5);
    expect($transfer->getTotalRejected())->toBe(1);
    expect($transfer->getTotalReceived())->toBe(6);
});

test('isAllReceived is true only when every detail is fully received', function() {
    $notAllReceived = transferWithDetails([
        ['inventoryItemId' => 1, 'quantity' => 5, 'quantityAccepted' => 2, 'quantityRejected' => 0],
    ]);
    expect($notAllReceived->isAllReceived())->toBeFalse();

    $allReceived = transferWithDetails([
        ['inventoryItemId' => 1, 'quantity' => 5, 'quantityAccepted' => 3, 'quantityRejected' => 2],
    ]);
    expect($allReceived->isAllReceived())->toBeTrue();
});

test('updateTransferStatus does nothing while still a draft', function() {
    $transfer = transferWithDetails([
        ['inventoryItemId' => 1, 'quantity' => 5, 'quantityAccepted' => 5],
    ]);
    $transfer->setTransferStatus(TransferStatusType::DRAFT);

    $transfer->updateTransferStatus();

    expect($transfer->getTransferStatus())->toBe(TransferStatusType::DRAFT);
});

test('updateTransferStatus moves to received once everything has been received', function() {
    $transfer = transferWithDetails([
        ['inventoryItemId' => 1, 'quantity' => 5, 'quantityAccepted' => 5],
    ]);
    $transfer->setTransferStatus(TransferStatusType::PENDING);

    $transfer->updateTransferStatus();

    expect($transfer->getTransferStatus())->toBe(TransferStatusType::RECEIVED);
});

test('updateTransferStatus moves to partial once some but not all has been received', function() {
    $transfer = transferWithDetails([
        ['inventoryItemId' => 1, 'quantity' => 5, 'quantityAccepted' => 2],
    ]);
    $transfer->setTransferStatus(TransferStatusType::PENDING);

    $transfer->updateTransferStatus();

    expect($transfer->getTransferStatus())->toBe(TransferStatusType::PARTIAL);
});

test('updateTransferStatus stays pending when nothing has been received yet', function() {
    $transfer = transferWithDetails([
        ['inventoryItemId' => 1, 'quantity' => 5],
    ]);
    $transfer->setTransferStatus(TransferStatusType::DRAFT);

    // simulate the draft -> pending move made by afterSave() before updateTransferStatus() runs
    $transfer->setTransferStatus(TransferStatusType::PENDING);
    $transfer->updateTransferStatus();

    expect($transfer->getTransferStatus())->toBe(TransferStatusType::PENDING);
});

test('validateLocations adds an error when origin and destination match', function() {
    $transfer = new Transfer();
    $transfer->originLocationId = 1;
    $transfer->destinationLocationId = 1;

    $transfer->validateLocations();

    expect($transfer->errors()->has('originLocationId'))->toBeTrue();
});

test('validateLocations adds no error when origin and destination differ', function() {
    $transfer = new Transfer();
    $transfer->originLocationId = 1;
    $transfer->destinationLocationId = 2;

    $transfer->validateLocations();

    expect($transfer->errors()->has('originLocationId'))->toBeFalse();
});

test('isTransferDraft, isTransferPending, isTransferPartial and isTransferReceived reflect the status', function() {
    $transfer = new Transfer();

    $transfer->setTransferStatus(TransferStatusType::DRAFT);
    expect($transfer->isTransferDraft())->toBeTrue();
    expect($transfer->isTransferPending())->toBeFalse();

    $transfer->setTransferStatus(TransferStatusType::PENDING);
    expect($transfer->isTransferPending())->toBeTrue();
    expect($transfer->isTransferDraft())->toBeFalse();

    $transfer->setTransferStatus(TransferStatusType::PARTIAL);
    expect($transfer->isTransferPartial())->toBeTrue();

    $transfer->setTransferStatus(TransferStatusType::RECEIVED);
    expect($transfer->isTransferReceived())->toBeTrue();
});
