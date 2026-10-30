<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\ViewModels;

use CraftCms\Cms\Cp\Data\ActionItem;
use CraftCms\Cms\Http\Requests\ElementIndexRequest;
use CraftCms\Cms\Http\ViewModels\ContentIndexViewModel;
use CraftCms\Cms\Support\Url;
use CraftCms\Commerce\Product\ProductType\ProductTypes;
use CraftCms\Commerce\Product\Variant\Elements\Variant;
use Override;

use function CraftCms\Cms\t;

class VariantIndexViewModel extends ContentIndexViewModel
{
    public function __construct(
        ElementIndexRequest $request,
        private readonly ?string $productTypeHandle = null,
    ) {
        parent::__construct(Variant::class, $request);
    }

    public function productTypeHandle(): string
    {
        return $this->productTypeHandle ?? '';
    }

    #[Override]
    public function indexUrl(): string
    {
        return Url::cpUrl('commerce/variants');
    }

    /** @return list<ActionItem|array<string, mixed>> */
    #[Override]
    public function crumbs(): array
    {
        return [
            new ActionItem()->label(t('Commerce', category: 'commerce'))->href(Url::cpUrl('commerce')),
            ...parent::crumbs(),
        ];
    }

    /** @param array<string, mixed> $source */
    #[Override]
    protected function sourceUrl(array $source): ?string
    {
        $handle = $source['data']['handle'] ?? null;

        return is_string($handle) && $handle !== ''
            ? Url::cpUrl("commerce/variants/$handle")
            : parent::sourceUrl($source);
    }

    #[Override]
    protected function defaultSourceKey(): ?string
    {
        if ($this->productTypeHandle === null || $this->productTypeHandle === '') {
            return null;
        }

        $productType = app(ProductTypes::class)->getProductTypeByHandle($this->productTypeHandle);

        return $productType ? "productType:$productType->uid" : null;
    }
}
