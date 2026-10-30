<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Management;

use CraftCms\Commerce\CatalogPricing;
use CraftCms\Commerce\Email;
use CraftCms\Commerce\Inventory;
use CraftCms\Commerce\Order;
use CraftCms\Commerce\Payment;
use CraftCms\Commerce\Pdf;
use CraftCms\Commerce\Product\ProductType;
use CraftCms\Commerce\Promotion;
use CraftCms\Commerce\Shipping;
use CraftCms\Commerce\Store;
use CraftCms\Commerce\Tax;

enum ResourceType : string
{
    case Stores = 'stores';
    case ProductTypes = 'productTypes';
    case OrderStatuses = 'orderStatuses';
    case LineItemStatuses = 'lineItemStatuses';
    case Emails = 'emails';
    case Pdfs = 'pdfs';
    case Gateways = 'gateways';
    case InventoryLocations = 'inventoryLocations';
    case PaymentCurrencies = 'paymentCurrencies';
    case ShippingCategories = 'shippingCategories';
    case ShippingMethods = 'shippingMethods';
    case ShippingRules = 'shippingRules';
    case ShippingZones = 'shippingZones';
    case TaxCategories = 'taxCategories';
    case TaxRates = 'taxRates';
    case TaxZones = 'taxZones';
    case Discounts = 'discounts';
    case Sales = 'sales';
    case CatalogPricingRules = 'catalogPricingRules';

    public function configuration(): bool
    {
        return in_array($this, [self::Stores, self::ProductTypes, self::OrderStatuses, self::LineItemStatuses, self::Emails, self::Pdfs, self::Gateways], true);
    }

    public function storeScoped(): bool
    {
        return in_array($this, [self::OrderStatuses, self::LineItemStatuses, self::Emails, self::Pdfs, self::PaymentCurrencies, self::ShippingCategories, self::ShippingMethods, self::ShippingZones, self::TaxRates, self::TaxZones, self::Discounts, self::CatalogPricingRules], true);
    }

    /** @return array{class-string, class-string, string, ?string, ?string} */
    public function binding(): array
    {
        return match ($this) {
            self::Stores => [Store\Data\Store::class, Store\Stores::class, 'getAllStores', 'saveStore', 'deleteStoreById'],
            self::ProductTypes => [ProductType\Data\ProductType::class, ProductType\ProductTypes::class, 'getAllProductTypes', 'saveProductType', 'deleteProductTypeById'],
            self::OrderStatuses => [Order\Data\OrderStatus::class, Order\OrderStatuses::class, 'getAllOrderStatuses', 'saveOrderStatus', 'deleteOrderStatusById'],
            self::LineItemStatuses => [Order\Data\LineItemStatus::class, Order\LineItemStatuses::class, 'getAllLineItemStatuses', 'saveLineItemStatus', 'archiveLineItemStatusById'],
            self::Emails => [Email\Data\Email::class, Email\Emails::class, 'getAllEmails', 'saveEmail', 'deleteEmailById'],
            self::Pdfs => [Pdf\Data\Pdf::class, Pdf\Pdfs::class, 'getAllPdfs', 'savePdf', 'deletePdfById'],
            self::Gateways => [Payment\Gateway\Gateway::class, Payment\Gateway\Gateways::class, 'getAllGateways', null, 'archiveGatewayById'],
            self::InventoryLocations => [Inventory\Data\InventoryLocation::class, Inventory\InventoryLocations::class, 'getAllInventoryLocations', 'saveInventoryLocation', null],
            self::PaymentCurrencies => [Payment\Data\PaymentCurrency::class, Payment\PaymentCurrencies::class, 'getAllPaymentCurrencies', 'savePaymentCurrency', 'deletePaymentCurrencyById'],
            self::ShippingCategories => [Shipping\Data\ShippingCategory::class, Shipping\ShippingCategories::class, 'getAllShippingCategories', 'saveShippingCategory', 'deleteShippingCategoryById'],
            self::ShippingMethods => [Shipping\Data\ShippingMethod::class, Shipping\ShippingMethods::class, 'getAllShippingMethods', 'saveShippingMethod', 'deleteShippingMethodById'],
            self::ShippingRules => [Shipping\Data\ShippingRule::class, Shipping\ShippingRules::class, 'getAllShippingRules', 'saveShippingRule', 'deleteShippingRuleById'],
            self::ShippingZones => [Shipping\Data\ShippingAddressZone::class, Shipping\ShippingZones::class, 'getAllShippingZones', 'saveShippingZone', 'deleteShippingZoneById'],
            self::TaxCategories => [Tax\Data\TaxCategory::class, Tax\TaxCategories::class, 'getAllTaxCategories', 'saveTaxCategory', 'deleteTaxCategoryById'],
            self::TaxRates => [Tax\Data\TaxRate::class, Tax\TaxRates::class, 'getAllTaxRates', 'saveTaxRate', 'deleteTaxRateById'],
            self::TaxZones => [Tax\Data\TaxAddressZone::class, Tax\TaxZones::class, 'getAllTaxZones', 'saveTaxZone', 'deleteTaxZoneById'],
            self::Discounts => [Promotion\Data\Discount::class, Promotion\Discounts::class, 'getAllDiscounts', 'saveDiscount', 'deleteDiscountById'],
            self::Sales => [Promotion\Data\Sale::class, Promotion\Sales::class, 'getAllSales', 'saveSale', 'deleteSaleById'],
            self::CatalogPricingRules => [CatalogPricing\Data\CatalogPricingRule::class, CatalogPricing\CatalogPricingRules::class, 'getAllCatalogPricingRules', 'saveCatalogPricingRule', 'deleteCatalogPricingRuleById'],
        };
    }

