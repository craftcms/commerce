<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\ViewModels;

use CraftCms\Cms\Http\ViewModels\ElementEditViewModel;
use CraftCms\Cms\Support\Url;
use Override;

/**
 * The Inertia payload for the product edit screen (`commerce::products/Edit`).
 */
class ProductEditViewModel extends ElementEditViewModel
{
    #[Override]
    protected function elementSaveUrl(): string
    {
        return Url::actionUrl('elements/save');
    }
}
