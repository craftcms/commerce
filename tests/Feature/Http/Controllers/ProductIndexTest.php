<?php

declare(strict_types=1);

use CraftCms\Cms\Cms;
use CraftCms\Cms\Element\Actions\Delete;
use CraftCms\Cms\Element\Actions\Duplicate;
use CraftCms\Cms\Element\Actions\SetStatus;
use CraftCms\Cms\Element\ElementSources;
use CraftCms\Cms\Entry\Actions\NewChild;
use CraftCms\Cms\Entry\Actions\NewSiblingAfter;
use CraftCms\Cms\Entry\Actions\NewSiblingBefore;
use CraftCms\Cms\Http\Controllers\Elements\ElementIndex\ElementIndexController;
use CraftCms\Cms\Http\Controllers\Elements\ElementSelectorModalController;
use CraftCms\Cms\Http\Controllers\Elements\PerformElementActionController;
use CraftCms\Cms\Support\Facades\Sites;
use CraftCms\Cms\Support\Url;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Commerce\Product\Conditions\ProductCondition;
use CraftCms\Commerce\Product\Conditions\ProductTypeConditionRule;
use CraftCms\Commerce\Product\Conditions\ProductVariantSkuConditionRule;
use CraftCms\Commerce\Product\Elements\Product;
use CraftCms\Commerce\Product\ProductType\Data\ProductType;
use CraftCms\Commerce\Product\ProductType\Data\ProductTypeSite;
use CraftCms\Commerce\Product\ProductType\ProductTypes;
use CraftCms\Commerce\Promotion\Actions\CreateDiscount;
use CraftCms\Commerce\Store\Stores;
use CraftCms\Commerce\Tests\Support\ProductConditionsFixture;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\postJson;

beforeEach(function() {
    $this->withoutVite();
    actingAs(User::find()->admin(true)->one());
    prioritizeCommerceRoutes();

    $this->fixture = ProductConditionsFixture::seed();
    $this->indexPath = '/' . Cms::config()->cpTrigger . '/commerce/products';
});

/**
 * @return list<string>
 */
function productIndexTestActionKeys(AssertableInertia $page): array
{
    return array_column($page->toArray()['props']['actions'] ?? [], 'key');
}

function productIndexTestPerform(string $source, string $actionClass, array $elementIds): Illuminate\Testing\TestResponse
{
    return postJson(action(PerformElementActionController::class), [
        'context' => 'index',
        'source' => $source,
        'viewState' => ['mode' => 'table', 'static' => false],
        'elementType' => Product::class,
        'elementAction' => $actionClass,
        'elementIds' => $elementIds,
    ]);
}

it('renders product rows with their price and SKU', function() {
    get($this->indexPath . '/hoodies')
        ->assertOk()
        ->assertInertia(fn(AssertableInertia $page) => $page
            ->has('data', 1)
            ->where('data.0.id', $this->fixture->hoodie->id)
            ->where('data.0.defaultPrice', '$123.99')
            ->where('data.0.defaultSku', '<code>rad-hood</code>')
        );
});

it('renders the variants column as text', function() {
    get($this->indexPath . '/hoodies?columns[]=variants')
        ->assertOk()
        ->assertInertia(fn(AssertableInertia $page) => $page->where('data.0.variants', 'Rad Hoodie'));
});

it('offers the product type actions on a product type source', function() {
    get($this->indexPath . '/hoodies')
        ->assertOk()
        ->assertInertia(fn(AssertableInertia $page) => expect(productIndexTestActionKeys($page))->toContain(
            Duplicate::class,
            Delete::class,
            SetStatus::class,
            CreateDiscount::class,
        ));
});

it('only offers delete on the all products source', function() {
    get($this->indexPath)
        ->assertOk()
        ->assertInertia(fn(AssertableInertia $page) => expect(productIndexTestActionKeys($page))
            ->toContain(Delete::class)
            ->not->toContain(Duplicate::class, SetStatus::class, CreateDiscount::class));
});

