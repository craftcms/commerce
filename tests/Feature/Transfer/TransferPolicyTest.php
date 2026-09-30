<?php

declare(strict_types=1);

use CraftCms\Cms\User\Elements\User;
use CraftCms\Cms\User\UserPermissions;
use CraftCms\Commerce\Transfer\Elements\Transfer;
use CraftCms\Commerce\Transfer\Enums\TransferStatusType;

/**
 * @param string[] $permissions
 */
function grantTransferPolicyTestPermissions(array $permissions): void
{
    $mock = Mockery::mock(UserPermissions::class)->makePartial();
    $mock->shouldReceive('doesUserHavePermission')
        ->andReturnUsing(fn(int $userId, string $checkPermission): bool => in_array($checkPermission, $permissions, true));
    app()->instance(UserPermissions::class, $mock);
}

function transferPolicyTestUser(bool $admin = false): User
{
    $user = new User();
    $user->id = 1;
    $user->admin = $admin;

    return $user;
}

function transferPolicyTestTransfer(TransferStatusType $status = TransferStatusType::DRAFT): Transfer
{
    $transfer = new Transfer();
    $transfer->id = 100;
    $transfer->setTransferStatus($status);

    return $transfer;
}

test('a user without the manage transfers permission cannot view, save or delete', function() {
    grantTransferPolicyTestPermissions([]);
    $user = transferPolicyTestUser();
    $transfer = transferPolicyTestTransfer();

    expect($transfer->canView($user))->toBeFalse()
        ->and($transfer->canSave($user))->toBeFalse()
        ->and($transfer->canDelete($user))->toBeFalse();
});

test('the manage transfers permission allows viewing, saving and deleting a draft transfer', function() {
    grantTransferPolicyTestPermissions(['commerce-manageInventoryTransfers']);
    $user = transferPolicyTestUser();
    $transfer = transferPolicyTestTransfer();

    expect($transfer->canView($user))->toBeTrue()
        ->and($transfer->canSave($user))->toBeTrue()
        ->and($transfer->canDelete($user))->toBeTrue();
});

test('transfers that are no longer drafts cannot be deleted', function(TransferStatusType $status) {
    grantTransferPolicyTestPermissions(['commerce-manageInventoryTransfers']);

    expect(transferPolicyTestTransfer($status)->canDelete(transferPolicyTestUser()))->toBeFalse()
        ->and(transferPolicyTestTransfer($status)->canDelete(transferPolicyTestUser(admin: true)))->toBeFalse();
})->with([TransferStatusType::PENDING, TransferStatusType::PARTIAL, TransferStatusType::RECEIVED]);

test('transfers can never be duplicated, copied or have drafts created, even by admins', function() {
    grantTransferPolicyTestPermissions([]);
    $admin = transferPolicyTestUser(admin: true);
    $transfer = transferPolicyTestTransfer();

    expect($transfer->canDuplicate($admin))->toBeFalse()
        ->and($transfer->canCopy($admin))->toBeFalse()
        ->and($transfer->canCreateDrafts($admin))->toBeFalse()
        ->and($transfer->canDuplicateAsDraft($admin))->toBeFalse();
});

test('an admin can view, save and delete a draft transfer', function() {
    grantTransferPolicyTestPermissions([]);
    $admin = transferPolicyTestUser(admin: true);
    $transfer = transferPolicyTestTransfer();

    expect($transfer->canView($admin))->toBeTrue()
        ->and($transfer->canSave($admin))->toBeTrue()
        ->and($transfer->canDelete($admin))->toBeTrue();
});
