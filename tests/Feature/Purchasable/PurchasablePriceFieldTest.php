<?php

declare(strict_types=1);

use CraftCms\Cms\FieldLayout\FieldLayoutElementContext;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Url;
use CraftCms\Cms\Ui\UiContext;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Commerce\CatalogPricing\CatalogPricingRules;
use CraftCms\Commerce\CatalogPricing\Data\CatalogPricingRule;
use CraftCms\Commerce\CatalogPricing\Models\CatalogPricingRule as CatalogPricingRuleRecord;
use CraftCms\Commerce\CatalogPricing\PurchasableCatalogPrices;
use CraftCms\Commerce\Customer\Conditions\CatalogPricingRuleCustomerConditionRule;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Product\Variant\Elements\Variant;
use CraftCms\Commerce\Purchasable\FieldLayoutElements\PurchasablePriceField;
use CraftCms\Commerce\Tests\Support\CatalogPricingFixture;
use CraftCms\Commerce\Ui\Nodes\CatalogPrices;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;

/**
 * @param array<string, mixed> $attributes
 */
function createPriceFieldTestRule(string $name, array $attributes = []): CatalogPricingRule
{
    $rule = new CatalogPricingRule([
        'name' => $name,
        'apply' => CatalogPricingRuleRecord::APPLY_BY_PERCENT,
        'applyAmount' => -0.1,
        'applyPriceType' => CatalogPricingRuleRecord::APPLY_PRICE_TYPE_PRICE,
        'enabled' => true,
        ...$attributes,
    ]);

    if (!app(CatalogPricingRules::class)->saveCatalogPricingRule($rule)) {
        throw new RuntimeException('Could not save catalog pricing rule: ' . json_encode($rule->errors()->all()));
    }

    return $rule;
}

/**
 * Replaces the variant's generated catalog prices so each test controls exactly which rows apply.
 *
 * @param list<array{rule: CatalogPricingRule|null, price: float, promotional?: bool}> $rows
 */
function setPriceFieldTestCatalogPrices(Variant $variant, array $rows): void
{
    DB::table(Table::CATALOG_PRICING)->delete();

    foreach ($rows as $row) {
        DB::table(Table::CATALOG_PRICING)->insert([
            'purchasableId' => $variant->id,
            'storeId' => $variant->getStore()->id,
            'price' => $row['price'],
            'catalogPricingRuleId' => $row['rule']?->id,
            'isPromotionalPrice' => $row['promotional'] ?? false,
            'hasUpdatePending' => false,
            'uid' => (string)Str::uuid(),
            'dateCreated' => now(),
            'dateUpdated' => now(),
        ]);
    }
}

/**
 * @return list<array<string, mixed>>
 */
function priceFieldTestRows(Variant $variant): array
{
    return app(PurchasableCatalogPrices::class)->data($variant->id, $variant->getStore())['rows'];
}

function priceFieldTestNode(Variant $variant): ?CatalogPrices
{
    $field = new PurchasablePriceField(['uid' => 'price-field-uid']);
    $group = $field->uiNode(new FieldLayoutElementContext($variant, new UiContext()));

    return collect($group->children())->first(fn($node) => $node instanceof CatalogPrices);
}

beforeEach(function() {
    $this->fixture = CatalogPricingFixture::seed();
    $this->variant = $this->fixture->hctWhite;
    $this->storeId = $this->variant->getStore()->id;

    actingAs(User::find()->admin(true)->one(), 'craft');
});

test('lists the variant’s catalog prices from lowest to highest', function() {
    $higher = createPriceFieldTestRule('Higher', ['storeId' => $this->storeId]);
    $lower = createPriceFieldTestRule('Lower', ['storeId' => $this->storeId]);

    setPriceFieldTestCatalogPrices($this->variant, [
        ['rule' => null, 'price' => 19.99],
        ['rule' => $higher, 'price' => 18.00],
        ['rule' => $lower, 'price' => 15.00, 'promotional' => true],
    ]);

    $rows = priceFieldTestRows($this->variant);
    $props = priceFieldTestNode($this->variant)->props();

    expect($props['heading'])->toBe('Pricing rules (2)')
        ->and(array_column($rows, 'name'))->toBe(['Lower', 'Higher'])
        ->and($rows[0]['isPromotionalPrice'])->toBeTrue()
        ->and($rows[0]['url'])->toBe($lower->getCpEditUrl())
        ->and($props['createUrl'])->toContain('pricing-rules/new')
        ->and($props['createUrl'])->toContain('purchasableId=' . $this->variant->id);
});

