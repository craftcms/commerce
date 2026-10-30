<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Plugin\Concerns;

use CraftCms\Cms\Gql\GqlArguments;
use CraftCms\Commerce\Gql\Handlers\HasProduct;
use CraftCms\Commerce\Gql\Handlers\HasVariant;
use CraftCms\Commerce\Gql\Handlers\RelatedProducts;
use CraftCms\Commerce\Gql\Handlers\RelatedVariants;
use CraftCms\Commerce\Plugin;

/**
 * @mixin Plugin
 */
trait HasGql
{
    private function registerGqlArguments(): void
    {
        $arguments = app(GqlArguments::class);
        $arguments->register('hasProduct', HasProduct::class);
        $arguments->register('hasVariant', HasVariant::class);
        $arguments->register('relatedToProducts', RelatedProducts::class);
        $arguments->register('relatedToVariants', RelatedVariants::class);
    }
}
