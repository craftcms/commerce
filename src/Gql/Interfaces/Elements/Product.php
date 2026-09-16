<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Gql\Interfaces\Elements;

use CraftCms\Cms\Gql\Gql as GqlService;
use CraftCms\Cms\Gql\GqlEntityRegistry;
use CraftCms\Cms\Gql\GqlHelper;
use CraftCms\Cms\Gql\Interfaces\Element;
use CraftCms\Cms\Support\Facades\Gql;
use CraftCms\Commerce\Gql\Arguments\Elements\Product as ProductArguments;
use CraftCms\Commerce\Gql\Types\Generators\ProductType;
use CraftCms\Commerce\Helpers\Gql as GqlCommerceHelper;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\Type;
use Override;

class Product extends Element
{
    #[Override]
    public static function getTypeGenerator(): string
    {
        return ProductType::class;
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
            'description' => 'This is the interface implemented by all products.',
            'resolveType' => self::class . '::resolveElementTypeName',
        ]));

        ProductType::generateTypes();

        return $type;
    }

    #[Override]
    public static function getName(): string
    {
        return 'ProductInterface';
    }

    #[Override]
    public static function getFieldDefinitions(): array
    {
        $productArguments = ProductArguments::getArguments();
        $structureProductTypeFieldArguments = [...$productArguments];

        foreach (GqlCommerceHelper::getSchemaContainedProductTypes() as $productType) {
            $productTypeArguments = Gql::getFieldLayoutArguments($productType->getProductFieldLayout());
            if ($productType->isStructure) {
                $structureProductTypeFieldArguments += $productTypeArguments;
            }
        }

        return Gql::prepareFieldDefinitions(array_merge(parent::getFieldDefinitions(), [
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
                'description' => 'The product\'s full URL',
            ],
            'variants' => [
                'name' => 'variants',
                'type' => Type::listOf(Variant::getType()),
                'description' => 'The product\'s variants.',
            ],
            'localized' => [
                'name' => 'localized',
                'args' => $productArguments,
                'type' => Type::nonNull(Type::listOf(Type::nonNull(static::getType()))),
                'description' => 'The same element in other locales.',
                'complexity' => GqlHelper::eagerLoadComplexity(),
            ],
            'children' => [
                'name' => 'children',
                'args' => $structureProductTypeFieldArguments,
                'type' => Type::nonNull(Type::listOf(Type::nonNull(static::getType()))),
                'description' => 'The products\'s children, if the product type is a structure. Accepts the same arguments as the `products` query.',
                'complexity' => GqlHelper::relatedArgumentComplexity(GqlService::GRAPHQL_COMPLEXITY_EAGER_LOAD),
            ],
            'descendants' => [
                'name' => 'descendants',
                'args' => $structureProductTypeFieldArguments,
                'type' => Type::nonNull(Type::listOf(Type::nonNull(static::getType()))),
                'description' => 'The products\'s descendants, if the product type is a structure. Accepts the same arguments as the `products` query.',
                'complexity' => GqlHelper::relatedArgumentComplexity(GqlService::GRAPHQL_COMPLEXITY_EAGER_LOAD),
            ],
            'parent' => [
                'name' => 'parent',
                'args' => $structureProductTypeFieldArguments,
                'type' => static::getType(),
                'description' => 'The products\'s parent, if the product type is a structure.',
                'complexity' => GqlHelper::relatedArgumentComplexity(GqlService::GRAPHQL_COMPLEXITY_EAGER_LOAD),
            ],
            'ancestors' => [
                'name' => 'ancestors',
                'args' => $structureProductTypeFieldArguments,
                'type' => Type::nonNull(Type::listOf(Type::nonNull(static::getType()))),
                'description' => 'The products\'s ancestors, if the product type is a structure. Accepts the same arguments as the `products` query.',
                'complexity' => GqlHelper::relatedArgumentComplexity(GqlService::GRAPHQL_COMPLEXITY_EAGER_LOAD),
            ],
        ]), self::getName());
    }
}