    /** @return list<string> */
    public function permissions(string $operation): array
    {
        $permission = match ($this) {
            self::InventoryLocations => 'commerce-manageInventoryLocations',
            self::PaymentCurrencies => 'commerce-managePaymentCurrencies',
            self::ShippingCategories, self::ShippingMethods, self::ShippingRules, self::ShippingZones => 'commerce-manageShipping',
            self::TaxCategories, self::TaxRates, self::TaxZones => 'commerce-manageTaxes',
            self::Discounts, self::Sales, self::CatalogPricingRules => 'commerce-managePromotions',
            default => null,
        };

        if ($permission === null) {
            return [];
        }

        $permissions = $this === self::InventoryLocations ? [$permission] : ['commerce-manageStoreSettings', $permission];

        if (in_array($this, [self::Discounts, self::Sales, self::CatalogPricingRules], true) && in_array($operation, ['create', 'update', 'delete'], true)) {
            $verb = $operation === 'update' ? 'edit' : $operation;
            $permissions[] = 'commerce-' . $verb . ucfirst($this->value);
        }

        return $permissions;
    }

    /** @return array<string, array<string, mixed>> */
    public function attributes(): array
    {
        $string = ['type' => 'string'];
        $nullableString = ['type' => ['string', 'null']];
        $bool = ['type' => 'boolean'];
        $int = ['type' => 'integer'];
        $id = ['type' => 'integer', 'minimum' => 1];
        $number = ['type' => 'number'];
        $date = ['type' => ['string', 'null'], 'format' => 'date-time'];
        $ids = ['type' => 'array', 'items' => $id, 'uniqueItems' => true];
        $condition = ['type' => 'object', 'description' => 'Commerce condition-builder configuration. Read the existing record before changing it.'];
        $named = ['name' => $string, 'handle' => $string];
        $described = ['name' => $string, 'description' => $nullableString];
        $category = [...$named, 'description' => $nullableString, 'default' => $bool, 'icon' => $nullableString, 'color' => $nullableString];
        $promotion = [...$described, 'enabled' => $bool, 'dateFrom' => $date, 'dateTo' => $date];

        $properties = match ($this) {
            self::Stores => [...$named, 'primary' => $bool, 'sortOrder' => $int, 'currency' => ['type' => 'string', 'pattern' => '^[A-Z]{3}$'], 'autoSetNewCartAddresses' => $bool, 'autoSetCartShippingMethodOption' => $bool, 'autoSetPaymentSource' => $bool, 'allowEmptyCartOnCheckout' => $bool, 'allowCheckoutWithoutPayment' => $bool, 'allowPartialPaymentOnCheckout' => $bool, 'requireShippingAddressAtCheckout' => $bool, 'requireBillingAddressAtCheckout' => $bool, 'requireShippingMethodSelectionAtCheckout' => $bool, 'useBillingAddressForTax' => $bool, 'validateOrganizationTaxIdAsVatId' => $bool, 'orderReferenceFormat' => $string, 'freeOrderPaymentStrategy' => ['enum' => ['complete', 'process']], 'minimumTotalPriceStrategy' => ['enum' => ['default', 'zero', 'shipping']]],
            self::ProductTypes => [...$named, 'enableVersioning' => $bool, 'hasDimensions' => $bool, 'maxVariants' => ['type' => ['integer', 'null'], 'minimum' => 1], 'hasVariantTitleField' => $bool, 'variantTitleFormat' => $string, 'variantUiLabelFormat' => $string, 'hasProductTitleField' => $bool, 'productTitleFormat' => $string, 'productUiLabelFormat' => $string, 'showSlugField' => $bool, 'skuFormat' => $nullableString, 'descriptionFormat' => $string, 'isStructure' => $bool, 'maxLevels' => ['type' => ['integer', 'null'], 'minimum' => 1], 'siteSettings' => ['type' => 'array', 'minItems' => 1, 'items' => ['type' => 'object', 'properties' => ['siteId' => $id, 'hasUrls' => $bool, 'uriFormat' => $nullableString, 'template' => $nullableString, 'enabledByDefault' => $bool], 'required' => ['siteId'], 'additionalProperties' => false]], 'shippingCategories' => $ids, 'taxCategories' => $ids, 'productFieldLayout' => ['type' => 'object'], 'variantFieldLayout' => ['type' => 'object']],
            self::OrderStatuses, self::LineItemStatuses => [...$named, 'color' => $string, 'default' => $bool, 'sortOrder' => $int, ...($this === self::OrderStatuses ? ['description' => $nullableString, 'emailIds' => $ids] : [])],
            self::Emails => ['name' => $string, 'subject' => $string, 'recipientType' => ['enum' => ['customer', 'custom']], 'replyTo' => $nullableString, 'enabled' => $bool, 'templatePath' => $nullableString, 'plainTextTemplatePath' => $nullableString, 'pdfId' => ['type' => ['integer', 'null']], 'language' => $string, 'renderSiteId' => ['type' => ['integer', 'null']], 'senderName' => $nullableString, 'senderAddress' => $nullableString, 'to' => $nullableString, 'cc' => $nullableString, 'bcc' => $nullableString],
            self::Pdfs => [...$named, 'description' => $nullableString, 'enabled' => $bool, 'isDefault' => $bool, 'templatePath' => $string, 'fileNameFormat' => $nullableString, 'paperOrientation' => ['enum' => ['portrait', 'landscape']], 'paperSize' => $string, 'language' => $string, 'linkExpiry' => ['type' => 'integer', 'minimum' => 1]],
            self::Gateways => [...$named, 'paymentType' => $string, 'isFrontendEnabled' => $bool, 'isArchived' => $bool],
            self::InventoryLocations => $named,
            self::PaymentCurrencies => ['iso' => $string, 'rate' => ['type' => 'number', 'exclusiveMinimum' => 0]],
            self::ShippingCategories, self::TaxCategories => $category,
            self::ShippingMethods => [...$named, 'enabled' => $bool, 'icon' => $nullableString, 'color' => $nullableString, 'orderCondition' => $condition, 'customerCondition' => $condition],
            self::ShippingRules => [...$described, 'methodId' => $id, 'priority' => $int, 'enabled' => $bool, 'orderCondition' => $condition, 'customerCondition' => $condition, 'orderConditionFormula' => $nullableString, 'baseRate' => $number, 'perItemRate' => $number, 'percentageRate' => $number, 'weightRate' => $number, 'minRate' => $number, 'maxRate' => $number],
            self::ShippingZones, self::TaxZones => [...$described, 'condition' => $condition, ...($this === self::TaxZones ? ['default' => $bool] : [])],
            self::TaxRates => ['name' => $string, 'code' => $nullableString, 'rate' => $number, 'include' => $bool, 'removeIncluded' => $bool, 'removeVatIncluded' => $bool, 'taxable' => ['enum' => ['price', 'shipping', 'price_shipping', 'order_total_shipping', 'order_total_price']], 'taxCategoryId' => $id, 'taxZoneId' => ['type' => ['integer', 'null']], 'enabled' => $bool],
            self::Discounts => [...$promotion, 'requireCouponCode' => $bool, 'couponFormat' => $string, 'perUserLimit' => $int, 'perEmailLimit' => $int, 'totalDiscountUseLimit' => $int, 'purchaseTotal' => $number, 'purchaseQty' => $int, 'maxPurchaseQty' => $int, 'baseDiscount' => $number, 'perItemDiscount' => $number, 'percentDiscount' => $number, 'percentageOffSubject' => ['enum' => ['original', 'discounted']], 'excludeOnPromotion' => $bool, 'hasFreeShippingForMatchingItems' => $bool, 'hasFreeShippingForOrder' => $bool, 'allPurchasables' => $bool, 'allCategories' => $bool, 'categoryRelationshipType' => ['enum' => ['sourceElement', 'targetElement', 'element']], 'stopProcessing' => $bool, 'ignorePromotions' => $bool, 'appliedTo' => $string, 'orderConditionFormula' => $nullableString, 'purchasableIds' => $ids, 'categoryIds' => $ids, 'orderCondition' => $condition, 'customerCondition' => $condition, 'shippingAddressCondition' => $condition, 'billingAddressCondition' => $condition],
            self::Sales => [...$promotion, 'apply' => ['enum' => ['toPercent', 'toFlat', 'byPercent', 'byFlat']], 'applyAmount' => $number, 'ignorePrevious' => $bool, 'stopProcessing' => $bool, 'allGroups' => $bool, 'allPurchasables' => $bool, 'allCategories' => $bool, 'categoryRelationshipType' => ['enum' => ['sourceElement', 'targetElement', 'element']], 'categoryIds' => $ids, 'purchasableIds' => $ids, 'userGroupIds' => $ids],
            self::CatalogPricingRules => [...$promotion, 'apply' => ['enum' => ['toPercent', 'toFlat', 'byPercent', 'byFlat']], 'applyAmount' => $number, 'applyPriceType' => $string, 'isPromotionalPrice' => $bool, 'customerCondition' => $condition, 'productCondition' => $condition, 'variantCondition' => $condition, 'purchasableCondition' => $condition],
        };

        if ($this === self::Discounts) {
            $properties['coupons'] = ['type' => 'array', 'maxItems' => 1000, 'description' => 'Complete replacement list. Preserve existing IDs to retain usage counts; omitted coupons are deleted.', 'items' => ['type' => 'object', 'properties' => ['id' => $id, 'code' => $string, 'maxUses' => ['type' => ['integer', 'null'], 'minimum' => 0]], 'required' => ['code'], 'additionalProperties' => false]];
        }
        if ($this === self::ShippingRules) {
            $properties['shippingRuleCategories'] = ['type' => 'array', 'items' => ['type' => 'object', 'properties' => ['shippingCategoryId' => $id, 'condition' => ['enum' => ['allow', 'disallow', 'require']], 'perItemRate' => ['type' => ['number', 'null']], 'weightRate' => ['type' => ['number', 'null']], 'percentageRate' => ['type' => ['number', 'null']]], 'required' => ['shippingCategoryId', 'condition'], 'additionalProperties' => false]];
        }

        return $this->storeScoped() ? ['storeId' => $id, ...$properties] : $properties;
    }

    /** @return list<string> */
    public function required(): array
    {
        return [
            ...($this->storeScoped() ? ['storeId'] : []),
            ...match ($this) {
                self::ProductTypes => ['name', 'handle', 'siteSettings'],
                self::PaymentCurrencies => ['iso'],
                self::ShippingRules => ['name', 'methodId'],
                self::ShippingZones, self::TaxZones => ['name', 'condition'],
                self::TaxRates => ['name', 'taxCategoryId'],
                default => array_key_exists('handle', $this->attributes()) ? ['name', 'handle'] : ['name'],
            },
        ];
    }

    public function deletionEffects(): string
    {
        return match ($this) {
            self::Stores => 'Deletes the store and its store-specific configuration. Commerce refuses to delete the primary store.',
            self::ProductTypes => 'Deletes this product type and its products and variants.',
            self::Gateways, self::LineItemStatuses => 'Archives the record. Historical references are retained.',
            self::ShippingCategories, self::TaxCategories => 'Deletes the category and updates related product types and purchasables.',
            self::InventoryLocations => 'Not supported. Deactivation requires choosing where to move inventory.',
            default => 'Deletes the record using its Commerce service, including its dependent associations.',
        };
    }
}
