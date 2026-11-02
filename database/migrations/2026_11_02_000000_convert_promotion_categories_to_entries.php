<?php

declare(strict_types=1);

use CraftCms\Cms\Database\Migration;
use CraftCms\Cms\Database\Table as CraftTable;
use CraftCms\Cms\Entry\Elements\Entry;
use CraftCms\Cms\Support\Facades\ProjectConfig;
use CraftCms\Cms\Support\Json;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Payment\Gateway\Gateways;
use CraftCms\Commerce\Purchasable\Conditions\CatalogPricingRulePurchasableEntryConditionRule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Commerce 6 doesn't support categories. Category groups must be converted to sections (with Craft's
 * `entrify/categories` command, before upgrading) — categories keep their element IDs when they're
 * converted, so promotions only need their category-named tables, columns and condition rules renamed.
 */
return new class() extends Migration {
    private const string CATEGORY_CLASS = 'craft\\elements\\Category';

    /** Class names the catalog pricing rule's category condition rule has been stored under. */
    private const array CATEGORY_RULE_CLASSES = [
        'craft\\commerce\\elements\\conditions\\purchasables\\CatalogPricingRulePurchasableCategoryConditionRule',
        'CraftCms\\Commerce\\Purchasable\\Conditions\\CatalogPricingRulePurchasableCategoryConditionRule',
    ];

    /** Condition columns, by table. */
    private const array CONDITION_COLUMNS = [
        Table::CATALOG_PRICING_RULES => ['productCondition', 'variantCondition', 'purchasableCondition', 'customerCondition'],
        Table::DISCOUNTS => ['orderCondition', 'customerCondition', 'shippingAddressCondition', 'billingAddressCondition'],
        Table::GATEWAYS => ['orderCondition', 'shippingAddressCondition', 'billingAddressCondition'],
        Table::SHIPPINGMETHODS => ['orderCondition', 'customerCondition'],
        Table::SHIPPINGRULES => ['orderCondition', 'customerCondition'],
        Table::STORESETTINGS => ['marketAddressCondition'],
    ];

    public function up(): void
    {
        $this->ensureCategoriesWereConverted();

        $this->renameTable('commerce_discount_categories', Table::DISCOUNT_ENTRIES, 'categoryId', 'entryId');
        $this->renameTable('commerce_sale_categories', Table::SALE_ENTRIES, 'categoryId', 'entryId');

        $this->renameColumns(Table::DISCOUNTS, [
            'allCategories' => 'allEntries',
            'categoryIds' => 'entryIds',
            'categoryRelationshipType' => 'entryRelationshipType',
        ]);
        $this->renameColumns(Table::SALES, [
            'allCategories' => 'allEntries',
            'categoryRelationshipType' => 'entryRelationshipType',
        ]);

        foreach (self::CONDITION_COLUMNS as $table => $columns) {
            $this->convertConditionColumns($table, $columns);
        }

        foreach (ProjectConfig::get(Gateways::CONFIG_GATEWAY_KEY) ?? [] as $uid => $config) {
            foreach (self::CONDITION_COLUMNS[Table::GATEWAYS] as $key) {
                if (is_array($config[$key] ?? null) && ($converted = $this->convertCondition($config[$key])) !== null) {
                    ProjectConfig::set(Gateways::CONFIG_GATEWAY_KEY . ".$uid.$key", $converted);
                }
            }
        }
    }

    /**
     * Stops the upgrade if promotions or conditions still reference categories, which would no longer match anything.
     */
    private function ensureCategoriesWereConverted(): void
    {
        $categoryIds = DB::table(CraftTable::ELEMENTS)->where('type', self::CATEGORY_CLASS)->pluck('id')->all();

        if ($categoryIds === []) {
            return;
        }

        $referenced = [];

        foreach (['commerce_discount_categories', 'commerce_sale_categories'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->whereIn('categoryId', $categoryIds)->exists()) {
                $referenced[] = $table === 'commerce_discount_categories' ? 'discounts' : 'sales';
            }
        }

        foreach (self::CONDITION_COLUMNS as $table => $columns) {
            foreach (DB::table($table)->select($columns)->get() as $row) {
                foreach ($columns as $column) {
                    if ($this->conditionReferencesCategories($row->$column ?? null, $categoryIds)) {
                        $referenced[] = "$table.$column";
                    }
                }
            }
        }

        if ($referenced !== []) {
            throw new RuntimeException(sprintf(
                "Commerce 6 doesn't support categories, but categories are still used by: %s. Convert them to entries with Commerce 5's `php craft entrify/categories` command (see the \"Commerce 6 Ready\" utility), then upgrade again.",
                implode(', ', array_unique($referenced)),
            ));
        }
    }

    private function conditionReferencesCategories(mixed $config, array $categoryIds): bool
    {
        $config = is_string($config) ? Json::decodeIfJson($config) : $config;

        if (!is_array($config)) {
            return false;
        }

        foreach ($config['conditionRules'] ?? [] as $rule) {
            if (array_intersect(array_map('intval', (array)($rule['elementIds'] ?? [])), $categoryIds)) {
                return true;
            }
        }

        return false;
    }

    private function renameTable(string $from, string $to, string $fromColumn, string $toColumn): void
    {
        if (!Schema::hasTable($from)) {
            return;
        }

        Schema::rename($from, $to);
        Schema::table($to, fn(Blueprint $table) => $table->renameColumn($fromColumn, $toColumn));
    }

    /**
     * @param array<string, string> $columns
     */
    private function renameColumns(string $table, array $columns): void
    {
        foreach ($columns as $from => $to) {
            if (Schema::hasColumn($table, $from)) {
                Schema::table($table, fn(Blueprint $blueprint) => $blueprint->renameColumn($from, $to));
            }
        }
    }

    /**
     * @param string[] $columns
     */
    private function convertConditionColumns(string $table, array $columns): void
    {
        foreach (DB::table($table)->select(['id', ...$columns])->get() as $row) {
            $updates = [];

            foreach ($columns as $column) {
                $config = is_string($row->$column) ? Json::decodeIfJson($row->$column) : null;

                if (is_array($config) && ($converted = $this->convertCondition($config)) !== null) {
                    $updates[$column] = Json::encode($converted);
                }
            }

            if ($updates !== []) {
                DB::table($table)->where('id', $row->id)->update($updates);
            }
        }
    }

    /**
     * Converts category condition rules to entry condition rules. Returns `null` if nothing changed.
     */
    private function convertCondition(array $config): ?array
    {
        $changed = false;

        foreach ($config['conditionRules'] ?? [] as $i => $rule) {
            if (in_array($rule['class'] ?? null, self::CATEGORY_RULE_CLASSES, true)) {
                $rule['class'] = CatalogPricingRulePurchasableEntryConditionRule::class;

                if (array_key_exists('categoryRelationshipType', $rule)) {
                    $rule['entryRelationshipType'] = $rule['categoryRelationshipType'];
                    unset($rule['categoryRelationshipType']);
                }

                $config['conditionRules'][$i] = $rule;
                $changed = true;
            } elseif (($rule['elementType'] ?? null) === self::CATEGORY_CLASS) {
                $config['conditionRules'][$i]['elementType'] = Entry::class;
                $changed = true;
            }
        }

        return $changed ? $config : null;
    }
};
