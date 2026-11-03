<?php

declare(strict_types=1);

use CraftCms\Cms\Edition;
use CraftCms\Cms\Support\Url;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Commerce\Product\Variant\Elements\Variant;
use CraftCms\Commerce\Promotion\Models\Sale as SaleRecord;
use CraftCms\Commerce\Promotion\Sales;
use CraftCms\Commerce\Store\Stores;
use CraftCms\Commerce\Tests\Support\SalesFixture;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\postJson;

beforeEach(function() {
    $this->withoutVite();
    actingAs(User::find()->admin(true)->one());
    prioritizeCommerceRoutes();

    $this->fixture = SalesFixture::seed();
    $this->salesPath = 'commerce/store-management/' . app(Stores::class)->getPrimaryStore()->handle . '/sales';
});

/**
 * Finds the control at `$path` anywhere in a resolved Ui tree.
 *
 * @param array<int, array<string, mixed>> $nodes
 * @param string[] $path
 * @return array<string, mixed>|null
 */
function salesUiControl(array $nodes, array $path): ?array
{
    foreach ($nodes as $node) {
        if (($node['control']['path'] ?? null) === $path) {
            return $node['control'];
        }

        foreach (['nodes', 'children'] as $key) {
            if (is_array($node[$key] ?? null) && $control = salesUiControl($node[$key], $path)) {
                return $control;
            }
        }
    }

    return null;
}

it('lists sales in a reorderable table', function() {
    get(Url::cpUrl($this->salesPath))
        ->assertOk()
        ->assertInertia(fn(AssertableInertia $page) => $page
            ->component('Ui', false)
            ->where('ui.nodes.0.component', 'craft:admin-table')
            ->where('ui.nodes.0.props.reorderUrl', Url::actionUrl('commerce/sales/reorder'))
            ->has('ui.nodes.0.props.rows', 2)
            ->where('ui.nodes.0.props.rows.0.name.label', 'My Percentage Sale')
            ->where('ui.nodes.0.props.rows.0.effect', '10% (off original price)')
            ->where('ui.nodes.0.props.rows.1.name.label', 'All Relationships')
            ->etc());
});

it('redirects to pricing rules when there are no sales', function() {
    foreach (app(Sales::class)->getAllSales() as $sale) {
        app(Sales::class)->deleteSaleById($sale->id);
    }

    get(Url::cpUrl($this->salesPath))->assertRedirect(Url::cpUrl(str_replace('/sales', '/pricing-rules', $this->salesPath)));
});

it('edits a sale with its amount shown as a positive percentage', function() {
    get(Url::cpUrl($this->salesPath . '/' . $this->fixture->percentageSale->id))
        ->assertOk()
        ->assertInertia(fn(AssertableInertia $page) => $page
            ->component('Ui', false)
            ->where('title', 'My Percentage Sale')
            ->where('ui.values.name', 'My Percentage Sale')
            ->where('ui.values.apply', SaleRecord::APPLY_BY_PERCENT)
            ->where('ui.values.applyAmount', 10)
            ->where('ui.values.restrictPurchasables', true)
            ->where('ui.values.purchasables.' . Variant::class, [$this->fixture->radHood->id])
            ->etc());
});

it('locks “ignore previous” on when the sale sets the price', function(string $apply, bool $locked) {
    $response = postJson(Url::actionUrl('commerce/sales/render-ui'), [
        'values' => ['id' => $this->fixture->percentageSale->id, 'apply' => $apply],
        'scope' => [],
    ])->assertOk();

    $ignorePrevious = salesUiControl($response->json('ui.nodes'), ['ignorePrevious']);

    expect($ignorePrevious)->not->toBeNull()
        ->and($ignorePrevious['mode'] === 'disabled')->toBe($locked);
})->with([
    'reduce by percent' => [SaleRecord::APPLY_BY_PERCENT, false],
    'set to flat amount' => [SaleRecord::APPLY_TO_FLAT, true],
]);

it('only shows purchasable selects when matching certain purchasables', function(bool $restrict) {
    $response = postJson(Url::actionUrl('commerce/sales/render-ui'), [
        'values' => ['id' => $this->fixture->percentageSale->id, 'restrictPurchasables' => $restrict],
        'scope' => [],
    ])->assertOk();

    expect(str_contains(json_encode($response->json('ui')), 'craft:element-select'))->toBe($restrict);
})->with([
    'restricted' => true,
    'all purchasables' => false,
]);

it('saves a sale from the edit screen’s values', function() {
    postJson(Url::actionUrl('commerce/sales/save'), [
        'name' => 'Flat price sale',
        'enabled' => true,
        'apply' => SaleRecord::APPLY_TO_FLAT,
        'applyAmount' => 15,
        'ignorePrevious' => false,
        'restrictPurchasables' => true,
        'purchasables' => [Variant::class => [$this->fixture->hctWhite->id]],
        'restrictRelatedElements' => false,
        'allGroups' => false,
        'groups' => [$this->fixture->group->id],
    ])->assertOk();

    $sale = collect(app(Sales::class)->getAllSales())->firstWhere('name', 'Flat price sale');

    expect($sale)->not->toBeNull()
        ->and($sale->applyAmount)->toEqual(-15)
        ->and($sale->ignorePrevious)->toBeTrue()
        ->and($sale->allPurchasables)->toBeFalse()
        ->and(array_map('intval', $sale->getPurchasableIds()))->toBe([$this->fixture->hctWhite->id])
        ->and($sale->allEntries)->toBeTrue();

    if (Edition::get() === Edition::Pro) {
        expect($sale->allGroups)->toBeFalse()
            ->and(array_map('intval', $sale->getUserGroupIds()))->toBe([$this->fixture->group->id]);
    }
});

it('stores percentage amounts as negative fractions', function() {
    postJson(Url::actionUrl('commerce/sales/save'), [
        'id' => $this->fixture->percentageSale->id,
        'name' => 'My Percentage Sale',
        'enabled' => true,
        'apply' => SaleRecord::APPLY_BY_PERCENT,
        'applyAmount' => 25,
        'restrictPurchasables' => false,
    ])->assertOk();

    $sale = app(Sales::class)->getSaleById($this->fixture->percentageSale->id);

    expect($sale->applyAmount)->toEqual(-0.25)
        ->and($sale->ignorePrevious)->toBeFalse()
        ->and($sale->allPurchasables)->toBeTrue();
});

it('reorders sales from the table’s posted ids', function() {
    $ids = [$this->fixture->allRelationshipsSale->id, $this->fixture->percentageSale->id];

    postJson(Url::actionUrl('commerce/sales/reorder'), ['ids' => $ids])->assertOk();

    expect(array_map(fn($sale) => $sale->id, array_values(app(Sales::class)->getAllSales())))->toBe($ids);
});
