<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Plugin\Listeners;

use CraftCms\Cms\Gql\Events\GqlArgumentsResolving;
use CraftCms\Commerce\Gql\Types\Input\Criteria\ProductRelation;
use CraftCms\Commerce\Gql\Types\Input\Criteria\VariantRelation;
use GraphQL\Type\Definition\Type;

class GqlArgumentsResolvingListener
{
    public function handle(GqlArgumentsResolving $event): void
    {
        $event->arguments['relatedToProducts'] = [
            'name' => 'relatedToProducts',
            'type' => Type::listOf(ProductRelation::getType()),
            'description' => 'Narrows the query results to elements that relate to a product list defined with this argument.',
        ];
        $event->arguments['relatedToVariants'] = [
            'name' => 'relatedToVariants',
            'type' => Type::listOf(VariantRelation::getType()),
            'description' => 'Narrows the query results to elements that relate to a variant list defined with this argument.',
        ];
    }
}
