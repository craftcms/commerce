<?php

declare(strict_types=1);

use CraftCms\Cms\Cms;
use CraftCms\Cms\FieldLayout\FieldLayoutElementContext;
use CraftCms\Cms\Ui\Nodes\Table;
use CraftCms\Cms\Ui\UiContext;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Commerce\CatalogPricing\CatalogPricing;
use CraftCms\Commerce\CatalogPricing\CatalogPricingRules;
use CraftCms\Commerce\CatalogPricing\Conditions\CatalogPricingRuleVariantCondition;
use CraftCms\Commerce\CatalogPricing\Data\CatalogPricingRule;
use CraftCms\Commerce\Purchasable\Conditions\SkuConditionRule;
use CraftCms\Commerce\Purchasable\FieldLayoutElements\PurchasablePriceField;
use CraftCms\Commerce\Store\Stores;
use CraftCms\Commerce\Tests\Support\ProductConditionsFixture;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function() {
    $this->withoutVite();
    actingAs(User::find()->admin(true)->one());
    prioritizeCommerceRoutes();

    $this->fixture = ProductConditionsFixture::seed();

    $rule = new CatalogPricingRule();
    $rule->storeId = app(Stores::class)->getPrimaryStore()->id;
    $rule->name = 'Hoodie sale';
    $rule->applyAmount = -0.1;
    $rule->isPromotionalPrice = true;
    $rule->enabled = true;

    /** @var CatalogPricingRuleVariantCondition $variantCondition */
    $variantCondition = $rule->getVariantCondition();
    $skuRule = new SkuConditionRule();
    $skuRule->value = 'rad-hood';
    $variantCondition->addConditionRule($skuRule);
    $rule->setVariantCondition($variantCondition);

    expect(app(CatalogPricingRules::class)->saveCatalogPricingRule($rule))->toBeTrue();
    app(CatalogPricing::class)->generateCatalogPrices();

    $this->rule = $rule;
    $this->pricesPath = '/' . Cms::config()->cpTrigger . '/commerce/catalog-pricing';
});

it('renders the prices page grouped by purchasable', function() {
    get($this->pricesPath)
        ->assertOk()
        ->assertInertia(fn(AssertableInertia $page) => $page
            ->component('commerce::prices/Index', false)
            ->where('pagination.current_page', 1)
            ->where('pagination.from', 1)
            ->where('conditionRuleCount', 0)
            ->where('searchTerm', null)
            ->has('conditionBuilder')
            ->has('sites')
            ->has('prices', 3)
            ->where('prices.0.purchasable.title', 'Rad Hoodie')
            ->where('prices.0.source', 'Base Price')
            ->where('prices.0.rule', null)
            ->where('prices.1.purchasable', null)
            ->where('prices.1.rule.name', 'Hoodie sale')
            ->where('prices.1.rule.url', $this->rule->getCpEditUrl())
            ->where('prices.1.isPromotional', true)
            ->where('prices.2.purchasable.title', 'Plain T-Shirt')
        );
});

it('narrows the prices to a purchasable and a search', function() {
    get($this->pricesPath . '?purchasableId=' . $this->fixture->hoodieVariant->id)
        ->assertOk()
        ->assertInertia(fn(AssertableInertia $page) => $page
            ->where('pagination.total', 1)
            ->where('conditionRuleCount', 1)
            ->where('prices.0.purchasable.title', 'Rad Hoodie')
        );

    get($this->pricesPath . '?search=nothing-matches-this')
        ->assertOk()
        ->assertInertia(fn(AssertableInertia $page) => $page
            ->where('pagination.total', 0)
            ->where('searchTerm', 'nothing-matches-this')
            ->has('prices', 0)
        );
});

it('shows a purchasable’s catalog prices under its price field', function() {
    $field = new PurchasablePriceField(['uid' => 'price-field']);
    $node = $field->uiNode(new FieldLayoutElementContext($this->fixture->hoodieVariant, new UiContext()));

    $table = collect($node->children())->first(fn($child) => $child instanceof Table);

    expect($table)->not->toBeNull()
        ->and($table->uid())->toBe('price-field-catalog-prices')
        ->and(collect($table->props()['rows'])->pluck('rule')->all())
        ->toContain(['label' => 'Hoodie sale', 'url' => $this->rule->getCpEditUrl()]);

    $tShirtNode = $field->uiNode(new FieldLayoutElementContext($this->fixture->tShirtVariant, new UiContext()));

    expect(collect($tShirtNode->children())->contains(fn($child) => $child instanceof Table))->toBeFalse();
});
