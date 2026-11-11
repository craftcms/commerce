<?php

declare(strict_types=1);

namespace CraftCms\Commerce\DigitalProduct\Gql;

use CraftCms\Cms\Gql\Arguments\ElementArguments;
use CraftCms\Cms\Gql\Types\QueryArgument;
use CraftCms\Cms\Support\Facades\Gql;
use CraftCms\Commerce\DigitalProduct\DigitalProductTypes;
use CraftCms\Commerce\DigitalProduct\Elements\DigitalProduct;
use GraphQL\Type\Definition\Type;
use Override;

class DigitalProductArguments extends ElementArguments
{
    #[Override]
    public static function getArguments(): array
    {
        return array_merge(parent::getArguments(), self::getContentArguments(), [
            'sku' => [
                'name' => 'sku',
                'type' => Type::listOf(QueryArgument::getType()),
                'description' => 'Narrows the query results based on the digital products’ SKUs.',
            ],
            'before' => [
                'name' => 'before',
                'type' => Type::string(),
                'description' => 'Narrows the query results to only digital products that were posted before a certain date.',
            ],
            'after' => [
                'name' => 'after',
                'type' => Type::string(),
                'description' => 'Narrows the query results to only digital products that were posted on or after a certain date.',
            ],
            'type' => [
                'name' => 'type',
                'type' => Type::listOf(Type::string()),
                'description' => 'Narrows the query results based on the digital products’ types, per the types’ handles.',
            ],
            'typeId' => [
                'name' => 'typeId',
                'type' => Type::listOf(QueryArgument::getType()),
                'description' => 'Narrows the query results based on the digital products’ types, per the types’ IDs.',
            ],
        ]);
    }

    #[Override]
    public static function getContentArguments(): array
    {
        return array_merge(parent::getContentArguments(), Gql::getContentArguments(
            contexts: app(DigitalProductTypes::class)->getAllDigitalProductTypes(),
            elementType: DigitalProduct::class,
        ));
    }
}
