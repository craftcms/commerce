<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Gql\Types\Generators;

use CraftCms\Cms\Gql\Contracts\GeneratorInterface;
use CraftCms\Cms\Gql\Contracts\SingleGeneratorInterface;
use CraftCms\Cms\Gql\GqlEntityRegistry;
use CraftCms\Cms\Gql\Types\Generators\Generator;
use CraftCms\Cms\Gql\Types\ObjectType;
use CraftCms\Cms\Support\Facades\Gql;
use CraftCms\Commerce\Catalog\Elements\Variant as VariantElement;
use CraftCms\Commerce\Catalog\ProductType\ProductTypes;
use CraftCms\Commerce\Gql\Interfaces\Elements\Variant as VariantInterface;
use CraftCms\Commerce\Gql\Types\Elements\Variant;
use CraftCms\Commerce\Helpers\Gql as CommerceGqlHelper;

class VariantType extends Generator implements GeneratorInterface, SingleGeneratorInterface
{
    public static function generateTypes(mixed $context = null): array
    {
        $productTypes = app(ProductTypes::class)->getAllProductTypes();
        $gqlTypes = [];

        foreach ($productTypes as $productType) {
            $requiredContexts = VariantElement::gqlScopesByContext($productType);

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
        $typeName = VariantElement::gqlTypeNameByContext($context);

        return GqlEntityRegistry::getOrCreate($typeName, fn() => new Variant([
            'name' => $typeName,
            'fields' => function() use ($context, $typeName) {
                $contentFieldGqlTypes = self::getContentFields($context->getVariantFieldLayout());
                $fields = array_merge(VariantInterface::getFieldDefinitions(), $contentFieldGqlTypes);

                return Gql::prepareFieldDefinitions($fields, $typeName);
            },
        ]));
    }
}
