<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\gql\arguments\elements;

use craft\commerce\gql\types\input\IntFalse;
use craft\commerce\gql\types\input\Product;
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
            'promotable' => [
                'name' => 'promotable',
                'type' => Type::boolean(),
                'description' => 'Whether to only return products that are promotable.',
            ],
            'availableForPurchase' => [
                'name' => 'availableForPurchase',
                'type' => Type::boolean(),
                'description' => 'Whether to only return products that are available to purchase.',
            ],
            'freeShipping' => [
                'name' => 'freeShipping',
                'type' => Type::boolean(),
                'description' => 'Whether to only return products that have free shipping.',
            ],
            'hasProduct' => [
                'name' => 'hasProduct',
                'type' => Product::getType(),
                'description' => 'Narrows the query results to only variants for certain products.',
            ],
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
                'description' => 'Narrows the query results based on the variant’s price.',
            ],
            'promotionalPrice' => [
                'name' => 'promotionalPrice',
                'type' => Type::listOf(QueryArgument::getType()),
                'description' => 'Narrows the query results based on the variant’s promotional price.',
            ],
            'onPromotion' => [
                'name' => 'onPromotion',
                'type' => Type::boolean(),
                'description' => 'Narrows the query results based on whether the variant has a promotional price.',
            ],
            'forCustomer' => [
                'name' => 'forCustomer',
                'type' => IntFalse::getType(),
                'description' => 'Narrows the pricing query results to only prices related for the specified customer.',
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
            ],
            'width' => [
                'name' => 'width',
                'type' => Type::listOf(QueryArgument::getType()),
                'description' => 'Narrows the query results based on the variant’s width dimension.',
            ],
            'height' => [
                'name' => 'height',
                'type' => Type::listOf(QueryArgument::getType()),
                'description' => 'Narrows the query results based on the variant’s height dimension.',
            ],
            'length' => [
                'name' => 'length',
                'type' => Type::listOf(QueryArgument::getType()),
                'description' => 'Narrows the query results based on the variant’s length dimension.',
            ],
            'weight' => [
                'name' => 'weight',
                'type' => Type::listOf(QueryArgument::getType()),
                'description' => 'Narrows the query results based on the variant’s weight dimension.',
            ],
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
