<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\ViewModels;

use CraftCms\Cms\Http\ViewModels\ElementEditViewModel;
use CraftCms\Cms\Support\Url;
use Override;

/**
 * The Inertia payload for the transfer edit screen (`commerce::inventory/transfers/Edit`).
 */
class TransferEditViewModel extends ElementEditViewModel
{
    #[Override]
    protected function elementSaveUrl(): string
    {
        return Url::actionUrl('elements/save');
    }
}
