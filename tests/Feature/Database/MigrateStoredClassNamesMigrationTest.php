<?php

declare(strict_types=1);

use CraftCms\Cms\Database\Table as CraftTable;
use CraftCms\Cms\ProjectConfig\ProjectConfig;
use CraftCms\Cms\Support\Json;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Order\Conditions\OrderCondition;
use CraftCms\Commerce\Order\Conditions\TotalPriceConditionRule;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Payment\Gateway\Types\Dummy;
use CraftCms\Commerce\Product\Fields\Products;
use CraftCms\Commerce\Product\Variant\FieldLayoutElements\VariantsField;
use CraftCms\Commerce\Store\Stores;
use Illuminate\Support\Facades\DB;

const LEGACY_ORDER_CONDITION = 'craft\\commerce\\elements\\conditions\\orders\\OrderCondition';
const LEGACY_TOTAL_PRICE_RULE = 'craft\\commerce\\elements\\conditions\\orders\\TotalPriceConditionRule';

function runStoredClassNamesMigration(): void
{
    $migration = require dirname(__DIR__, 3) . '/database/migrations/2026_11_26_000000_migrate_stored_class_names.php';
    $migration->up();
}

function legacyOrderCondition(): array
{
    return [
        'class' => LEGACY_ORDER_CONDITION,
        'conditionRules' => [
            [
                'class' => 'craft\\elements\\conditions\\ElementCondition',
                'conditionRules' => [
                    ['class' => LEGACY_TOTAL_PRICE_RULE, 'uid' => 'rule-1', 'operator' => '>=', 'value' => '10'],
                ],
            ],
        ],
    ];
}

test('every class the migration maps to exists', function() {
    $migration = require dirname(__DIR__, 3) . '/database/migrations/2026_11_26_000000_migrate_stored_class_names.php';
    $map = (new ReflectionClass($migration))->getReflectionConstant('CLASS_MAP')->getValue();

    expect($map)->not->toBeEmpty();

    foreach ($map as $legacy => $class) {
        expect(str_starts_with($legacy, 'craft\\commerce\\'))->toBeTrue()
            ->and(class_exists($class))->toBeTrue("$class does not exist");
    }
});

test('type columns are rewritten', function() {
    $now = now();
    $layoutId = DB::table(CraftTable::FIELDLAYOUTS)->insertGetId([
        'type' => 'craft\\commerce\\elements\\Order',
        'config' => Json::encode(['tabs' => [['name' => 'Content', 'elements' => [
            ['type' => 'craft\\commerce\\fieldlayoutelements\\VariantsField', 'uid' => 'element-1'],
            ['type' => 'craft\\fieldlayoutelements\\TitleField', 'uid' => 'element-2'],
        ]]]]),
        'dateCreated' => $now,
        'dateUpdated' => $now,
        'uid' => fake()->uuid(),
    ]);
    $fieldId = DB::table(CraftTable::FIELDS)->insertGetId([
        'name' => 'Products',
        'handle' => 'legacyProducts',
        'type' => 'craft\\commerce\\fields\\Products',
        'dateCreated' => $now,
        'dateUpdated' => $now,
        'uid' => fake()->uuid(),
    ]);
    $gatewayId = DB::table(Table::GATEWAYS)->insertGetId([
        'type' => 'craft\\commerce\\gateways\\Dummy',
        'name' => 'Dummy',
        'handle' => 'legacyDummy',
        'orderCondition' => Json::encode(legacyOrderCondition()),
        'dateCreated' => $now,
        'dateUpdated' => $now,
        'uid' => fake()->uuid(),
    ]);

    try {
        runStoredClassNamesMigration();

        $layout = DB::table(CraftTable::FIELDLAYOUTS)->where('id', $layoutId)->first();
        $elements = Json::decode($layout->config)['tabs'][0]['elements'];

        expect($layout->type)->toBe(Order::class)
            ->and($elements[0]['type'])->toBe(VariantsField::class)
            ->and($elements[1]['type'])->toBe('craft\\fieldlayoutelements\\TitleField')
            ->and(DB::table(CraftTable::FIELDS)->where('id', $fieldId)->value('type'))->toBe(Products::class)
            ->and(DB::table(Table::GATEWAYS)->where('id', $gatewayId)->value('type'))->toBe(Dummy::class);

        $condition = Json::decode(DB::table(Table::GATEWAYS)->where('id', $gatewayId)->value('orderCondition'));

        expect($condition['class'])->toBe(OrderCondition::class)
            ->and($condition['conditionRules'][0]['class'])->toBe('craft\\elements\\conditions\\ElementCondition')
            ->and($condition['conditionRules'][0]['conditionRules'][0]['class'])->toBe(TotalPriceConditionRule::class);
    } finally {
        DB::table(CraftTable::FIELDLAYOUTS)->where('id', $layoutId)->delete();
        DB::table(CraftTable::FIELDS)->where('id', $fieldId)->delete();
        DB::table(Table::GATEWAYS)->where('id', $gatewayId)->delete();
    }
});

