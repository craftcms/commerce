<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Transfer\Policies;

use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\Element\Policies\ElementPolicy;
use CraftCms\Cms\User\Contracts\CraftUser;
use CraftCms\Commerce\Transfer\Elements\Transfer;
use CraftCms\Commerce\Transfer\Enums\TransferStatusType;
use Override;

class TransferPolicy extends ElementPolicy
{
    public function view(CraftUser $user, Transfer $transfer): bool
    {
        return $user->can('commerce-manageInventoryTransfers');
    }

    public function save(CraftUser $user, Transfer $transfer): bool
    {
        return $user->can('commerce-manageInventoryTransfers');
    }

    public function delete(CraftUser $user, Transfer $transfer): bool
    {
        return $transfer->getTransferStatus() === TransferStatusType::DRAFT
            && $user->can('commerce-manageInventoryTransfers');
    }

    public function duplicate(CraftUser $user, Transfer $transfer): bool
    {
        return false;
    }

    public function createDrafts(CraftUser $user, Transfer $transfer): bool
    {
        return false;
    }

    #[Override]
    protected function shouldCheckSiteAuthorization(ElementInterface $element): bool
    {
        return false;
    }
}
