<?php

declare(strict_types=1);

namespace CraftCms\Commerce\DigitalProduct\Gql;

use CraftCms\Cms\Gql\Contracts\GeneratorInterface;
use CraftCms\Cms\Gql\Contracts\SingleGeneratorInterface;
use CraftCms\Cms\Gql\GqlEntityRegistry;
use CraftCms\Cms\Gql\Types\Generators\Generator;
use CraftCms\Cms\Gql\Types\ObjectType;
use CraftCms\Cms\Support\Facades\Gql;
use CraftCms\Commerce\DigitalProduct\Data\DigitalProductType;
use CraftCms\Commerce\DigitalProduct\DigitalProductTypes;
use CraftCms\Commerce\DigitalProduct\Elements\DigitalProduct;

/**
 * Generates a `{handle}_Product` GraphQL type for each digital product type in the schema.
 */
class DigitalProductTypeGenerator extends Generator implements GeneratorInterface, SingleGeneratorInterface
{
    public static function generateTypes(mixed $context = null): array
    {
        $gqlTypes = [];

        foreach (app(DigitalProductTypes::class)->getAllDigitalProductTypes() as $type) {
            if (!DigitalProductGqlHelper::isSchemaAwareOf(DigitalProduct::gqlScopesByContext($type))) {
                continue;
            }

            $gqlType = static::generateType($type);
            $gqlTypes[$gqlType->name] = $gqlType;
        }

        return $gqlTypes;
    }

    /**
     * @param DigitalProductType $context
     */
    public static function generateType(mixed $context): ObjectType
    {
        $typeName = DigitalProduct::gqlTypeNameByContext($context);

        return GqlEntityRegistry::getOrCreate($typeName, fn() => new DigitalProductGqlType([
            'name' => $typeName,
            'fields' => function() use ($context, $typeName) {
                return Gql::prepareFieldDefinitions(array_merge(
                    DigitalProductInterface::getFieldDefinitions(),
                    self::getContentFields($context->getFieldLayout()),
                ), $typeName);
            },
        ]));
    }
}
