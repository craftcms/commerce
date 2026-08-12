<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers;

use craft\commerce\Plugin;
use CraftCms\Cms\View\TemplateMode;

use function CraftCms\Cms\pageTemplate;

readonly class VariantsController
{
    public function index(): string
    {
        abort_if(empty(Plugin::getInstance()->getProductTypes()->getViewableProductTypeIds(true)), 403, 'User is not permitted to view any product types.');

        return pageTemplate('commerce/variants/_index', [], TemplateMode::Cp);
    }
}
