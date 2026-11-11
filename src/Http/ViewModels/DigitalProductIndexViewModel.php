<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\ViewModels;

use CraftCms\Cms\Cp\Data\ActionItem;
use CraftCms\Cms\Http\Requests\ElementIndexRequest;
use CraftCms\Cms\Http\ViewModels\ContentIndexViewModel;
use CraftCms\Cms\Support\Url;
use CraftCms\Commerce\DigitalProduct\Data\DigitalProductType;
use CraftCms\Commerce\DigitalProduct\DigitalProductTypes;
use CraftCms\Commerce\DigitalProduct\Elements\DigitalProduct;
use Override;

use function CraftCms\Cms\t;

/**
 * The Inertia payload for the digital product index (`commerce::digital-products/Index`).
 */
class DigitalProductIndexViewModel extends ContentIndexViewModel
{
    public function __construct(
        ElementIndexRequest $request,
        private readonly ?string $typeHandle = null,
    ) {
        parent::__construct(DigitalProduct::class, $request);
    }

    public function typeHandle(): string
    {
        return $this->typeHandle ?? '';
    }

    #[Override]
    public function indexUrl(): string
    {
        return Url::cpUrl('commerce/digital-products');
    }

    /**
     * The types the user can create digital products in, for the “New digital product” button.
     *
     * @return list<array{id: int, handle: string, name: string, sourceKey: string, siteIds: int[], newUrl: string, newLabel: string}>
     */
    public function creatableTypes(): array
    {
        return collect(app(DigitalProductTypes::class)->getEditableDigitalProductTypes())
            ->map(function(DigitalProductType $type) {
                $name = t($type->name, category: 'site');

                return [
                    'id' => $type->id,
                    'handle' => $type->handle,
                    'name' => $name,
                    'sourceKey' => "productType:$type->uid",
                    'siteIds' => $type->getSiteIds(),
                    'newUrl' => Url::cpUrl("commerce/digital-products/$type->handle/new"),
                    'newLabel' => t('New {type} digital product', ['type' => $name], 'commerce'),
                ];
            })
            ->values()
            ->all();
    }

    public function newLabel(): string
    {
        return t('New digital product', category: 'commerce');
    }

    public function newMenuLabel(): string
    {
        return t('New digital product, choose a type', category: 'commerce');
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
        $uri = DigitalProduct::sourceCpUri($source);

        return $uri === null ? parent::sourceUrl($source) : Url::cpUrl($uri);
    }

    /**
     * Maps the type handle route segment (`commerce/digital-products/{handle}`) to its source key.
     */
    #[Override]
    protected function defaultSourceKey(): ?string
    {
        if ($this->typeHandle === null || $this->typeHandle === '') {
            return null;
        }

        $type = app(DigitalProductTypes::class)->getDigitalProductTypeByHandle($this->typeHandle);

        return $type ? "productType:$type->uid" : null;
    }
}
