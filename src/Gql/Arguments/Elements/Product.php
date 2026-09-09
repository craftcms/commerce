<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Gql\Arguments\Elements;

use CraftCms\Cms\Gql\Arguments\ElementArguments;
use CraftCms\Cms\Gql\Types\QueryArgument;
use CraftCms\Cms\Support\Facades\Gql;
use CraftCms\Commerce\Gql\Types\Input\Variant;
use CraftCms\Commerce\Product\Elements\Product as ProductElement;
use CraftCms\Commerce\Product\ProductType\ProductTypes;
use GraphQL\Type\Definition\Type;
use Override;

class Product extends ElementArguments
{
    #[Override]
    public static function getArguments(): array
    {
        return array_merge(parent::getArguments(), self::getContentArguments(), [
            'defaultSku' => [
                'name' => 'defaultSku',
                'type' => Type::listOf(QueryArgument::getType()),
                'description' => 'Narrows the query results based on the default SKU on the product.',
            ],
            'defaultPrice' => [
                'name' => 'defaultPrice',
                'type' => Type::listOf(QueryArgument::getType()),
                'description' => 'Narrows the query results based on the default price on the product.',
            ],
            'defaultHeight' => [
                'name' => 'defaultHeight',
                'type' => Type::listOf(QueryArgument::getType()),
                'description' => 'Narrows the query results based on the default height on the product.',
            ],
            'defaultLength' => [
                'name' => 'defaultLength',
                'type' => Type::listOf(QueryArgument::getType()),
                'description' => 'Narrows the query results based on the default length on the product.',
            ],
            'defaultWidth' => [
                'name' => 'defaultWidth',
                'type' => Type::listOf(QueryArgument::getType()),
                'description' => 'Narrows the query results based on the default width on the product.',
            ],
            'defaultWeight' => [
                'name' => 'defaultWeight',
                'type' => Type::listOf(QueryArgument::getType()),
                'description' => 'Narrows the query results based on the default weight on the product.',
            ],
            'editable' => [
                'name' => 'editable',
                'type' => Type::boolean(),
                'description' => 'Whether to only return products that the user has permission to edit.',
            ],
            'type' => [
                'name' => 'type',
                'type' => Type::listOf(Type::string()),
                'description' => 'Narrows the query results based on the product type the products belong to per the product type\'s handles.',
            ],
            'typeId' => [
                'name' => 'typeId',
                'type' => Type::listOf(QueryArgument::getType()),
                'description' => 'Narrows the query results based on the product types the products belong to, per the product type IDs.',
            ],
            'hasVariant' => [
                'name' => 'hasVariant',
                'type' => Variant::getType(),
                'description' => 'Narrows the query results to only products that have certain variants.',
            ],
        ]);
    }

    #[Override]
    public static function getContentArguments(): array
    {
        $productTypeFieldArguments = Gql::getContentArguments(
            contexts: app(ProductTypes::class)->getAllProductTypes(),
            elementType: ProductElement::class,
        );

        return array_merge(parent::getContentArguments(), $productTypeFieldArguments);
    }
}
