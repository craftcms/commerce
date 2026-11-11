<?php

declare(strict_types=1);

namespace CraftCms\Commerce\DigitalProduct\Gql;

use CraftCms\Cms\Gql\GqlEntityRegistry;
use CraftCms\Cms\Gql\Interfaces\Element;
use CraftCms\Cms\Support\Facades\Gql;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\Type;
use Override;

/**
 * `DigitalProductInterface`, implemented by each digital product type's `{handle}_Product` type.
 */
class DigitalProductInterface extends Element
{
    #[Override]
    public static function getTypeGenerator(): string
    {
        return DigitalProductTypeGenerator::class;
    }

    #[Override]
    public static function getType(): Type
    {
        if ($type = GqlEntityRegistry::getEntity(self::getName())) {
            return $type;
        }

        $type = GqlEntityRegistry::createEntity(self::getName(), new InterfaceType([
            'name' => static::getName(),
            'fields' => self::class . '::getFieldDefinitions',
            'description' => 'This is the interface implemented by all digital products.',
            'resolveType' => self::class . '::resolveElementTypeName',
        ]));

        DigitalProductTypeGenerator::generateTypes();

        return $type;
    }

    #[Override]
    public static function getName(): string
    {
        return 'DigitalProductInterface';
    }

    #[Override]
    public static function getFieldDefinitions(): array
    {
        return Gql::prepareFieldDefinitions(array_merge(parent::getFieldDefinitions(), [
            'price' => [
                'name' => 'price',
                'type' => Type::float(),
                'description' => 'The digital product’s price.',
            ],
            'priceAsCurrency' => [
                'name' => 'priceAsCurrency',
                'type' => Type::string(),
                'description' => 'The digital product’s formatted price.',
            ],
            'sku' => [
                'name' => 'sku',
                'type' => Type::string(),
                'description' => 'The digital product’s SKU.',
            ],
            'postDate' => [
                'name' => 'postDate',
                'type' => Type::string(),
                'description' => 'The digital product’s post date.',
            ],
            'expiryDate' => [
                'name' => 'expiryDate',
                'type' => Type::string(),
                'description' => 'The digital product’s expiry date.',
            ],
            'productTypeId' => [
                'name' => 'productTypeId',
                'type' => Type::int(),
                'description' => 'The ID of the digital product type that contains the product.',
            ],
            'productTypeHandle' => [
                'name' => 'productTypeHandle',
                'type' => Type::string(),
                'description' => 'The handle of the digital product type that contains the product.',
            ],
        ]), self::getName());
    }
}
