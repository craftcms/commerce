<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\ViewModels;

use CraftCms\Cms\Cp\Data\ActionItem;
use CraftCms\Cms\Http\Requests\ElementIndexRequest;
use CraftCms\Cms\Http\ViewModels\ContentIndexViewModel;
use CraftCms\Cms\Support\Url;
use CraftCms\Commerce\License\Elements\License;
use Override;

use function CraftCms\Cms\t;

/**
 * The Inertia payload for the license index (`commerce::licenses/Index`).
 */
class LicenseIndexViewModel extends ContentIndexViewModel
{
    public function __construct(ElementIndexRequest $request)
    {
        parent::__construct(License::class, $request);
    }

    #[Override]
    public function indexUrl(): string
    {
        return Url::cpUrl('commerce/licenses');
    }

    public function newLicenseUrl(): string
    {
        return Url::actionUrl('commerce/licenses/create');
    }

    public function newLicenseLabel(): string
    {
        return t('New license', category: 'commerce');
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
}
