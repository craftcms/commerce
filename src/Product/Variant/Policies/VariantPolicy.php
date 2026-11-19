<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Product\Variant\Policies;

use CraftCms\Cms\Element\ElementHelper;
use CraftCms\Cms\Element\Policies\ElementPolicy;
use CraftCms\Cms\User\Contracts\CraftUser;
use CraftCms\Commerce\Product\Elements\Product;
use CraftCms\Commerce\Product\Variant\Elements\Variant;

class VariantPolicy extends ElementPolicy
{
    public function view(CraftUser $user, Variant $variant): bool
    {
        if (!$product = $variant->getOwner()) {
            return false;
        }

        return $user->can('view', $product);
    }

    public function save(CraftUser $user, Variant $variant): bool
    {
        if (!$product = $variant->getOwner()) {
            return false;
        }

        return $user->can('save', $product);
    }

    public function delete(CraftUser $user, Variant $variant): bool
    {
        // Enabled products must have at least one variant, and deleting a variant doesn't validate its product
        $product = $variant->getOwner();
        if (
            $product instanceof Product &&
            $product->enabled &&
            !$variant->trashed &&
            !ElementHelper::isDraftOrRevision($variant)
        ) {
            // Count the variants fresh, as the product's variants may be memoized from before others were deleted
            $variantCount = $product->id
                ? Variant::find()->ownerId($product->id)->siteId($product->siteId)->status(null)->count()
                : $product->getVariants(true)->count();

            if ($variantCount <= 1) {
                return false;
            }
        }

        return $this->save($user, $variant);
    }

    public function duplicate(CraftUser $user, Variant $variant): bool
    {
        $product = $variant->getOwner();
        if (!$product || !$this->save($user, $variant)) {
            return false;
        }

        $maxVariants = $product->getType()->maxVariants;

        return !$maxVariants || $product->getVariants(true)->count() < $maxVariants;
    }

    public function duplicateAsDraft(CraftUser $user, Variant $variant): bool
    {
        return $this->duplicate($user, $variant);
    }

    public function createDrafts(CraftUser $user, Variant $variant): bool
    {
        return true;
    }

    public function deleteForSite(CraftUser $user, Variant $variant): bool
    {
        return false;
    }

    public function copy(CraftUser $user, Variant $variant): bool
    {
        // TODO: Review whether copying a variant should require authorization against its owner.
        return true;
    }
}
