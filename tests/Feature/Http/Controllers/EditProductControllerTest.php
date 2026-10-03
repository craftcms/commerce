<?php

declare(strict_types=1);

use CraftCms\Cms\Auth\SessionAuth;
use CraftCms\Cms\Cms;
use CraftCms\Cms\Support\Facades\Drafts;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Url;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Commerce\Product\Elements\Product;
use CraftCms\Commerce\Product\ProductType\ProductTypes;
use CraftCms\Commerce\Product\Variant\Elements\Variant;
use CraftCms\Commerce\Tests\Support\ProductConditionsFixture;
use Illuminate\Support\Collection;
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
});

/** @return array<string, mixed>|null */
function findProductFormControl(array|Collection $nodes, string $component): ?array
{
    foreach ($nodes as $node) {
        if (data_get($node, 'control.component') === $component) {
            return data_get($node, 'control');
        }

        $control = findProductFormControl(data_get($node, 'children', []), $component);
        if ($control !== null) {
            return $control;
        }
    }

    return null;
}

it('renders the product edit page', function() {
    $hoodie = $this->fixture->hoodie;

    get($hoodie->getCpEditUrl())
        ->assertOk()
        ->assertInertia(fn(AssertableInertia $page) => $page
            ->component('elements/Edit', false)
            ->where('elementType', Product::class)
            ->where('canonicalId', $hoodie->id)
            ->where('siteId', $hoodie->siteId)
            ->where('saveUrl', Url::actionUrl('elements/save'))
            ->where('canAutosave', true)
            ->where('crumbs.0.label', 'Commerce')
            ->where('crumbs.1.label', 'Products')
            ->where('crumbs.2.label', $this->fixture->hoodiesType->name)
            ->has('form.values.title')
            ->has('sidebarForm.values.slug')
            ->has('sidebarForm.values.postDate')
            ->has('sidebarForm.values.expiryDate')
            ->missing('sidebarForm.values.parentId')
        );
});

it('manages variants through the product form as a nested element index', function() {
    $hoodie = $this->fixture->hoodie;
    $productType = app(ProductTypes::class)->getProductTypeById($hoodie->typeId);
    $productType->maxVariants = 3;
    expect(app(ProductTypes::class)->saveProductType($productType))->toBeTrue();

    get($hoodie->getCpEditUrl())
        ->assertOk()
        ->assertInertia(fn(AssertableInertia $page) => $page->where('form.nodes', function(Collection $nodes) use ($hoodie) {
            $control = findProductFormControl($nodes, 'craft:nested-elements');

            expect($control)->not->toBeNull()
                ->and($control['path'])->toBe(['variants'])
                ->and($control['props']['viewMode'])->toBe('index')
                ->and($control['props']['manager']['elementType'])->toBe(Variant::class)
                ->and($control['props']['manager']['ownerId'])->toBe($hoodie->id)
                ->and($control['props']['manager']['attribute'])->toBe('variants')
                ->and($control['props']['manager']['canCreate'])->toBeTrue()
                ->and($control['props']['manager']['canPaste'])->toBeTrue()
                ->and($control['props']['manager']['sortable'])->toBeTrue()
                ->and($control['props']['manager']['maxElements'])->toBe(3)
                ->and($control['props']['index'])->toHaveKeys(['indexSettings', 'initial']);

            return true;
        }));
});

it('renders variants with the shared element editor', function() {
    $variant = $this->fixture->hoodie->getDefaultVariant();

    get('/' . Cms::config()->cpTrigger . '/' . Cms::config()->actionTrigger . '/elements/edit?' . http_build_query([
        'elementType' => Variant::class,
        'elementId' => $variant->id,
        'siteId' => $variant->siteId,
    ]))
        ->assertOk()
        ->assertInertia(fn(AssertableInertia $page) => $page
            ->component('elements/Edit', false)
            ->where('elementType', Variant::class)
            ->where('canonicalId', $variant->id)
            ->where('siteId', $variant->siteId)
            ->where('saveUrl', Url::actionUrl('elements/save'))
            ->has('form.values.sku')
            ->has('form.values.basePrice')
            ->has('form.values.inventoryTracked')
            ->has('form.values.allowOutOfStockPurchases')
            ->has('form.values.availableForPurchase')
            ->has('form.values.minQty')
            ->has('form.values.maxQty')
            ->has('form.values.freeShipping')
            ->has('form.values.promotable')
            ->missing('form.values.length')
            ->missing('form.values.weight')
            ->where('sidebarForm.values.taxCategoryId', $variant->taxCategoryId)
            ->where('sidebarForm.values.shippingCategoryId', $variant->shippingCategoryId)
        );
});

it('renders variant dimensions when enabled by the product type', function() {
    $productType = app(ProductTypes::class)->getProductTypeById($this->fixture->hoodie->typeId);
    $productType->hasDimensions = true;
    expect(app(ProductTypes::class)->saveProductType($productType))->toBeTrue();
    $variant = $this->fixture->hoodie->getDefaultVariant();

    get('/' . Cms::config()->cpTrigger . '/' . Cms::config()->actionTrigger . '/elements/edit?' . http_build_query([
        'elementType' => Variant::class,
        'elementId' => $variant->id,
        'siteId' => $variant->siteId,
    ]))
        ->assertOk()
        ->assertInertia(fn(AssertableInertia $page) => $page
            ->has('form.values.length')
            ->has('form.values.width')
            ->has('form.values.height')
            ->has('form.values.weight')
        );
});

