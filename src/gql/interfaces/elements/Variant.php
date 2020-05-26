<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\gql\interfaces\elements;

use craft\commerce\elements\Variant as VariantElement;
use craft\commerce\gql\types\generators\VariantType;
use craft\gql\GqlEntityRegistry;
use craft\gql\interfaces\Element;
use craft\gql\TypeManager;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\Type;

/**
 * Class Variant
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.1
 */
class Variant extends Element
{
    /**
     * @inheritdoc
     */
    public static function getTypeGenerator(): string
    {
        return VariantType::class;
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
            'description' => 'This is the interface implemented by all variants.',
            'resolveType' => function(VariantElement $value) {
                return $value->getGqlTypeName();
            }
        ]));

        VariantType::generateTypes();

        return $type;
    }

    /**
     * @inheritdoc
     */
    public static function getName(): string
    {
        return 'VariantInterface';
    }

    /**
     * @inheritdoc
     */
    public static function getFieldDefinitions(): array
    {
        return TypeManager::prepareFieldDefinitions(array_merge(parent::getFieldDefinitions(), [
            'isDefault' => [
                'name' => 'isDefault',
                'type' => Type::boolean(),
                'description' => 'If the variant is the default for the product.',
            ],
            'isAvailable' => [
                'name' => 'isAvailable',
                'type' => Type::boolean(),
                'description' => 'If the variant is available to be purchased.',
            ],
            'price' => [
                'name' => 'price',
                'type' => Type::float(),
                'description' => 'The price of the variant.',
            ],
            'sortOrder' => [
                'name' => 'sortOrder',
                'type' => Type::int(),
                'description' => 'The sort order of the variant.',
            ],
            'width' => [
                'name' => 'width',
                'type' => Type::float(),
                'description' => 'The width of the variant.',
            ],
            'height' => [
                'name' => 'height',
                'type' => Type::float(),
                'description' => 'The height of the variant.',
            ],
            'length' => [
                'name' => 'length',
                'type' => Type::float(),
                'description' => 'The length of the variant.',
            ],
            'weight' => [
                'name' => 'weight',
                'type' => Type::float(),
                'description' => 'The weight of the variant.',
            ],
            'stock' => [
                'name' => 'stock',
                'type' => Type::int(),
                'description' => 'The stock level of the variant.',
            ],
            'hasUnlimitedStock' => [
                'name' => 'hasUnlimitedStock',
                'type' => Type::boolean(),
                'description' => 'If the variant has unlimited stock.',
            ],
            'minQty' => [
                'name' => 'minQty',
                'type' => Type::int(),
                'description' => 'The minimum allowed quantity to be purchased.',
            ],
            'maxQty' => [
                'name' => 'maxQty',
                'type' => Type::int(),
                'description' => 'The maximum allowed quantity to be purchased.',
            ],
            'productId' => [
                'name' => 'productId',
                'type' => Type::int(),
                'description' => 'The ID of the variant’s parent product.',
            ],
            'productTitle' => [
                'name' => 'productTitle',
                'type' => Type::string(),
                'description' => 'The title of the variant’s parent product.',
            ],
            'productTypeId' => [
                'name' => 'productTypeId',
                'type' => Type::int(),
                'description' => 'The product type ID of the variant’s parent product.'
            ],
            'sku' => [
                'name' => 'sku',
                'type' => Type::string(),
                'description' => 'The SKU of the variant.',
            ],
        ]), self::getName());
    }
}
