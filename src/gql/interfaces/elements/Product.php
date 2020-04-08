<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\gql\interfaces\elements;

use craft\commerce\elements\Product as ProductElement;
use craft\commerce\gql\types\generators\ProductType;
use craft\gql\GqlEntityRegistry;
use craft\gql\interfaces\Element;
use craft\gql\TypeManager;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\Type;

/**
 * Class Product
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0
 */
class Product extends Element
{
    /**
     * @inheritdoc
     */
    public static function getTypeGenerator(): string
    {
        return ProductType::class;
    }

    /**
     * @inheritdoc
     */
    public static function getType($fields = null): Type
    {
        if ($type = GqlEntityRegistry::getEntity(self::getName())) {
            return $type;
        }

        $type = GqlEntityRegistry::createEntity(self::getName(), new InterfaceType([
            'name' => static::getName(),
            'fields' => self::class . '::getFieldDefinitions',
            'description' => 'This is the interface implemented by all products.',
            'resolveType' => function(ProductElement $value) {
                return $value->getGqlTypeName();
            }
        ]));

        ProductType::generateTypes();

        return $type;
    }

    /**
     * @inheritdoc
     */
    public static function getName(): string
    {
        return 'ProductInterface';
    }

    /**
     * @inheritdoc
     */
    public static function getFieldDefinitions(): array
    {
        return TypeManager::prepareFieldDefinitions(array_merge(parent::getFieldDefinitions(), [
            'availableForPurchase' => [
                'name' => 'availableForPurchase',
                'type' => Type::boolean(),
                'description' => 'If the product is available for purchase.'
            ],
            'defaultPrice' => [
                'name' => 'defaultPrice',
                'type' => Type::float(),
                'description' => 'The price of the default variant for the product.'
            ],
            'productTypeId' => [
                'name' => 'productTypeId',
                'type' => Type::int(),
                'description' => 'The ID of the product type that contains the product.'
            ],
            'productTypeHandle' => [
                'name' => 'productTypeHandle',
                'type' => Type::string(),
                'description' => 'The handle of the product type that contains the product.'
            ],
            'variants' => [
                'name' => 'variants',
                'type' => Type::listOf(Variant::getType()),
                'description' => 'The product’s variants.',
            ]
        ]), self::getName());
    }
}
