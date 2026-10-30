<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Product\Policies;

use CraftCms\Cms\Element\Policies\ElementPolicy;
use CraftCms\Cms\User\Contracts\CraftUser;
use CraftCms\Commerce\Product\Elements\Product;
use CraftCms\Commerce\Product\ProductType\Data\ProductType;
use CraftCms\Commerce\Product\ProductType\ProductTypes;

class ProductPolicy extends ElementPolicy
{
    public function view(CraftUser $user, Product $product): bool
    {
        if (!$productType = $this->productType($product)) {
            return false;
        }

        return $user->can("commerce-viewProductType:$productType->uid");
    }

    public function save(CraftUser $user, Product $product): bool
    {
        if (!$productType = $this->productType($product)) {
            return false;
        }

        if ($product->getIsDraft()) {
            return $this->createDrafts($user, $product);
        }

        if (!$product->id) {
            return $user->can("commerce-createProductType:$productType->uid");
        }

        return $user->can("commerce-saveProductType:$productType->uid");
    }

    public function delete(CraftUser $user, Product $product): bool
    {
        if (!$productType = $this->productType($product)) {
            return false;
        }

        return $user->can("commerce-deleteProductType:$productType->uid");
    }

    public function deleteForSite(CraftUser $user, Product $product): bool
    {
        return $this->delete($user, $product);
    }

    public function duplicate(CraftUser $user, Product $product): bool
    {
        if (!$productType = $this->productType($product)) {
            return false;
        }

        return $user->can("commerce-createProductType:$productType->uid")
            && $user->can("commerce-saveProductType:$productType->uid");
    }

    public function duplicateAsDraft(CraftUser $user, Product $product): bool
    {
        return $this->duplicate($user, $product);
    }

    public function copy(CraftUser $user, Product $product): bool
    {
        return $this->view($user, $product);
    }

    /**
     * Anyone who can view a product can create drafts of it.
     */
    public function createDrafts(CraftUser $user, Product $product): bool
    {
        return true;
    }

    private function productType(Product $product): ?ProductType
    {
        if ($product->typeId === null) {
            return null;
        }

        return app(ProductTypes::class)->getProductTypeById($product->typeId);
    }
}
