<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\gql\types\generators;

use Craft;
use craft\commerce\elements\Product as ProductElement;
use craft\commerce\gql\interfaces\elements\Product as ProductInterface;
use craft\commerce\gql\types\elements\Product as ProductTypeElement;
use craft\commerce\helpers\Gql as CommerceGqlHelper;
use craft\commerce\models\ProductType as ProductTypeModel;
use craft\commerce\Plugin;
use craft\gql\base\GeneratorInterface;
use craft\gql\base\ObjectType;
use craft\gql\base\SingleGeneratorInterface;
use craft\gql\GqlEntityRegistry;

/**
 * Class ProductType
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0
 */
class ProductType implements GeneratorInterface, SingleGeneratorInterface
{
    /**
     * @inheritdoc
     */
    public static function generateTypes(mixed $context = null): array
    {
        $gqlTypes = [];

        foreach (Plugin::getInstance()->getProductTypes()->getAllProductTypes() as $productType) {
            if (CommerceGqlHelper::isSchemaAwareOf(ProductElement::gqlScopesByContext($productType))) {
                $type = static::generateType($productType);
                $gqlTypes[$type->name] = $type;
            }
        }

        return $gqlTypes;
    }

    /**
     * @inheritdoc
     * @since 5.8.0
     */
    public static function generateType(mixed $context): ObjectType
    {
        /** @var ProductTypeModel $context */
        $typeName = ProductElement::gqlTypeNameByContext($context);

        return GqlEntityRegistry::getOrCreate($typeName, fn() => new ProductTypeElement([
            'name' => $typeName,
            'fields' => function() use ($context, $typeName) {
                $contentFieldGqlTypes = [];
                foreach ($context->getCustomFields() as $contentField) {
                    $contentFieldGqlTypes[$contentField->handle] = $contentField->getContentGqlType();
                }

                return Craft::$app->getGql()->prepareFieldDefinitions(array_merge(ProductInterface::getFieldDefinitions(), $contentFieldGqlTypes), $typeName);
            },
        ]));
    }
}