test('flags the lowest price that applies to everyone', function() {
    $vip = createPriceFieldTestRule('VIP', ['storeId' => $this->storeId]);
    $customerRule = new CatalogPricingRuleCustomerConditionRule();
    $customerRule->setElementIds([User::find()->admin(true)->one()->id]);
    $customerCondition = $vip->getCustomerCondition();
    $customerCondition->addConditionRule($customerRule);
    $vip->setCustomerCondition($customerCondition);
    app(CatalogPricingRules::class)->saveCatalogPricingRule($vip);

    $sale = createPriceFieldTestRule('Sale', ['storeId' => $this->storeId]);
    $other = createPriceFieldTestRule('Other', ['storeId' => $this->storeId]);

    setPriceFieldTestCatalogPrices($this->variant, [
        ['rule' => null, 'price' => 19.99],
        ['rule' => $vip, 'price' => 10.00],
        ['rule' => $sale, 'price' => 15.00],
        ['rule' => $other, 'price' => 17.00],
    ]);

    $rows = collect(priceFieldTestRows($this->variant))->keyBy('name');

    expect($rows['VIP']['specificCustomers'])->toBeTrue()
        ->and($rows['VIP']['lowestForEveryone'])->toBeFalse()
        ->and($rows['Sale']['specificCustomers'])->toBeFalse()
        ->and($rows['Sale']['lowestForEveryone'])->toBeTrue()
        ->and($rows['Other']['lowestForEveryone'])->toBeFalse();
});

test('doesn’t flag a rule when the base price is already the lowest', function() {
    $rule = createPriceFieldTestRule('Markup', ['storeId' => $this->storeId]);

    setPriceFieldTestCatalogPrices($this->variant, [
        ['rule' => null, 'price' => 19.99],
        ['rule' => $rule, 'price' => 22.00],
    ]);

    expect(priceFieldTestRows($this->variant)[0]['lowestForEveryone'])->toBeFalse();
});

test('notes that prices will update once an edited price is saved', function() {
    setPriceFieldTestCatalogPrices($this->variant, []);

    expect(priceFieldTestNode($this->variant)->props()['pricesChanged'])->toBeFalse();

    $this->variant->basePrice = 99.0;

    expect(priceFieldTestNode($this->variant)->props()['pricesChanged'])->toBeTrue();
});

test('shows nothing for a variant that has never been saved', function() {
    $variant = clone $this->variant;
    $variant->id = null;

    expect(priceFieldTestNode($variant))->toBeNull();
});

test('hides rule links and the create action from users who can’t manage pricing rules', function() {
    $rule = createPriceFieldTestRule('Sale', ['storeId' => $this->storeId]);
    setPriceFieldTestCatalogPrices($this->variant, [
        ['rule' => null, 'price' => 19.99],
        ['rule' => $rule, 'price' => 15.00],
    ]);

    $user = new User(['username' => 'price-field-viewer', 'email' => 'price-field-viewer@crafttest.com', 'admin' => false]);
    Elements::saveElement($user);
    actingAs($user, 'craft');

    $rows = priceFieldTestRows($this->variant);

    expect($rows[0]['name'])->toBe('Sale')
        ->and($rows[0]['url'])->toBeNull()
        ->and(priceFieldTestNode($this->variant)->props()['createUrl'])->toBeNull();
});

test('returns the latest catalog prices for a purchasable', function() {
    prioritizeCommerceRoutes();
    $rule = createPriceFieldTestRule('Sale', ['storeId' => $this->storeId]);
    setPriceFieldTestCatalogPrices($this->variant, [
        ['rule' => null, 'price' => 19.99],
        ['rule' => $rule, 'price' => 15.00],
    ]);

    postJson(Url::actionUrl('commerce/catalog-pricing/purchasable-prices'), [
        'purchasableId' => $this->variant->id,
        'storeId' => $this->storeId,
    ])
        ->assertOk()
        ->assertJsonPath('heading', 'Pricing rules (1)')
        ->assertJsonPath('rows.0.id', (string)$rule->id)
        ->assertJson(fn($json) => $json
            ->where('rows.0.rule.html', fn(string $html) => str_contains($html, 'Sale') && str_contains($html, 'Lowest for everyone'))
            ->etc());
});
