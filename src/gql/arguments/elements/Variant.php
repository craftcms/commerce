<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\gql\arguments\elements;

use craft\commerce\Plugin;
use craft\gql\base\ElementArguments;
use craft\gql\types\QueryArgument;
use GraphQL\Type\Definition\Type;

/**
 * Class Variant
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.1
 */
class Variant extends ElementArguments
{
    /**
     * @inheritdoc
     */
    public static function getArguments(): array
    {
        return array_merge(parent::getArguments(), self::getContentArguments(), [
            'hasSales' => [
                'name' => 'hasSales',
                'type' => Type::boolean(),
                'description' => 'Narrows the query results based on whether the variant has sales applied.',
            ],
            'hasStock' => [
                'name' => 'hasStock',
                'type' => Type::boolean(),
                'description' => 'Narrows the query results based on whether the variant has stock available.',
            ],
            'isDefault' => [
                'name' => 'isDefault',
                'type' => Type::boolean(),
                'description' => 'Narrows the query results based on the variants default status.',
            ],
            'maxQty' => [
                'name' => 'maxQty',
                'type' => Type::listOf(QueryArgument::getType()),
                'description' => 'Narrows the query results based on the variant’s maximum allowed quantity to be purchased.',
            ],
            'minQty' => [
                'name' => 'minQty',
                'type' => Type::listOf(QueryArgument::getType()),
                'description' => 'Narrows the query results based on the variant’s minimum allowed quantity to be purchased.',
            ],
            'price' => [
                'name' => 'price',
                'type' => Type::listOf(QueryArgument::getType()),
                'description' => 'Narrows the query results based on variant price.',
            ],
            'productId' => [
                'name' => 'productId',
                'type' => Type::listOf(QueryArgument::getType()),
                'description' => 'Narrows the query results based on the variant’s product ID.',
            ],
            'sku' => [
                'name' => 'sku',
                'type' => Type::listOf(Type::string()),
                'description' => 'Narrows the query results based on the variant SKU.',
            ],
            'stock' => [
                'name' => 'stock',
                'type' => Type::listOf(QueryArgument::getType()),
                'description' => 'Narrows the query results based on variant stock level.',
            ],
            'typeId' => [
                'name' => 'typeId',
                'type' => Type::listOf(QueryArgument::getType()),
                'description' => 'Narrows the query results based on the variant’s product’s type ID.',
            ]
        ]);
    }

    /**
     * @inheritdoc
     * @since 3.1.2
     */
    public static function getContentArguments(): array
    {
        return array_merge(parent::getContentArguments(), Plugin::getInstance()->getVariants()->getVariantGqlContentArguments());
    }
}
