<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\gql\arguments\elements;

use Craft;
use craft\commerce\elements\Product as ProductElement;
use craft\commerce\Plugin;
use craft\gql\base\ElementArguments;
use craft\gql\types\QueryArgument;
use GraphQL\Type\Definition\Type;

/**
 * Class Product
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0
 */
class Product extends ElementArguments
{
    /**
     * @inheritdoc
     */
    public static function getArguments(): array
    {
        return array_merge(parent::getArguments(),  self::getContentArguments(), [
            'availableForPurchase' => [
                'name' => 'availableForPurchase',
                'type' => Type::boolean(),
                'description' => 'Whether to only return products that are available to purchase.'
            ],
            'defaultPrice' => [
                'name' => 'defaultPrice',
                'type' => Type::listOf(QueryArgument::getType()),
                'description' => 'Narrows the query results based on the default price on the product.'
            ],
            'editable' => [
                'name' => 'editable',
                'type' => Type::boolean(),
                'description' => 'Whether to only return products that the user has permission to edit.'
            ],
            'type' => [
                'name' => 'type',
                'type' => Type::listOf(Type::string()),
                'description' => 'Narrows the query results based on the product type the products belong to per the product type’s handles.'
            ],
            'typeId' => [
                'name' => 'typeId',
                'type' => Type::listOf(QueryArgument::getType()),
                'description' => 'Narrows the query results based on the product types the products belong to, per the product type IDs.'
            ],
        ]);
    }

    /**
     * @inheritdoc
     * @since 3.1.2
     */
    public static function getContentArguments(): array
    {
        $productTypeFieldArguments = Craft::$app->getGql()->getContentArguments(Plugin::getInstance()->getProductTypes()->getAllProductTypes(), ProductElement::class);

        return array_merge(parent::getContentArguments(), $productTypeFieldArguments);
    }
}
