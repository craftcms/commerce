<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Gql\Types\Generators;

use CraftCms\Cms\Gql\Contracts\GeneratorInterface;
use CraftCms\Cms\Gql\Contracts\SingleGeneratorInterface;
use CraftCms\Cms\Gql\GqlEntityRegistry;
use CraftCms\Cms\Gql\Types\Generators\Generator;
use CraftCms\Cms\Gql\Types\ObjectType;
use CraftCms\Cms\Support\Facades\Gql;
use CraftCms\Commerce\Gql\Interfaces\Elements\Product as ProductInterface;
use CraftCms\Commerce\Gql\Types\Elements\Product as ProductTypeElement;
use CraftCms\Commerce\Helpers\Gql as CommerceGqlHelper;
use CraftCms\Commerce\Product\Elements\Product as ProductElement;
use CraftCms\Commerce\Product\ProductType\ProductTypes;

class ProductType extends Generator implements GeneratorInterface, SingleGeneratorInterface
{
    public static function generateTypes(mixed $context = null): array
    {
        $productTypes = app(ProductTypes::class)->getAllProductTypes();
        $gqlTypes = [];

        foreach ($productTypes as $productType) {
            $requiredContexts = ProductElement::gqlScopesByContext($productType);

            if (!CommerceGqlHelper::isSchemaAwareOf($requiredContexts)) {
                continue;
            }

            $type = static::generateType($productType);
            $gqlTypes[$type->name] = $type;
        }

        return $gqlTypes;
    }

    public static function generateType(mixed $context): ObjectType
    {
        $typeName = ProductElement::gqlTypeNameByContext($context);

        return GqlEntityRegistry::getOrCreate($typeName, fn() => new ProductTypeElement([
            'name' => $typeName,
            'fields' => function() use ($context, $typeName) {
                $contentFieldGqlTypes = self::getContentFields($context->getProductFieldLayout());
                $productFields = array_merge(ProductInterface::getFieldDefinitions(), $contentFieldGqlTypes);

                return Gql::prepareFieldDefinitions($productFields, $typeName);
            },
        ]));
    }
}
