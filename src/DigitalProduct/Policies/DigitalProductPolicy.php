<?php

declare(strict_types=1);

namespace CraftCms\Commerce\DigitalProduct\Policies;

use CraftCms\Cms\Element\Policies\ElementPolicy;
use CraftCms\Cms\User\Contracts\CraftUser;
use CraftCms\Commerce\DigitalProduct\DigitalProductTypes;
use CraftCms\Commerce\DigitalProduct\Elements\DigitalProduct;

/**
 * Everything is allowed for users with the “Manage {type} digital products” permission.
 */
class DigitalProductPolicy extends ElementPolicy
{
    public function view(CraftUser $user, DigitalProduct $product): bool
    {
        return $this->canManage($user, $product);
    }

    public function save(CraftUser $user, DigitalProduct $product): bool
    {
        return $this->canManage($user, $product);
    }

    public function delete(CraftUser $user, DigitalProduct $product): bool
    {
        return $this->canManage($user, $product);
    }

    public function deleteForSite(CraftUser $user, DigitalProduct $product): bool
    {
        return $this->canManage($user, $product);
    }

    public function duplicate(CraftUser $user, DigitalProduct $product): bool
    {
        return $this->canManage($user, $product);
    }

    public function duplicateAsDraft(CraftUser $user, DigitalProduct $product): bool
    {
        return $this->canManage($user, $product);
    }

    public function copy(CraftUser $user, DigitalProduct $product): bool
    {
        return $this->canManage($user, $product);
    }

    public function createDrafts(CraftUser $user, DigitalProduct $product): bool
    {
        return $this->canManage($user, $product);
    }

    private function canManage(CraftUser $user, DigitalProduct $product): bool
    {
        $type = $product->typeId ? app(DigitalProductTypes::class)->getDigitalProductTypeById($product->typeId) : null;

        return $type !== null && $user->can("commerce-manageDigitalProductType:$type->uid");
    }
}
