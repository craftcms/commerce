<?php

declare(strict_types=1);

use CraftCms\Cms\Cms;
use CraftCms\Cms\Support\Facades\Sites;
use CraftCms\Cms\Support\Url;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Commerce\Product\Elements\Product;
use CraftCms\Commerce\Product\ProductType\Data\ProductType;
use CraftCms\Commerce\Product\ProductType\Data\ProductTypeSite;
use CraftCms\Commerce\Product\ProductType\ProductTypes;
use Illuminate\Support\Facades\Gate;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\postJson;

function createProductControllerTestProductType(string $handle, bool $enabledByDefault = true, bool $isStructure = false): ProductType
{
    $siteId = Sites::getPrimarySite()->id;

    $productType = new ProductType();
    $productType->name = ucfirst($handle);
    $productType->handle = $handle;
    $productType->hasVariantTitleField = false;
    $productType->variantTitleFormat = '{product.title}';
    $productType->isStructure = $isStructure;

    $siteSettings = new ProductTypeSite();
    $siteSettings->siteId = $siteId;
    $siteSettings->hasUrls = false;
    $siteSettings->enabledByDefault = $enabledByDefault;
    $productType->setSiteSettings([$siteId => $siteSettings]);

    app(ProductTypes::class)->saveProductType($productType);

    return app(ProductTypes::class)->getProductTypeByHandle($handle);
}

function createProductControllerTestDraft(int $typeId): ?Product
{
    return Product::find()->typeId($typeId)->drafts()->status(null)->orderBy('elements.id desc')->one();
}

beforeEach(function() {
    $this->withoutVite();
    actingAs(User::find()->admin(true)->one());
    prioritizeCommerceRoutes();

    $this->hoodies = createProductControllerTestProductType('hoodies');
    $this->newPath = '/' . Cms::config()->cpTrigger . '/commerce/products/hoodies/new';
});

it('creates an unpublished draft and redirects to its edit screen', function() {
    $response = get($this->newPath);

    $draft = createProductControllerTestDraft($this->hoodies->id);

    expect($draft)->not->toBeNull()
        ->and($draft->getIsUnpublishedDraft())->toBeTrue()
        ->and($draft->siteId)->toBe(Sites::getPrimarySite()->id)
        ->and($draft->postDate)->not->toBeNull()
        ->and($draft->enabled)->toBeTrue();

    $response->assertRedirect(Url::urlWithParams($draft->getCpEditUrl(), ['fresh' => 1]));
});

it('returns the new draft as JSON from the create action', function() {
    postJson(Url::actionUrl('commerce/products/create'), ['productType' => 'hoodies', 'title' => 'Rad Hoodie'])
        ->assertOk()
        ->assertJsonPath('product.title', 'Rad Hoodie')
        ->assertJsonPath('product.slug', 'rad-hoodie');

    expect(createProductControllerTestDraft($this->hoodies->id)->title)->toBe('Rad Hoodie');
});

it('rejects an unknown product type', function() {
    get('/' . Cms::config()->cpTrigger . '/commerce/products/nope/new')->assertBadRequest();
});

it('requires permission to create products in the type', function() {
    Gate::before(fn($user, string $ability, array $arguments) => $ability === 'save'
        && ($arguments[0] ?? null) instanceof Product ? false : null);

    get($this->newPath)->assertForbidden();

    expect(createProductControllerTestDraft($this->hoodies->id))->toBeNull();
});

it('uses the product type’s default status', function() {
    createProductControllerTestProductType('tShirts', enabledByDefault: false);

    get('/' . Cms::config()->cpTrigger . '/commerce/products/tShirts/new');

    expect(createProductControllerTestDraft(app(ProductTypes::class)->getProductTypeByHandle('tShirts')->id)->enabled)->toBeFalse();
});

it('sets the parent on a structured product type', function() {
    $widgets = createProductControllerTestProductType('widgets', isStructure: true);

    $parent = new Product();
    $parent->typeId = $widgets->id;
    $parent->title = 'Parent';
    $parent->siteId = Sites::getPrimarySite()->id;
    CraftCms\Cms\Support\Facades\Elements::saveElement($parent);

    postJson(Url::actionUrl('commerce/products/create'), ['productType' => 'widgets', 'parentId' => $parent->id])->assertOk();

    expect(createProductControllerTestDraft($widgets->id)->getParent()?->id)->toBe($parent->id);
});
