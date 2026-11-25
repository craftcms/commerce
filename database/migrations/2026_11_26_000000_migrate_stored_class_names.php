<?php

declare(strict_types=1);

use CraftCms\Cms\Database\Migration;
use CraftCms\Cms\Database\Table as CraftTable;
use CraftCms\Cms\ProjectConfig\ProjectConfig;
use CraftCms\Cms\Support\Json;
use CraftCms\Commerce\Database\Table;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Rewrites the Commerce 5 `craft\commerce\*` class names stored in the database and project config
 * (element, field, widget, gateway, condition, layout element and tax ID validator types) to their
 * Commerce 6 classes.
 */
return new class() extends Migration {
    /** @var array<string, class-string> */
    private const array CLASS_MAP = [
        'craft\\commerce\\elements\\Donation' => \CraftCms\Commerce\Purchasable\Elements\Donation::class,
        'craft\\commerce\\elements\\Order' => \CraftCms\Commerce\Order\Elements\Order::class,
        'craft\\commerce\\elements\\Product' => \CraftCms\Commerce\Product\Elements\Product::class,
        'craft\\commerce\\elements\\Transfer' => \CraftCms\Commerce\Transfer\Elements\Transfer::class,
        'craft\\commerce\\elements\\Variant' => \CraftCms\Commerce\Product\Variant\Elements\Variant::class,
        'craft\\commerce\\elements\\conditions\\addresses\\DiscountAddressCondition' => \CraftCms\Commerce\Address\Conditions\DiscountAddressCondition::class,
        'craft\\commerce\\elements\\conditions\\addresses\\GatewayAddressCondition' => \CraftCms\Commerce\Address\Conditions\GatewayAddressCondition::class,
        'craft\\commerce\\elements\\conditions\\addresses\\PostalCodeFormulaConditionRule' => \CraftCms\Commerce\Address\Conditions\PostalCodeFormulaConditionRule::class,
        'craft\\commerce\\elements\\conditions\\addresses\\ZoneAddressCondition' => \CraftCms\Commerce\Address\Conditions\ZoneAddressCondition::class,
        'craft\\commerce\\elements\\conditions\\customers\\CatalogPricingRuleCustomerCondition' => \CraftCms\Commerce\Customer\Conditions\CatalogPricingRuleCustomerCondition::class,
        'craft\\commerce\\elements\\conditions\\customers\\CatalogPricingRuleCustomerConditionRule' => \CraftCms\Commerce\Customer\Conditions\CatalogPricingRuleCustomerConditionRule::class,
        'craft\\commerce\\elements\\conditions\\customers\\DiscountCustomerCondition' => \CraftCms\Commerce\Customer\Conditions\DiscountCustomerCondition::class,
        'craft\\commerce\\elements\\conditions\\customers\\HasOrdersConditionRule' => \CraftCms\Commerce\Customer\Conditions\HasOrdersConditionRule::class,
        'craft\\commerce\\elements\\conditions\\customers\\ShippingMethodCustomerCondition' => \CraftCms\Commerce\Customer\Conditions\ShippingMethodCustomerCondition::class,
        'craft\\commerce\\elements\\conditions\\customers\\ShippingRuleCustomerCondition' => \CraftCms\Commerce\Customer\Conditions\ShippingRuleCustomerCondition::class,
        'craft\\commerce\\elements\\conditions\\customers\\SignedInConditionRule' => \CraftCms\Commerce\Customer\Conditions\SignedInConditionRule::class,
        'craft\\commerce\\elements\\conditions\\orders\\CompletedConditionRule' => \CraftCms\Commerce\Order\Conditions\CompletedConditionRule::class,
        'craft\\commerce\\elements\\conditions\\orders\\ContainsPurchasablesConditionRule' => \CraftCms\Commerce\Order\Conditions\ContainsPurchasablesConditionRule::class,
        'craft\\commerce\\elements\\conditions\\orders\\CouponCodeConditionRule' => \CraftCms\Commerce\Order\Conditions\CouponCodeConditionRule::class,
        'craft\\commerce\\elements\\conditions\\orders\\CustomerConditionRule' => \CraftCms\Commerce\Order\Conditions\CustomerConditionRule::class,
        'craft\\commerce\\elements\\conditions\\orders\\DateOrderedConditionRule' => \CraftCms\Commerce\Order\Conditions\DateOrderedConditionRule::class,
        'craft\\commerce\\elements\\conditions\\orders\\DiscountOrderCondition' => \CraftCms\Commerce\Order\Conditions\DiscountOrderCondition::class,
        'craft\\commerce\\elements\\conditions\\orders\\DiscountedItemSubtotalConditionRule' => \CraftCms\Commerce\Order\Conditions\DiscountedItemSubtotalConditionRule::class,
        'craft\\commerce\\elements\\conditions\\orders\\GatewayOrderCondition' => \CraftCms\Commerce\Order\Conditions\GatewayOrderCondition::class,
        'craft\\commerce\\elements\\conditions\\orders\\HasAdminNoticesConditionRule' => \CraftCms\Commerce\Order\Conditions\HasAdminNoticesConditionRule::class,
        'craft\\commerce\\elements\\conditions\\orders\\HasPurchasableConditionRule' => \CraftCms\Commerce\Order\Conditions\HasPurchasableConditionRule::class,
        'craft\\commerce\\elements\\conditions\\orders\\ItemSubtotalConditionRule' => \CraftCms\Commerce\Order\Conditions\ItemSubtotalConditionRule::class,
        'craft\\commerce\\elements\\conditions\\orders\\ItemTotalConditionRule' => \CraftCms\Commerce\Order\Conditions\ItemTotalConditionRule::class,
        'craft\\commerce\\elements\\conditions\\orders\\OrderCondition' => \CraftCms\Commerce\Order\Conditions\OrderCondition::class,
        'craft\\commerce\\elements\\conditions\\orders\\OrderCurrencyValuesAttributeConditionRule' => \CraftCms\Commerce\Order\Conditions\OrderCurrencyValuesAttributeConditionRule::class,
        'craft\\commerce\\elements\\conditions\\orders\\OrderSiteConditionRule' => \CraftCms\Commerce\Order\Conditions\OrderSiteConditionRule::class,
        'craft\\commerce\\elements\\conditions\\orders\\OrderStatusConditionRule' => \CraftCms\Commerce\Order\Conditions\OrderStatusConditionRule::class,
        'craft\\commerce\\elements\\conditions\\orders\\OrderTextValuesAttributeConditionRule' => \CraftCms\Commerce\Order\Conditions\OrderTextValuesAttributeConditionRule::class,
        'craft\\commerce\\elements\\conditions\\orders\\OrderValuesAttributeConditionRule' => \CraftCms\Commerce\Order\Conditions\OrderValuesAttributeConditionRule::class,
        'craft\\commerce\\elements\\conditions\\orders\\PaidConditionRule' => \CraftCms\Commerce\Order\Conditions\PaidConditionRule::class,
        'craft\\commerce\\elements\\conditions\\orders\\PaymentGatewayConditionRule' => \CraftCms\Commerce\Order\Conditions\PaymentGatewayConditionRule::class,
        'craft\\commerce\\elements\\conditions\\orders\\ReferenceConditionRule' => \CraftCms\Commerce\Order\Conditions\ReferenceConditionRule::class,
        'craft\\commerce\\elements\\conditions\\orders\\ShippingAddressZoneConditionRule' => \CraftCms\Commerce\Order\Conditions\ShippingAddressZoneConditionRule::class,
        'craft\\commerce\\elements\\conditions\\orders\\ShippingMethodConditionRule' => \CraftCms\Commerce\Order\Conditions\ShippingMethodConditionRule::class,
        'craft\\commerce\\elements\\conditions\\orders\\ShippingMethodOrderCondition' => \CraftCms\Commerce\Order\Conditions\ShippingMethodOrderCondition::class,
        'craft\\commerce\\elements\\conditions\\orders\\ShippingRuleOrderCondition' => \CraftCms\Commerce\Order\Conditions\ShippingRuleOrderCondition::class,
        'craft\\commerce\\elements\\conditions\\orders\\TotalConditionRule' => \CraftCms\Commerce\Order\Conditions\TotalConditionRule::class,
        'craft\\commerce\\elements\\conditions\\orders\\TotalDiscountConditionRule' => \CraftCms\Commerce\Order\Conditions\TotalDiscountConditionRule::class,
        'craft\\commerce\\elements\\conditions\\orders\\TotalPaidConditionRule' => \CraftCms\Commerce\Order\Conditions\TotalPaidConditionRule::class,
        'craft\\commerce\\elements\\conditions\\orders\\TotalPriceConditionRule' => \CraftCms\Commerce\Order\Conditions\TotalPriceConditionRule::class,
        'craft\\commerce\\elements\\conditions\\orders\\TotalQtyConditionRule' => \CraftCms\Commerce\Order\Conditions\TotalQtyConditionRule::class,
        'craft\\commerce\\elements\\conditions\\orders\\TotalTaxConditionRule' => \CraftCms\Commerce\Order\Conditions\TotalTaxConditionRule::class,
        'craft\\commerce\\elements\\conditions\\orders\\TotalWeightConditionRule' => \CraftCms\Commerce\Order\Conditions\TotalWeightConditionRule::class,
        'craft\\commerce\\elements\\conditions\\products\\CatalogPricingRuleProductCondition' => \CraftCms\Commerce\CatalogPricing\Conditions\CatalogPricingRuleProductCondition::class,
        'craft\\commerce\\elements\\conditions\\products\\ProductCondition' => \CraftCms\Commerce\Product\Conditions\ProductCondition::class,
        'craft\\commerce\\elements\\conditions\\products\\ProductTypeConditionRule' => \CraftCms\Commerce\Product\Conditions\ProductTypeConditionRule::class,
        'craft\\commerce\\elements\\conditions\\products\\ProductVariantInventoryTrackedConditionRule' => \CraftCms\Commerce\Product\Conditions\ProductVariantInventoryTrackedConditionRule::class,
        'craft\\commerce\\elements\\conditions\\products\\ProductVariantPriceConditionRule' => \CraftCms\Commerce\Product\Conditions\ProductVariantPriceConditionRule::class,
        'craft\\commerce\\elements\\conditions\\products\\ProductVariantSearchConditionRule' => \CraftCms\Commerce\Product\Conditions\ProductVariantSearchConditionRule::class,
        'craft\\commerce\\elements\\conditions\\products\\ProductVariantSkuConditionRule' => \CraftCms\Commerce\Product\Conditions\ProductVariantSkuConditionRule::class,
        'craft\\commerce\\elements\\conditions\\products\\ProductVariantStockConditionRule' => \CraftCms\Commerce\Product\Conditions\ProductVariantStockConditionRule::class,
        'craft\\commerce\\elements\\conditions\\purchasables\\CatalogPricingCondition' => \CraftCms\Commerce\CatalogPricing\Conditions\CatalogPricingCondition::class,
        'craft\\commerce\\elements\\conditions\\purchasables\\CatalogPricingCustomerConditionRule' => \CraftCms\Commerce\CatalogPricing\Conditions\CatalogPricingCustomerConditionRule::class,
        'craft\\commerce\\elements\\conditions\\purchasables\\CatalogPricingPurchasableConditionRule' => \CraftCms\Commerce\CatalogPricing\Conditions\CatalogPricingPurchasableConditionRule::class,
        'craft\\commerce\\elements\\conditions\\purchasables\\CatalogPricingRulePurchasableCondition' => \CraftCms\Commerce\Purchasable\Conditions\CatalogPricingRulePurchasableCondition::class,
        'craft\\commerce\\elements\\conditions\\purchasables\\PurchasableConditionRule' => \CraftCms\Commerce\Purchasable\Conditions\PurchasableConditionRule::class,
        'craft\\commerce\\elements\\conditions\\purchasables\\PurchasableTypeConditionRule' => \CraftCms\Commerce\Purchasable\Conditions\PurchasableTypeConditionRule::class,
        'craft\\commerce\\elements\\conditions\\purchasables\\SkuConditionRule' => \CraftCms\Commerce\Purchasable\Conditions\SkuConditionRule::class,
        'craft\\commerce\\elements\\conditions\\transfers\\TransferCondition' => \CraftCms\Commerce\Transfer\Conditions\TransferCondition::class,
        'craft\\commerce\\elements\\conditions\\users\\DiscountGroupConditionRule' => \CraftCms\Commerce\Customer\Conditions\DiscountGroupConditionRule::class,
        'craft\\commerce\\elements\\conditions\\variants\\CatalogPricingRuleVariantCondition' => \CraftCms\Commerce\CatalogPricing\Conditions\CatalogPricingRuleVariantCondition::class,
        'craft\\commerce\\elements\\conditions\\variants\\CatalogPricingRuleVariantConditionRule' => \CraftCms\Commerce\CatalogPricing\Conditions\CatalogPricingRuleVariantConditionRule::class,
        'craft\\commerce\\elements\\conditions\\variants\\ProductConditionRule' => \CraftCms\Commerce\Product\Variant\Conditions\VariantProductConditionRule::class,
        'craft\\commerce\\elements\\conditions\\variants\\VariantCondition' => \CraftCms\Commerce\Product\Variant\Conditions\VariantCondition::class,
        'craft\\commerce\\elements\\conditions\\variants\\VariantConditionRule' => \CraftCms\Commerce\Product\Variant\Conditions\VariantConditionRule::class,
        'craft\\commerce\\fieldlayoutelements\\ProductTitleField' => \CraftCms\Commerce\Product\FieldLayoutElements\ProductTitleField::class,
        'craft\\commerce\\fieldlayoutelements\\PurchasableAllowedQtyField' => \CraftCms\Commerce\Purchasable\FieldLayoutElements\PurchasableAllowedQtyField::class,
        'craft\\commerce\\fieldlayoutelements\\PurchasableAvailableForPurchaseField' => \CraftCms\Commerce\Purchasable\FieldLayoutElements\PurchasableAvailableForPurchaseField::class,
        'craft\\commerce\\fieldlayoutelements\\PurchasableDimensionsField' => \CraftCms\Commerce\Purchasable\FieldLayoutElements\PurchasableDimensionsField::class,
        'craft\\commerce\\fieldlayoutelements\\PurchasableFreeShippingField' => \CraftCms\Commerce\Purchasable\FieldLayoutElements\PurchasableFreeShippingField::class,
        'craft\\commerce\\fieldlayoutelements\\PurchasablePriceField' => \CraftCms\Commerce\Purchasable\FieldLayoutElements\PurchasablePriceField::class,
        'craft\\commerce\\fieldlayoutelements\\PurchasablePromotableField' => \CraftCms\Commerce\Purchasable\FieldLayoutElements\PurchasablePromotableField::class,
        'craft\\commerce\\fieldlayoutelements\\PurchasableSkuField' => \CraftCms\Commerce\Purchasable\FieldLayoutElements\PurchasableSkuField::class,
        'craft\\commerce\\fieldlayoutelements\\PurchasableStockField' => \CraftCms\Commerce\Purchasable\FieldLayoutElements\PurchasableStockField::class,
        'craft\\commerce\\fieldlayoutelements\\PurchasableWeightField' => \CraftCms\Commerce\Purchasable\FieldLayoutElements\PurchasableWeightField::class,
        'craft\\commerce\\fieldlayoutelements\\TransferManagementField' => \CraftCms\Commerce\Transfer\FieldLayoutElements\TransferManagementField::class,
        'craft\\commerce\\fieldlayoutelements\\UserAddressSettings' => \CraftCms\Commerce\Customer\FieldLayoutElements\UserAddressSettings::class,
        'craft\\commerce\\fieldlayoutelements\\VariantTitleField' => \CraftCms\Commerce\Product\Variant\FieldLayoutElements\VariantTitleField::class,
        'craft\\commerce\\fieldlayoutelements\\VariantsField' => \CraftCms\Commerce\Product\Variant\FieldLayoutElements\VariantsField::class,
        'craft\\commerce\\fields\\Products' => \CraftCms\Commerce\Product\Fields\Products::class,
        'craft\\commerce\\fields\\Variants' => \CraftCms\Commerce\Product\Fields\Variants::class,
        'craft\\commerce\\gateways\\Dummy' => \CraftCms\Commerce\Payment\Gateway\Types\Dummy::class,
        'craft\\commerce\\gateways\\Manual' => \CraftCms\Commerce\Payment\Gateway\Types\Manual::class,
        'craft\\commerce\\gateways\\MissingGateway' => \CraftCms\Commerce\Payment\Gateway\Types\MissingGateway::class,
        'craft\\commerce\\taxidvalidators\\EuVatIdValidator' => \CraftCms\Commerce\Tax\VatValidator\Eu::class,
        'craft\\commerce\\widgets\\AverageOrderTotal' => \CraftCms\Commerce\Dashboard\Widgets\AverageOrderTotal::class,
        'craft\\commerce\\widgets\\NewCustomers' => \CraftCms\Commerce\Dashboard\Widgets\NewCustomers::class,
        'craft\\commerce\\widgets\\Orders' => \CraftCms\Commerce\Dashboard\Widgets\Orders::class,
        'craft\\commerce\\widgets\\RepeatCustomers' => \CraftCms\Commerce\Dashboard\Widgets\RepeatCustomers::class,
        'craft\\commerce\\widgets\\TopCustomers' => \CraftCms\Commerce\Dashboard\Widgets\TopCustomers::class,
        'craft\\commerce\\widgets\\TopProductTypes' => \CraftCms\Commerce\Dashboard\Widgets\TopProductTypes::class,
        'craft\\commerce\\widgets\\TopProducts' => \CraftCms\Commerce\Dashboard\Widgets\TopProducts::class,
        'craft\\commerce\\widgets\\TopPurchasables' => \CraftCms\Commerce\Dashboard\Widgets\TopPurchasables::class,
        'craft\\commerce\\widgets\\TotalOrders' => \CraftCms\Commerce\Dashboard\Widgets\TotalOrders::class,
        'craft\\commerce\\widgets\\TotalOrdersByCountry' => \CraftCms\Commerce\Dashboard\Widgets\TotalOrdersByCountry::class,
        'craft\\commerce\\widgets\\TotalRevenue' => \CraftCms\Commerce\Dashboard\Widgets\TotalRevenue::class,
    ];

    /** Columns holding a single class name, by table. */
    private const array TYPE_COLUMNS = [
        CraftTable::ELEMENTS => 'type',
        CraftTable::FIELDLAYOUTS => 'type',
        CraftTable::FIELDS => 'type',
        CraftTable::WIDGETS => 'type',
        Table::GATEWAYS => 'type',
    ];

    /** Columns holding JSON that contains class names, by table. */
    private const array JSON_COLUMNS = [
        CraftTable::FIELDLAYOUTS => ['config'],
        CraftTable::FIELDS => ['settings'],
        CraftTable::WIDGETS => ['settings'],
        Table::CATALOG_PRICING_RULES => ['productCondition', 'variantCondition', 'purchasableCondition', 'customerCondition'],
        Table::DISCOUNTS => ['orderCondition', 'customerCondition', 'shippingAddressCondition', 'billingAddressCondition'],
        Table::GATEWAYS => ['orderCondition', 'shippingAddressCondition', 'billingAddressCondition'],
        Table::SHIPPINGMETHODS => ['orderCondition', 'customerCondition'],
        Table::SHIPPINGRULES => ['orderCondition', 'customerCondition'],
        Table::STORESETTINGS => ['marketAddressCondition'],
        Table::TAXRATES => ['taxIdValidators'],
    ];

    public function up(): void
    {
        foreach (self::TYPE_COLUMNS as $table => $column) {
            $this->updateTypeColumn($table, $column);
        }

        foreach (self::JSON_COLUMNS as $table => $columns) {
            $this->updateJsonColumns($table, $columns);
        }

        $this->updateProjectConfig();
    }

    private function updateTypeColumn(string $table, string $column): void
    {
        $legacyTypes = DB::table($table)
            ->whereIn($column, array_keys(self::CLASS_MAP))
            ->distinct()
            ->pluck($column);

        foreach ($legacyTypes as $legacyType) {
            DB::table($table)->where($column, $legacyType)->update([$column => self::CLASS_MAP[$legacyType]]);
        }
    }

    /**
     * @param string[] $columns
     */
    private function updateJsonColumns(string $table, array $columns): void
    {
        foreach (DB::table($table)->select(['id', ...$columns])->lazyById() as $row) {
            $updates = [];

            foreach ($columns as $column) {
                $value = $row->$column;

                if (!is_string($value) || $value === '') {
                    continue;
                }

                $updated = $this->rewrite($value);

                if ($updated !== $value) {
                    $updates[$column] = $updated;
                }
            }

            if ($updates !== []) {
                DB::table($table)->where('id', $row->id)->update($updates);
            }
        }
    }

    private function updateProjectConfig(): void
    {
        $projectConfig = app(ProjectConfig::class);
        $muteEvents = $projectConfig->muteEvents;
        $projectConfig->muteEvents = true;

        try {
            foreach ($projectConfig->get() as $key => $config) {
                $updated = $this->rewrite($config);

                if ($updated !== $config) {
                    $projectConfig->set($key, $updated);
                }
            }

            $projectConfig->saveModifiedConfigData();
        } finally {
            $projectConfig->muteEvents = $muteEvents;
        }
    }

    /**
     * Rewrites class names found in array keys and values, including inside JSON-encoded strings.
     */
    private function rewrite(mixed $value): mixed
    {
        if (is_string($value)) {
            return $this->rewriteString($value);
        }

        if ($value instanceof stdClass) {
            $array = (array)$value;
            $updated = $this->rewrite($array);

            return $updated === $array ? $value : (object)$updated;
        }

        if (!is_array($value)) {
            return $value;
        }

        if (isset($value[ProjectConfig::ASSOC_KEY])) {
            foreach ($value[ProjectConfig::ASSOC_KEY] as $i => [$key, $item]) {
                $value[ProjectConfig::ASSOC_KEY][$i] = [is_string($key) ? $this->rewriteString($key) : $key, $this->rewrite($item)];
            }

            return $value;
        }

        $updated = [];

        foreach ($value as $key => $item) {
            $newKey = is_string($key) ? $this->rewriteString($key) : $key;

            if ($newKey !== $key && array_key_exists($newKey, $value)) {
                Log::warning("Both `$key` and `$newKey` exist in the same config. The legacy entry has been retained; compare the two entries and remove the one you no longer need.");
                $newKey = $key;
            }

            $updated[$newKey] = $this->rewrite($item);
        }

        return $updated === $value ? $value : $updated;
    }

    private function rewriteString(string $value): string
    {
        $className = ltrim($value, '\\');

        if (isset(self::CLASS_MAP[$className])) {
            return self::CLASS_MAP[$className];
        }

        if (($value[0] ?? '') !== '{' && ($value[0] ?? '') !== '[') {
            return $value;
        }

        if (!json_validate($value)) {
            return $value;
        }

        $decoded = json_decode($value, true);
        $updated = $this->rewrite($decoded);

        return $updated !== $decoded ? Json::encode($updated) : $value;
    }

    /** The legacy class names can't be told apart from classes that were always stored under their Commerce 6 name. */
    public function down(): void
    {
    }
};
