<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers;

use CraftCms\Cms\View\TemplateMode;

use CraftCms\Commerce\Product\ProductType\ProductTypes;
use function CraftCms\Cms\pageTemplate;

readonly class VariantsController
{
    public function index(): string
    {
        abort_if(empty(app(ProductTypes::class)->getViewableProductTypeIds(true)), 403, 'User is not permitted to view any product types.');

        return pageTemplate('commerce/variants/_index', [], TemplateMode::Cp);
    }
}
