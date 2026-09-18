<?php

declare(strict_types=1);

use CraftCms\Cms\Support\Json;
use CraftCms\Cms\Support\Url;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Shipping\Data\ShippingMethod;
use CraftCms\Commerce\Shipping\Data\ShippingRule;
use CraftCms\Commerce\Shipping\ShippingMethods;
use CraftCms\Commerce\Shipping\ShippingRules;
use CraftCms\Commerce\Store\Stores;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;

beforeEach(function() {
    actingAs(User::find()->admin(true)->one());
    prioritizeCommerceRoutes();
});

/** @return array{storeId: int, method: ShippingMethod, usOnly: ShippingRule, usOnly2: ShippingRule} */
function seedShippingRulesForController(): array
{
    $storeId = app(Stores::class)->getPrimaryStore()->id;

    $method = new ShippingMethod();
    $method->storeId = $storeId;
    $method->name = 'US Shipping';
    $method->handle = 'usShipping';
    $method->enabled = true;
    if (!app(ShippingMethods::class)->saveShippingMethod($method)) {
        throw new RuntimeException('Could not save shipping method: ' . json_encode($method->errors()->all()));
    }

    $usOnly = new ShippingRule();
    $usOnly->storeId = $storeId;
    $usOnly->methodId = $method->id;
    $usOnly->name = 'US Shipping';
    $usOnly->enabled = true;
    $usOnly->priority = 0;
    if (!app(ShippingRules::class)->saveShippingRule($usOnly)) {
        throw new RuntimeException('Could not save shipping rule: ' . json_encode($usOnly->errors()->all()));
    }

    $usOnly2 = new ShippingRule();
    $usOnly2->storeId = $storeId;
    $usOnly2->methodId = $method->id;
    $usOnly2->name = 'US Shipping 2';
    $usOnly2->enabled = true;
    $usOnly2->priority = 1;
    if (!app(ShippingRules::class)->saveShippingRule($usOnly2)) {
        throw new RuntimeException('Could not save shipping rule: ' . json_encode($usOnly2->errors()->all()));
    }

    return compact('storeId', 'method', 'usOnly', 'usOnly2');
}

/** @return array<string, mixed> */
function shippingRuleSaveBody(ShippingMethod $method, ShippingRule $rule, ?string $name = null): array
{
    return [
        'id' => $rule->id,
        'storeId' => $method->storeId,
        'name' => $name ?? $rule->name,
        'methodId' => $rule->methodId,
        'enabled' => $rule->enabled,
        'orderConditionFormula' => '',
        'baseRate' => ['value' => 0],
        'perItemRate' => ['value' => 0],
        'weightRate' => ['value' => 0],
        'percentageRate' => 0,
        'minRate' => ['value' => 0],
        'maxRate' => ['value' => 0],
        'ruleCategories' => [],
        'orderCondition' => null,
    ];
}

it('reorders shipping rules', function() {
    ['usOnly' => $usOnly, 'usOnly2' => $usOnly2] = seedShippingRulesForController();

    $ids = [$usOnly2->id, $usOnly->id];

    postJson(Url::actionUrl('commerce/shipping-rules/reorder'), [
        'ids' => Json::encode($ids),
    ])
        ->assertOk()
        ->assertExactJson([]);

    $results = DB::table(Table::SHIPPINGRULES)
        ->whereIn('id', $ids)
        ->orderBy('priority')
        ->pluck('id')
        ->all();

    expect($results)->toBe($ids);
});

it('saves changes to an existing shipping rule', function() {
    ['method' => $method, 'usOnly' => $usOnly] = seedShippingRulesForController();

    $newName = $usOnly->name . ' saved';

    postJson(Url::actionUrl('commerce/shipping-rules/save'), shippingRuleSaveBody($method, $usOnly, $newName))
        ->assertOk();

    $result = DB::table(Table::SHIPPINGRULES)->where('id', $usOnly->id)->value('name');

    expect($result)->toBe($newName);
});

it('deletes a shipping rule via an ajax request', function() {
    ['usOnly' => $usOnly] = seedShippingRulesForController();

    postJson(Url::actionUrl('commerce/shipping-rules/delete'), ['id' => $usOnly->id], [
        'X-Requested-With' => 'XMLHttpRequest',
    ])
        ->assertOk()
        ->assertExactJson([]);

    expect(DB::table(Table::SHIPPINGRULES)->where('id', $usOnly->id)->exists())->toBeFalse();
});

it('deletes a shipping rule via a normal (non-ajax) request', function() {
    ['usOnly2' => $usOnly2] = seedShippingRulesForController();

    postJson(Url::actionUrl('commerce/shipping-rules/delete'), ['id' => $usOnly2->id]);

    expect(DB::table(Table::SHIPPINGRULES)->where('id', $usOnly2->id)->exists())->toBeFalse();
});

it('duplicates a shipping rule', function() {
    ['method' => $method, 'usOnly' => $usOnly] = seedShippingRulesForController();

    postJson(Url::actionUrl('commerce/shipping-rules/duplicate'), shippingRuleSaveBody($method, $usOnly))
        ->assertOk();

    $count = DB::table(Table::SHIPPINGRULES)->where('name', $usOnly->name)->count();

    expect($count)->toBe(2);
});