test('stored conditions in other condition columns are rewritten', function() {
    $ruleId = DB::table(Table::CATALOG_PRICING_RULES)->insertGetId([
        'storeId' => app(Stores::class)->getPrimaryStore()->id,
        'name' => 'Legacy rule',
        'customerCondition' => Json::encode(['class' => 'craft\\commerce\\elements\\conditions\\customers\\CatalogPricingRuleCustomerCondition', 'conditionRules' => []]),
        'apply' => 'toPercent',
        'applyAmount' => 0,
        'applyPriceType' => 'price',
        'dateCreated' => now(),
        'dateUpdated' => now(),
        'uid' => fake()->uuid(),
    ]);

    try {
        runStoredClassNamesMigration();

        $condition = Json::decode(DB::table(Table::CATALOG_PRICING_RULES)->where('id', $ruleId)->value('customerCondition'));

        expect($condition['class'])->toBe(CraftCms\Commerce\Customer\Conditions\CatalogPricingRuleCustomerCondition::class);
    } finally {
        DB::table(Table::CATALOG_PRICING_RULES)->where('id', $ruleId)->delete();
    }
});

test('project config keys, values and encoded conditions are rewritten', function() {
    $projectConfig = app(ProjectConfig::class);
    $muteEvents = $projectConfig->muteEvents;
    $projectConfig->muteEvents = true;

    $projectConfig->set('elementSources', ['craft\\commerce\\elements\\Order' => [['key' => 'all']]]);
    $projectConfig->set('commerce.gateways.legacy-uid', [
        'type' => 'craft\\commerce\\gateways\\Dummy',
        'name' => 'Dummy',
        'orderCondition' => legacyOrderCondition(),
        'encoded' => Json::encode(legacyOrderCondition()),
    ]);

    try {
        runStoredClassNamesMigration();

        $sources = $projectConfig->get('elementSources');
        $gateway = $projectConfig->get('commerce.gateways.legacy-uid');

        expect($sources)->toHaveKey(Order::class)
            ->not->toHaveKey('craft\\commerce\\elements\\Order')
            ->and($gateway['type'])->toBe(Dummy::class)
            ->and($gateway['orderCondition']['class'])->toBe(OrderCondition::class)
            ->and($gateway['orderCondition']['conditionRules'][0]['conditionRules'][0]['class'])->toBe(TotalPriceConditionRule::class)
            ->and(Json::decode($gateway['encoded'])['class'])->toBe(OrderCondition::class);
    } finally {
        $projectConfig->remove('commerce.gateways.legacy-uid');
        $projectConfig->remove('elementSources');
        $projectConfig->saveModifiedConfigData();
        $projectConfig->muteEvents = $muteEvents;
    }
});

test('running the migration again changes nothing', function() {
    $now = now();
    $gatewayId = DB::table(Table::GATEWAYS)->insertGetId([
        'type' => Dummy::class,
        'name' => 'Dummy',
        'handle' => 'modernDummy',
        'orderCondition' => Json::encode(['class' => OrderCondition::class, 'conditionRules' => []]),
        'dateCreated' => $now,
        'dateUpdated' => $now,
        'uid' => fake()->uuid(),
    ]);

    try {
        $before = (array)DB::table(Table::GATEWAYS)->where('id', $gatewayId)->first();

        runStoredClassNamesMigration();

        expect((array)DB::table(Table::GATEWAYS)->where('id', $gatewayId)->first())->toBe($before);
    } finally {
        DB::table(Table::GATEWAYS)->where('id', $gatewayId)->delete();
    }
});
