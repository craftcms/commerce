<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\ViewModels;

use CraftCms\Cms\Cp\Data\ActionItem;
use CraftCms\Cms\Http\Requests\ElementIndexRequest;
use CraftCms\Cms\Http\ViewModels\ContentIndexViewModel;
use CraftCms\Cms\Support\Url;
use CraftCms\Commerce\Product\Elements\Product;
use CraftCms\Commerce\Product\ProductType\Data\ProductType;
use CraftCms\Commerce\Product\ProductType\ProductTypes;
use Illuminate\Support\Facades\Gate;
use Override;

use function CraftCms\Cms\t;

/**
 * The Inertia payload for the product index (`commerce::products/Index`).
 */
class ProductIndexViewModel extends ContentIndexViewModel
{
    public function __construct(
        ElementIndexRequest $request,
        private readonly ?string $productTypeHandle = null,
    ) {
        parent::__construct(Product::class, $request);
    }

    public function productTypeHandle(): string
    {
        return $this->productTypeHandle ?? '';
    }

    /**
     * Public so the page can build its index route from it.
     */
    #[Override]
    public function indexUrl(): string
    {
        return Url::cpUrl('commerce/products');
    }

    /**
     * The product types the user can create products in, for the “New product” button.
     *
     * @return list<array{id: int, handle: string, name: string, sourceKey: string, siteIds: int[], newUrl: string, newLabel: string}>
     */
    public function creatableProductTypes(): array
    {
        return collect(app(ProductTypes::class)->getAllProductTypes())
            ->filter(fn(ProductType $productType) => Gate::allows('save', $this->newProduct($productType)))
            ->map(function(ProductType $productType) {
                $name = t($productType->name, category: 'site');

                return [
                    'id' => $productType->id,
                    'handle' => $productType->handle,
                    'name' => $name,
                    'sourceKey' => "productType:$productType->uid",
                    'siteIds' => $productType->getSiteIds(),
                    'newUrl' => Url::cpUrl("commerce/products/$productType->handle/new"),
                    'newLabel' => t('New {productType} product', ['productType' => $name], 'commerce'),
                ];
            })
            ->values()
            ->all();
    }

    public function newProductLabel(): string
    {
        return t('New product', category: 'commerce');
    }

    public function newProductMenuLabel(): string
    {
        return t('New product, choose a type', category: 'commerce');
    }

    /**
     * @return list<ActionItem|array<string, mixed>>
     */
    #[Override]
    public function crumbs(): array
    {
        return [
            new ActionItem()->label(t('Commerce', category: 'commerce'))->href(Url::cpUrl('commerce')),
            ...parent::crumbs(),
        ];
    }

    /**
     * @param array<string, mixed> $source
     */
    #[Override]
    protected function sourceUrl(array $source): ?string
    {
        $uri = Product::sourceCpUri($source);

        return $uri === null ? parent::sourceUrl($source) : Url::cpUrl($uri);
    }

    /**
     * Maps the product type handle route segment (`commerce/products/{handle}`) to its source key.
     */
    #[Override]
    protected function defaultSourceKey(): ?string
    {
        if ($this->productTypeHandle === null || $this->productTypeHandle === '') {
            return null;
        }

        $productType = app(ProductTypes::class)->getProductTypeByHandle($this->productTypeHandle);

        return $productType ? "productType:$productType->uid" : null;
    }

    private function newProduct(ProductType $productType): Product
    {
        $product = new Product();
        $product->typeId = $productType->id;

        return $product;
    }
}
