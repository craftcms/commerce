<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Plugin\Concerns;

use CraftCms\Cms\User\Data\Permission;
use CraftCms\Commerce\Catalog\Elements\Product;
use CraftCms\Commerce\Catalog\ProductType\ProductTypes;
use Illuminate\Support\Collection;
use Override;

use function CraftCms\Cms\t;

trait HasPermissions
{
    #[Override]
    protected function getPermissions(): array
    {
        return [
            ...$this->productTypePermissions(),
            new Permission(
                key: 'commerce-manageOrders',
                label: t('Manage orders', category: 'commerce'),
                nested: new Collection([
                    new Permission(key: 'commerce-editOrders', label: t('Edit orders', category: 'commerce')),
                    new Permission(key: 'commerce-deleteOrders', label: t('Delete orders', category: 'commerce')),
                    new Permission(key: 'commerce-capturePayment', label: t('Capture payment', category: 'commerce')),
                    new Permission(key: 'commerce-refundPayment', label: t('Refund payment', category: 'commerce')),
                ]),
            ),
            new Permission(
                key: 'commerce-manageInventoryStockLevels',
                label: t('Manage inventory stock levels', category: 'commerce'),
            ),
            new Permission(
                key: 'commerce-manageInventoryLocations',
                label: t('Manage inventory locations', category: 'commerce'),
            ),
            new Permission(
                key: 'commerce-manageInventoryTransfers',
                label: t('Manage inventory transfers', category: 'commerce'),
            ),
            new Permission(
                key: 'commerce-manageStoreSettings',
                label: t('Manage store settings', category: 'commerce'),
                nested: new Collection([
                    new Permission(key: 'commerce-manageGeneralStoreSettings', label: t('Manage general store settings', category: 'commerce')),
                    new Permission(key: 'commerce-managePaymentCurrencies', label: t('Manage payment currencies', category: 'commerce')),
                    new Permission(key: 'commerce-manageShipping', label: t('Manage shipping', category: 'commerce')),
                    new Permission(key: 'commerce-manageTaxes', label: t('Manage taxes', category: 'commerce')),
                    new Permission(
                        key: 'commerce-managePromotions',
                        label: t('Manage promotions', category: 'commerce'),
                        nested: new Collection([
                            new Permission(key: 'commerce-editSales', label: t('Edit sales', category: 'commerce')),
                            new Permission(key: 'commerce-createSales', label: t('Create sales', category: 'commerce')),
                            new Permission(key: 'commerce-deleteSales', label: t('Delete sales', category: 'commerce')),
                            new Permission(key: 'commerce-editCatalogPricingRules', label: t('Edit catalog pricing rules', category: 'commerce')),
                            new Permission(key: 'commerce-createCatalogPricingRules', label: t('Create catalog pricing rules', category: 'commerce')),
                            new Permission(key: 'commerce-deleteCatalogPricingRules', label: t('Delete catalog pricing rules', category: 'commerce')),
                            new Permission(key: 'commerce-editDiscounts', label: t('Edit discounts', category: 'commerce')),
                            new Permission(key: 'commerce-createDiscounts', label: t('Create discounts', category: 'commerce')),
                            new Permission(key: 'commerce-deleteDiscounts', label: t('Delete discounts', category: 'commerce')),
                        ]),
                    ),
                ]),
            ),
            new Permission(
                key: 'commerce-manageDonationSettings',
                label: t('Manage donation settings', category: 'commerce'),
            ),
        ];
    }

    /**
     * @return Permission[]
     */
    private function productTypePermissions(): array
    {
        $productTypes = app(ProductTypes::class)->getAllProductTypes();

        if (empty($productTypes)) {
            return [];
        }

        $pluralType = Product::pluralLowerDisplayName();

        return array_map(fn($productType) => new Permission(
            key: "commerce-viewProductType:$productType->uid",
            label: t('View {type}', ['type' => $pluralType], category: 'app'),
            info: t('Allows viewing existing {type} and creating drafts for them.', ['type' => $pluralType], category: 'app'),
            nested: new Collection([
                new Permission(
                    key: "commerce-createProductType:$productType->uid",
                    label: ucfirst(t('Create {type}', ['type' => $pluralType], category: 'app')),
                    info: t('Allows creating drafts of new {type}.', ['type' => $pluralType], category: 'app'),
                ),
                new Permission(
                    key: "commerce-saveProductType:$productType->uid",
                    label: ucfirst(t('Save {type}', ['type' => $pluralType], category: 'app')),
                    info: t('Allows fully saving canonical {type} (directly or by applying drafts).', ['type' => $pluralType], category: 'app'),
                ),
                new Permission(
                    key: "commerce-deleteProductType:$productType->uid",
                    label: ucfirst(t('Delete {type}', ['type' => $pluralType], category: 'app')),
                    info: t('Allows deleting {type} for all sites.', ['type' => $pluralType], category: 'app'),
                ),
            ]),
        ), $productTypes);
    }
}
