<?php

declare(strict_types=1);

use CraftCms\Cms\Database\Table as CraftTable;
use CraftCms\Cms\Entry\Elements\Entry;
use CraftCms\Cms\Support\Json;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Purchasable\Conditions\CatalogPricingRulePurchasableEntryConditionRule;
use CraftCms\Commerce\Store\Stores;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Puts the promotion tables back the way Commerce 5 named them.
 */
function restoreCommerce5PromotionSchema(): void
{
    Schema::rename(Table::DISCOUNT_ENTRIES, 'commerce_discount_categories');
    Schema::table('commerce_discount_categories', fn(Blueprint $table) => $table->renameColumn('entryId', 'categoryId'));
    Schema::rename(Table::SALE_ENTRIES, 'commerce_sale_categories');
    Schema::table('commerce_sale_categories', fn(Blueprint $table) => $table->renameColumn('entryId', 'categoryId'));

    foreach (['allEntries' => 'allCategories', 'entryIds' => 'categoryIds', 'entryRelationshipType' => 'categoryRelationshipType'] as $from => $to) {
        Schema::table(Table::DISCOUNTS, fn(Blueprint $table) => $table->renameColumn($from, $to));
    }

    foreach (['allEntries' => 'allCategories', 'entryRelationshipType' => 'categoryRelationshipType'] as $from => $to) {
        Schema::table(Table::SALES, fn(Blueprint $table) => $table->renameColumn($from, $to));
    }
}

function runConvertPromotionCategoriesMigration(): void
{
    $migration = require dirname(__DIR__, 3) . '/database/migrations/2026_11_02_000000_convert_promotion_categories_to_entries.php';
    $migration->up();
}

function insertPromotionCategoryCondition(int $elementId): int
{
    return DB::table(Table::CATALOG_PRICING_RULES)->insertGetId([
        'storeId' => app(Stores::class)->getPrimaryStore()->id,
        'name' => 'Category rule',
        'purchasableCondition' => Json::encode([
            'class' => 'craft\\commerce\\elements\\conditions\\purchasables\\CatalogPricingRulePurchasableCondition',
            'conditionRules' => [
                [
                    'class' => 'craft\\commerce\\elements\\conditions\\purchasables\\CatalogPricingRulePurchasableCategoryConditionRule',
                    'uid' => 'rule-1',
                    'elementIds' => [$elementId],
                    'categoryRelationshipType' => 'sourceElement',
                ],
                [
                    'class' => 'craft\\elements\\conditions\\RelatedToConditionRule',
                    'uid' => 'rule-2',
                    'elementType' => 'craft\\elements\\Category',
                    'elementIds' => [$elementId],
                ],
            ],
        ]),
        'apply' => 'toPercent',
        'applyAmount' => 0,
        'applyPriceType' => 'price',
        'dateCreated' => now(),
        'dateUpdated' => now(),
        'uid' => fake()->uuid(),
    ]);
}

test('promotion category tables, columns and conditions are converted to entries', function() {
    restoreCommerce5PromotionSchema();
    $ruleId = insertPromotionCategoryCondition(999999);

    try {
        runConvertPromotionCategoriesMigration();

        expect(Schema::hasTable(Table::DISCOUNT_ENTRIES))->toBeTrue()
            ->and(Schema::hasTable('commerce_discount_categories'))->toBeFalse()
            ->and(Schema::hasColumn(Table::DISCOUNT_ENTRIES, 'entryId'))->toBeTrue()
            ->and(Schema::hasTable(Table::SALE_ENTRIES))->toBeTrue()
            ->and(Schema::hasColumns(Table::DISCOUNTS, ['allEntries', 'entryIds', 'entryRelationshipType']))->toBeTrue()
            ->and(Schema::hasColumns(Table::SALES, ['allEntries', 'entryRelationshipType']))->toBeTrue();

        $rules = Json::decode(DB::table(Table::CATALOG_PRICING_RULES)->where('id', $ruleId)->value('purchasableCondition'))['conditionRules'];

        expect($rules[0])->toBe([
            'class' => CatalogPricingRulePurchasableEntryConditionRule::class,
            'uid' => 'rule-1',
            'elementIds' => [999999],
            'entryRelationshipType' => 'sourceElement',
        ])->and($rules[1]['elementType'])->toBe(Entry::class);
    } finally {
        DB::table(Table::CATALOG_PRICING_RULES)->where('id', $ruleId)->delete();
    }
});

test('the conversion stops if promotions still use categories', function() {
    restoreCommerce5PromotionSchema();

    $categoryId = DB::table(CraftTable::ELEMENTS)->insertGetId([
        'type' => 'craft\\elements\\Category',
        'enabled' => true,
        'archived' => false,
        'dateCreated' => now(),
        'dateUpdated' => now(),
        'uid' => fake()->uuid(),
    ]);
    $ruleId = insertPromotionCategoryCondition($categoryId);

    try {
        expect(fn() => runConvertPromotionCategoriesMigration())
            ->toThrow(RuntimeException::class, 'categories are still used by: ' . Table::CATALOG_PRICING_RULES . '.purchasableCondition');

        expect(Schema::hasTable('commerce_discount_categories'))->toBeTrue();
    } finally {
        DB::table(Table::CATALOG_PRICING_RULES)->where('id', $ruleId)->delete();
        DB::table(CraftTable::ELEMENTS)->where('id', $categoryId)->delete();
        runConvertPromotionCategoriesMigration();
    }
});