it('leaves out the promotion actions without the manage promotions permission', function() {
    Gate::before(fn($user, string $ability) => $ability === 'commerce-managePromotions' ? false : null);

    get($this->indexPath . '/hoodies')
        ->assertOk()
        ->assertInertia(fn(AssertableInertia $page) => expect(productIndexTestActionKeys($page))
            ->not->toContain(CreateDiscount::class));
});

it('sends the selected products to a new discount', function() {
    $storeHandle = app(Stores::class)->getCurrentStore()->handle;
    $source = 'productType:' . $this->fixture->hoodiesType->uid;

    productIndexTestPerform($source, CreateDiscount::class, [$this->fixture->hoodie->id])
        ->assertOk()
        ->assertJsonPath('redirect', Url::cpUrl("commerce/store-management/$storeHandle/discounts/new", [
            'purchasableIds' => (string)$this->fixture->hoodie->id,
        ]));
});

it('offers structure actions on a structured product type', function() {
    $siteId = Sites::getPrimarySite()->id;

    $productType = new ProductType();
    $productType->name = 'Widgets';
    $productType->handle = 'widgets';
    $productType->isStructure = true;
    $productType->maxLevels = 2;
    $productType->hasVariantTitleField = false;
    $productType->variantTitleFormat = '{product.title}';

    $siteSettings = new ProductTypeSite();
    $siteSettings->siteId = $siteId;
    $siteSettings->hasUrls = false;
    $siteSettings->enabledByDefault = true;
    $productType->setSiteSettings([$siteId => $siteSettings]);

    app(ProductTypes::class)->saveProductType($productType);
    $structureId = app(ProductTypes::class)->getProductTypeByHandle('widgets')->structureId;

    get($this->indexPath . '/widgets')
        ->assertOk()
        ->assertInertia(function(AssertableInertia $page) use ($structureId) {
            $page->where('source.structureId', $structureId);

            expect(productIndexTestActionKeys($page))->toContain(
                NewSiblingBefore::class,
                NewSiblingAfter::class,
                NewChild::class,
            );
        });
});

it('filters products by variant SKU', function() {
    get($this->indexPath . '?' . http_build_query(['condition' => [
        'class' => ProductCondition::class,
        'elementType' => Product::class,
        'conditionRules' => [[
            'class' => ProductVariantSkuConditionRule::class,
            'operator' => '=',
            'value' => 'plain-tee',
        ]],
    ]]))
        ->assertOk()
        ->assertInertia(fn(AssertableInertia $page) => $page
            ->has('data', 1)
            ->where('data.0.id', $this->fixture->tShirt->id)
        );
});

it('renders the product filters', function() {
    postJson(action([ElementIndexController::class, 'filterHud']), [
        'context' => 'index',
        'elementType' => Product::class,
        'source' => '*',
        'viewState' => ['mode' => 'table', 'static' => false],
        'id' => 'filters',
        'conditionConfig' => [
            'class' => ProductCondition::class,
            'elementType' => Product::class,
            'conditionRules' => [
                ['class' => ProductTypeConditionRule::class],
                ['class' => ProductVariantSkuConditionRule::class],
            ],
        ],
    ])
        ->assertOk()
        ->assertJsonPath('hudHtml', fn(string $html) => str_contains($html, 'condition-container'));
});

it('lists products in the element select modal', function() {
    $response = postJson(action(ElementSelectorModalController::class), [
        'context' => ElementSources::CONTEXT_MODAL,
        'elementType' => Product::class,
    ])->assertOk();

    expect($response->json('props.context'))->toBe(ElementSources::CONTEXT_MODAL)
        ->and(array_column($response->json('props.sources'), 'key'))->toContain(
            '*',
            'productType:' . $this->fixture->hoodiesType->uid,
            'productType:' . $this->fixture->tShirtsType->uid,
        )
        ->and(array_column($response->json('props.data'), 'id'))->toEqualCanonicalizing([
            $this->fixture->hoodie->id,
            $this->fixture->tShirt->id,
        ]);
});
