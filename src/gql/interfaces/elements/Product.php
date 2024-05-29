<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\gql\interfaces\elements;

use Craft;
use craft\commerce\elements\Product as ProductElement;
use craft\commerce\gql\types\generators\ProductType;
use craft\gql\GqlEntityRegistry;
use craft\gql\interfaces\Element;
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
            },
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
        return Craft::$app->getGql()->prepareFieldDefinitions(array_merge(parent::getFieldDefinitions(), [
            'defaultSku' => [
                'name' => 'defaultSku',
                'type' => Type::string(),
                'description' => 'The SKU of the default variant for the product.',
            ],
            'defaultPrice' => [
                'name' => 'defaultPrice',
                'type' => Type::float(),
                'description' => 'The price of the default variant for the product.',
            ],
            'defaultPriceAsCurrency' => [
                'name' => 'defaultPriceAsCurrency',
                'type' => Type::string(),
                'description' => 'The formatted price of the default variant for the product.',
            ],
            'defaultHeight' => [
                'name' => 'defaultHeight',
                'type' => Type::float(),
                'description' => 'The height of the default variant for the product.',
            ],
            'defaultLength' => [
                'name' => 'defaultLength',
                'type' => Type::float(),
                'description' => 'The length of the default variant for the product.',
            ],
            'defaultWidth' => [
                'name' => 'defaultWidth',
                'type' => Type::float(),
                'description' => 'The width of the default variant for the product.',
            ],
            'defaultWeight' => [
                'name' => 'defaultWeight',
                'type' => Type::float(),
                'description' => 'The weight of the default variant for the product.',
            ],
            'defaultVariant' => [
                'name' => 'defaultVariant',
                'type' => Variant::getType(),
                'description' => 'The default variant for the product.',
            ],
            'productTypeId' => [
                'name' => 'productTypeId',
                'type' => Type::int(),
                'description' => 'The ID of the product type that contains the product.',
            ],
            'productTypeHandle' => [
                'name' => 'productTypeHandle',
                'type' => Type::string(),
                'description' => 'The handle of the product type that contains the product.',
            ],
            'url' => [
                'name' => 'url',
                'type' => Type::string(),
                'description' => 'The product’s full URL',
            ],
            'variants' => [
                'name' => 'variants',
                'type' => Type::listOf(Variant::getType()),
                'description' => 'The product’s variants.',
            ],
        ]), self::getName());
    }
}
