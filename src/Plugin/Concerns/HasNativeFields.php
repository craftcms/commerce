<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Plugin\Concerns;

use Closure;
use CraftCms\Cms\Address\Elements\Address;
use CraftCms\Cms\FieldLayout\FieldLayout;
use CraftCms\Commerce\Customer\FieldLayoutElements\UserAddressSettings;
use CraftCms\Commerce\Product\Elements\Product;
use CraftCms\Commerce\Product\FieldLayoutElements\ProductTitleField;
use CraftCms\Commerce\Product\Variant\Elements\Variant;
use CraftCms\Commerce\Product\Variant\FieldLayoutElements\VariantsField as VariantsLayoutElement;
use CraftCms\Commerce\Product\Variant\FieldLayoutElements\VariantTitleField;
use CraftCms\Commerce\Purchasable\FieldLayoutElements\PurchasableAllowedQtyField;
use CraftCms\Commerce\Purchasable\FieldLayoutElements\PurchasableAvailableForPurchaseField;
use CraftCms\Commerce\Purchasable\FieldLayoutElements\PurchasableDimensionsField;
use CraftCms\Commerce\Purchasable\FieldLayoutElements\PurchasableFreeShippingField;
use CraftCms\Commerce\Purchasable\FieldLayoutElements\PurchasablePriceField;
use CraftCms\Commerce\Purchasable\FieldLayoutElements\PurchasablePromotableField;
use CraftCms\Commerce\Purchasable\FieldLayoutElements\PurchasableSkuField;
use CraftCms\Commerce\Purchasable\FieldLayoutElements\PurchasableStockField;
use CraftCms\Commerce\Purchasable\FieldLayoutElements\PurchasableWeightField;
use CraftCms\Commerce\Transfer\Elements\Transfer;
use CraftCms\Commerce\Transfer\FieldLayoutElements\TransferManagementField;
use Override;

trait HasNativeFields
{
    #[Override]
    protected function getNativeFields(): ?Closure
    {
        return function(FieldLayout $fieldLayout, array $fields): array {
            switch ($fieldLayout->type) {
                case Address::class:
                    $fields[] = UserAddressSettings::class;
                    break;
                case Product::class:
                    $fields[] = ProductTitleField::class;
                    $fields[] = VariantsLayoutElement::class;
                    break;
                case Transfer::class:
                    $fields[] = TransferManagementField::class;
                    break;
                case Variant::class:
                    $fields[] = VariantTitleField::class;
                    $fields[] = PurchasableSkuField::class;
                    $fields[] = PurchasablePriceField::class;
                    $fields[] = PurchasableStockField::class;
                    $fields[] = PurchasableAvailableForPurchaseField::class;
                    $fields[] = PurchasableAllowedQtyField::class;
                    $fields[] = PurchasableFreeShippingField::class;
                    $fields[] = PurchasablePromotableField::class;
                    $fields[] = PurchasableDimensionsField::class;
                    $fields[] = PurchasableWeightField::class;
                    break;
            }

            return $fields;
        };
    }
}
