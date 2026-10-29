<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\gql\mutations;

use Craft;
use craft\commerce\gql\arguments\mutations\Product as ProductMutationArguments;
use craft\commerce\gql\arguments\mutations\Variant as VariantMutationArguments;
use craft\commerce\gql\resolvers\mutations\Product as ProductMutationResolver;
use craft\commerce\gql\resolvers\mutations\Variant as VariantMutationResolver;
use craft\commerce\gql\types\generators\ProductType as ProductTypeGenerator;
use craft\commerce\gql\types\generators\VariantType as VariantTypeGenerator;
use craft\commerce\models\ProductType;
use craft\commerce\Plugin;
use craft\gql\base\ElementMutationResolver;
use craft\gql\base\Mutation;
use craft\gql\GqlEntityRegistry;
use craft\helpers\Gql;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\Type;

/**
 * Class Product
 *
 * Defines mutations for products and their variants.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.8.0
 */
class Product extends Mutation
{
    /**
     * @inheritdoc
     */
    public static function getMutations(): array
    {
        $mutationList = [];
        $createDeleteMutations = false;

        foreach (Plugin::getInstance()->getProductTypes()->getAllProductTypes() as $productType) {
            $scope = "productTypes.$productType->uid";

            if (Gql::canSchema($scope, 'create') || Gql::canSchema($scope, 'save')) {
                $mutation = static::createSaveMutation($productType);
                $mutationList[$mutation['name']] = $mutation;
            }

            // Variants are saved as part of their product, so saving them requires the product type’s “save” action
            if (Gql::canSchema($scope, 'save')) {
                $mutation = static::createSaveVariantMutation($productType);
                $mutationList[$mutation['name']] = $mutation;
            }

            if (!$createDeleteMutations && Gql::canSchema($scope, 'delete')) {
                $createDeleteMutations = true;
            }
        }

        if ($createDeleteMutations) {
            $mutationList['deleteProduct'] = [
                'name' => 'deleteProduct',
                'args' => [
                    'id' => Type::nonNull(Type::int()),
                    'hardDelete' => Type::boolean(),
                ],
                'resolve' => [Craft::createObject(ProductMutationResolver::class), 'deleteProduct'],
                'description' => 'Delete a product.',
                'type' => Type::boolean(),
            ];

            $mutationList['deleteVariant'] = [
                'name' => 'deleteVariant',
                'args' => [
                    'id' => Type::nonNull(Type::int()),
                    'hardDelete' => Type::boolean(),
                ],
                'resolve' => [Craft::createObject(VariantMutationResolver::class), 'deleteVariant'],
                'description' => 'Delete a variant.',
                'type' => Type::boolean(),
            ];
        }

        return $mutationList;
    }

    /**
     * Creates the save mutation for a product type.
     *
     * @param ProductType $productType
     * @return array
     */
    public static function createSaveMutation(ProductType $productType): array
    {
        $arguments = ProductMutationArguments::getArguments();

        if ($productType->isStructure) {
            $arguments['parentId'] = [
                'name' => 'parentId',
                'type' => Type::id(),
                'description' => 'The ID of the product’s parent.',
            ];
        }

        $arguments['variants'] = [
            'name' => 'variants',
            'type' => Type::listOf(Type::nonNull(static::variantInputType($productType))),
            'description' => 'The product’s variants. Variants with an `id` or `uid` are updated, variants without one are created, and any existing variants that aren’t included are deleted.',
        ];

        /** @var ProductMutationResolver $resolver */
        $resolver = Craft::createObject(ProductMutationResolver::class);
        $resolver->setResolutionData('productType', $productType);
        static::prepareResolver($resolver, $productType->getCustomFields());

        return [
            'name' => "save_{$productType->handle}_Product",
            'description' => sprintf('Save a product in the “%s” product type.', $productType->name),
            'args' => array_merge($arguments, $resolver->getResolutionData(ElementMutationResolver::CONTENT_FIELD_KEY)),
            'resolve' => [$resolver, 'saveProduct'],
            'type' => ProductTypeGenerator::generateType($productType),
        ];
    }

    /**
     * Creates the save mutation for a product type’s variants.
     *
     * @param ProductType $productType
     * @return array
     */
    public static function createSaveVariantMutation(ProductType $productType): array
    {
        $arguments = array_merge(VariantMutationArguments::getArguments(), [
            'productId' => [
                'name' => 'productId',
                'type' => Type::id(),
                'description' => 'The ID of the variant’s product. Required for new variants.',
            ],
            'siteId' => [
                'name' => 'siteId',
                'type' => Type::int(),
                'description' => 'Determines which site the variant should be saved to. Defaults to the primary site.',
            ],
        ]);

        /** @var VariantMutationResolver $resolver */
        $resolver = Craft::createObject(VariantMutationResolver::class);
        $resolver->setResolutionData('productType', $productType);
        static::prepareResolver($resolver, $productType->getVariantFieldLayout()->getCustomFields());

        return [
            'name' => "save_{$productType->handle}_Variant",
            'description' => sprintf('Save a variant of a product in the “%s” product type.', $productType->name),
            'args' => array_merge($arguments, $resolver->getResolutionData(ElementMutationResolver::CONTENT_FIELD_KEY)),
            'resolve' => [$resolver, 'saveVariant'],
            'type' => VariantTypeGenerator::generateType($productType),
        ];
    }

    /**
     * Returns the input type for a product type’s variants, used by the product save mutation.
     *
     * @param ProductType $productType
     * @return InputObjectType
     */
    public static function variantInputType(ProductType $productType): InputObjectType
    {
        $typeName = "{$productType->handle}_VariantInput";

        return GqlEntityRegistry::getOrCreate($typeName, function() use ($productType, $typeName) {
            $contentFields = [];
            foreach ($productType->getVariantFieldLayout()->getCustomFields() as $field) {
                $contentFields[$field->handle] = $field->getContentGqlMutationArgumentType();
            }

            return new InputObjectType([
                'name' => $typeName,
                'fields' => fn() => array_merge(VariantMutationArguments::getArguments(), $contentFields),
            ]);
        });
    }
}