it('exposes the variants index and shared variant editor', function() {
    $cpPath = '/' . Cms::config()->cpTrigger . '/commerce/variants';

    get($cpPath)->assertOk();
    get($cpPath . '/' . $this->fixture->hoodie->getDefaultVariant()->id)
        ->assertOk()
        ->assertInertia(fn(AssertableInertia $page) => $page->component('elements/Edit', false));
});

it('requires permission to view the product', function() {
    Gate::before(fn($user, string $ability, array $arguments) => $ability === 'view'
        && ($arguments[0] ?? null) instanceof Product ? false : null);

    get($this->fixture->hoodie->getCpEditUrl())->assertForbidden();
});

it('hides the title field when the product type generates titles', function() {
    $productType = app(ProductTypes::class)->getProductTypeById($this->fixture->hoodie->typeId);
    $productType->hasProductTitleField = false;
    $productType->productTitleFormat = '{sku}';
    expect(app(ProductTypes::class)->saveProductType($productType))->toBeTrue();

    get($this->fixture->hoodie->getCpEditUrl())
        ->assertOk()
        ->assertInertia(fn(AssertableInertia $page) => $page->missing('form.values.title'));
});

it('shows the parent field for structured product types', function() {
    $productType = app(ProductTypes::class)->getProductTypeById($this->fixture->hoodie->typeId);
    $productType->isStructure = true;
    expect(app(ProductTypes::class)->saveProductType($productType))->toBeTrue();

    get($this->fixture->hoodie->getCpEditUrl())
        ->assertOk()
        ->assertInertia(fn(AssertableInertia $page) => $page->has('sidebarForm.values.parentId'));
});

it('offers the product type settings to admins', function() {
    get($this->fixture->hoodie->getCpEditUrl())
        ->assertOk()
        ->assertInertia(fn(AssertableInertia $page) => $page->where(
            'actionMenu',
            fn($items) => collect($items)->contains(fn($item) => ($item['behavior']['url'] ?? null) === Url::cpUrl("commerce/settings/producttypes/{$this->fixture->hoodie->typeId}")),
        ));
});

it('routes the product revisions index to the element revisions screen', function() {
    get($this->fixture->hoodie->getCpRevisionsUrl())
        ->assertBadRequest()
        ->assertSeeText('Element doesn\'t have revisions');
});

it('saves a revision of a versioned product', function() {
    $productType = app(ProductTypes::class)->getProductTypeById($this->fixture->hoodie->typeId);
    $productType->enableVersioning = true;
    expect(app(ProductTypes::class)->saveProductType($productType))->toBeTrue();

    $hoodie = Product::find()->id($this->fixture->hoodie->id)->one();
    $hoodie->title = 'Revised Hoodie';

    expect(Elements::saveElement($hoodie))->toBeTrue()
        ->and(Product::find()->revisionOf($hoodie)->status(null)->count())->toBeGreaterThan(0);
});

it('lets the product type be switched from the breadcrumbs', function() {
    get($this->fixture->hoodie->getCpEditUrl())
        ->assertOk()
        ->assertInertia(fn(AssertableInertia $page) => $page
            ->has('crumbs.2.items', 2)
            ->where('crumbs.2.items.0.selected', true)
            ->where('crumbs.2.items.1.label', $this->fixture->tShirtsType->name)
        );
});

it('authorizes the session to preview the product', function() {
    $productType = app(ProductTypes::class)->getProductTypeById($this->fixture->hoodie->typeId);
    $productType->previewTargets = [['label' => 'Shop', 'urlFormat' => 'shop/{slug}']];
    expect(app(ProductTypes::class)->saveProductType($productType))->toBeTrue();

    get($this->fixture->hoodie->getCpEditUrl())
        ->assertOk()
        ->assertInertia(fn(AssertableInertia $page) => $page->where('previewTargets.0.label', 'Shop'));

    expect(SessionAuth::checkAuthorization("previewElement:{$this->fixture->hoodie->id}"))->toBeTrue();
});

it('shows the user’s provisional draft', function() {
    $user = User::find()->admin(true)->one();
    $draft = Drafts::createDraft($this->fixture->hoodie, $user->id, provisional: true);

    get($this->fixture->hoodie->getCpEditUrl())
        ->assertOk()
        ->assertInertia(fn(AssertableInertia $page) => $page
            ->where('elementId', $draft->id)
            ->where('canonicalId', $this->fixture->hoodie->id)
            ->where('isProvisionalDraft', true)
        );
});

it('saves the product through the element save action', function() {
    $hoodie = $this->fixture->hoodie;

    postJson('/' . Cms::config()->cpTrigger . '/' . Cms::config()->actionTrigger . '/elements/save', [
        'elementType' => Product::class,
        'elementId' => $hoodie->id,
        'siteId' => $hoodie->siteId,
        'title' => 'Renamed Hoodie',
    ])->assertOk();

    $saved = Product::find()->id($hoodie->id)->status(null)->one();

    expect($saved->title)->toBe('Renamed Hoodie')
        ->and($saved->getVariants()->pluck('id')->all())->toBe($hoodie->getVariants()->pluck('id')->all());
});
