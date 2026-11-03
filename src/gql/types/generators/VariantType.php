<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\gql\types\generators;

use Craft;
use craft\commerce\elements\Variant as VariantElement;
use craft\commerce\gql\interfaces\elements\Variant as VariantInterface;
use craft\commerce\gql\types\elements\Variant;
use craft\commerce\helpers\Gql;
use craft\commerce\models\ProductType as ProductTypeModel;
use craft\commerce\Plugin;
use craft\gql\base\GeneratorInterface;
use craft\gql\base\ObjectType;
use craft\gql\base\SingleGeneratorInterface;
use craft\gql\GqlEntityRegistry;

/**
 * Class VariantType
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.1
 */
class VariantType implements GeneratorInterface, SingleGeneratorInterface
{
    /**
     * @inheritdoc
     */
    public static function generateTypes(mixed $context = null): array
    {
        $gqlTypes = [];

        foreach (Plugin::getInstance()->getProductTypes()->getAllProductTypes() as $productType) {
            if (Gql::isSchemaAwareOf(VariantElement::gqlScopesByContext($productType))) {
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
        $typeName = VariantElement::gqlTypeNameByContext($context);

        return GqlEntityRegistry::getOrCreate($typeName, fn() => new Variant([
            'name' => $typeName,
            'fields' => function() use ($context, $typeName) {
                $contentFieldGqlTypes = [];
                foreach ($context->getVariantFieldLayout()->getCustomFields() as $contentField) {
                    $contentFieldGqlTypes[$contentField->handle] = $contentField->getContentGqlType();
                }

                return Craft::$app->getGql()->prepareFieldDefinitions(array_merge(VariantInterface::getFieldDefinitions(), $contentFieldGqlTypes), $typeName);
            },
        ]));
    }
}
