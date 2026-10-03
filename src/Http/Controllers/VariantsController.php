<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers;

use CraftCms\Cms\Cp\SiteSwitcher;
use CraftCms\Cms\Http\Controllers\Concerns\RedirectsToShownSource;
use CraftCms\Cms\Http\Requests\ElementIndexRequest;
use CraftCms\Commerce\Http\ViewModels\VariantIndexViewModel;
use CraftCms\Commerce\Product\ProductType\ProductTypes;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

readonly class VariantsController
{
    use RedirectsToShownSource;

    public function index(ElementIndexRequest $request, ?string $productTypeHandle = null): InertiaResponse|RedirectResponse
    {
        abort_if(empty(app(ProductTypes::class)->getViewableProductTypeIds(true)), 403, 'User is not permitted to view any product types.');

        $viewModel = new VariantIndexViewModel($request, $productTypeHandle);

        if ($viewModel->showSiteMenu()) {
            app(SiteSwitcher::class)->scopeToSite();
        }

        return $this->shownSourceRedirect($request, $viewModel, $productTypeHandle !== null && $productTypeHandle !== '')
            ?? Inertia::render('commerce::variants/Index', [$viewModel]);
    }
}
