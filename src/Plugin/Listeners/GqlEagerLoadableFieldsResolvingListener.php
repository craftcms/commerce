<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Plugin\Listeners;

use CraftCms\Cms\Gql\Events\GqlEagerLoadableFieldsResolving;
use CraftCms\Commerce\Product\Fields\Products as ProductsField;
use CraftCms\Commerce\Product\Fields\Variants as VariantsField;

class GqlEagerLoadableFieldsResolvingListener
{
    public function handle(GqlEagerLoadableFieldsResolving $event): void
    {
        $event->fieldList['variants'] = [ProductsField::class];
        $event->fieldList['product'] = [VariantsField::class];
    }
}
