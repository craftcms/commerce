<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers;

use CraftCms\Cms\Cp\SiteSwitcher;
use CraftCms\Cms\Http\Controllers\Concerns\RedirectsToShownSource;
use CraftCms\Cms\Http\Requests\ElementIndexRequest;
use CraftCms\Commerce\Http\ViewModels\ProductIndexViewModel;
use CraftCms\Commerce\Product\ProductType\ProductTypes;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

readonly class ProductsController
{
    use RedirectsToShownSource;

    public function productIndex(ElementIndexRequest $request, ?string $productTypeHandle = null): InertiaResponse|RedirectResponse
    {
        $this->requireViewableProductTypes();

        $viewModel = new ProductIndexViewModel($request, $productTypeHandle);

        if ($viewModel->showSiteMenu()) {
            app(SiteSwitcher::class)->scopeToSite();
        }

        return $this->shownSourceRedirect($request, $viewModel, $productTypeHandle !== null && $productTypeHandle !== '')
            ?? Inertia::render('commerce::products/Index', [$viewModel]);
    }

    private function requireViewableProductTypes(): void
    {
        abort_if(empty(app(ProductTypes::class)->getViewableProductTypeIds(true)), 403, 'User is not permitted to view any product types.');
    }
}
