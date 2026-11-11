<?php

declare(strict_types=1);

use CraftCms\Cms\Cms;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Url;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Commerce\DigitalProduct\Elements\DigitalProduct;
use CraftCms\Commerce\DigitalProduct\Elements\License;
use CraftCms\Commerce\Tests\Support\DigitalProductsFixture;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function() {
    $this->withoutVite();
    actingAs(User::find()->admin(true)->one());
    prioritizeCommerceRoutes();
    $this->cp = '/' . Cms::config()->cpTrigger;
});

it('renders the digital products index for a type', function() {
    $type = DigitalProductsFixture::type();
    DigitalProductsFixture::product($type);

    get("$this->cp/commerce/digital-products/ebooks?viewMode=table")
        ->assertOk()
        ->assertInertia(fn(AssertableInertia $page) => $page
            ->component('commerce::digital-products/Index', false)
            ->where('elementType', DigitalProduct::class)
            ->where('source.key', "productType:$type->uid")
            ->where('creatableTypes.0.newUrl', Url::cpUrl('commerce/digital-products/ebooks/new'))
            ->where('crumbs.0.label', 'Commerce'));
});

it('creates a draft digital product and redirects to its edit screen', function() {
    DigitalProductsFixture::type();

    $response = get("$this->cp/commerce/digital-products/ebooks/new");

    $draft = DigitalProduct::find()->drafts()->status(null)->one();
    expect($draft)->not->toBeNull()
        // The SKU format uses the slug, which an untitled draft doesn't have yet
        ->and($draft->getSku())->not->toStartWith('EBOOK-');
    $response->assertRedirectContains("commerce/digital-products/ebooks/$draft->id");

    get(Url::urlWithParams($draft->getCpEditUrl(), ['draftId' => $draft->draftId]))->assertOk();
});

it('renders the license index and a license edit screen', function() {
    $product = DigitalProductsFixture::product(DigitalProductsFixture::type());
    $license = new License();
    $license->productId = $product->id;
    $license->ownerEmail = 'owner@example.com';
    expect(Elements::saveElement($license))->toBeTrue();

    get("$this->cp/commerce/licenses")
        ->assertOk()
        ->assertInertia(fn(AssertableInertia $page) => $page
            ->component('commerce::licenses/Index', false)
            ->where('elementType', License::class));

    get("$this->cp/commerce/licenses/$license->id")->assertOk();
});

it('renders the digital products settings screens', function() {
    $type = DigitalProductsFixture::type();

    get("$this->cp/commerce/settings/digital-product-types")->assertOk();
    get("$this->cp/commerce/settings/digital-product-types/new")->assertOk();
    get("$this->cp/commerce/settings/digital-product-types/$type->id")->assertOk();
    get("$this->cp/commerce/settings/digital-products")->assertOk();
});

it('saves a digital product type from the settings screen', function() {
    $this->postJson(Url::actionUrl('commerce/digital-product-types/save'), [
        'name' => 'Software',
        'handle' => 'software',
        'skuFormat' => 'SW-{slug}',
        'sites' => [
            \CraftCms\Cms\Support\Facades\Sites::getPrimarySite()->handle => ['enabled' => true, 'uriFormat' => 'software/{slug}', 'template' => 'software/_product'],
        ],
    ])->assertOk();

    $type = app(\CraftCms\Commerce\DigitalProduct\DigitalProductTypes::class)->getDigitalProductTypeByHandle('software');
    expect($type?->skuFormat)->toBe('SW-{slug}')
        ->and(array_values($type->getSiteSettings())[0]->uriFormat)->toBe('software/{slug}');
});

it('returns 404 for a download with an unknown license key or an asset not related to the product', function() {
    $product = DigitalProductsFixture::product(DigitalProductsFixture::type());
    $license = new License();
    $license->productId = $product->id;
    $license->ownerEmail = 'owner@example.com';
    expect(Elements::saveElement($license))->toBeTrue();

    get(Url::actionUrl('commerce/digital-products/download', ['licenseKey' => 'nope', 'assetId' => 1]))->assertNotFound();
    get(Url::actionUrl('commerce/digital-products/download', ['licenseKey' => $license->licenseKey, 'assetId' => 999999]))->assertNotFound();
});

it('queries digital products with GraphQL using the plugin’s query and scope names', function() {
    $type = DigitalProductsFixture::type();
    $product = DigitalProductsFixture::product($type, 'Field Guide', 15);

    gqlActivateSchema(["digitalProductTypes.$type->uid:read"]);

    $result = graphQL('{ digitalProducts { id title sku price productTypeHandle ... on ebooks_Product { slug } } }');

    expect($result['errors'] ?? null)->toBeNull()
        ->and($result['data']['digitalProducts'])->toBe([[
            'id' => (string)$product->id,
            'title' => 'Field Guide',
            'sku' => 'EBOOK-field-guide',
            'price' => 15.0,
            'productTypeHandle' => 'ebooks',
            'slug' => 'field-guide',
        ]]);

    gqlActivateSchema([]);
    expect(graphQL('{ digitalProducts { id } }')['errors'] ?? [])->not->toBeEmpty();
});
