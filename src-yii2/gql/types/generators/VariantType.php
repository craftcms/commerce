<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\gql\types\generators;

use Craft;
use craft\base\Field;
use craft\commerce\elements\Variant as VariantElement;
use craft\commerce\gql\interfaces\elements\Variant as VariantInterface;
use craft\commerce\gql\types\elements\Variant;
use craft\commerce\helpers\Gql;
use craft\commerce\models\ProductType as ProductTypeModel;
use craft\commerce\Plugin;
use craft\gql\base\GeneratorInterface;
use craft\gql\GqlEntityRegistry;

/**
 * Class VariantType
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.1
 */
class VariantType implements GeneratorInterface
{
    /**
     * @inheritdoc
     */
    public static function generateTypes(mixed $context = null): array
    {
        $productTypes = Plugin::getInstance()->getProductTypes()->getAllProductTypes();
        $gqlTypes = [];

        foreach ($productTypes as $productType) {
            /** @var ProductTypeModel $productType */
            $typeName = VariantElement::gqlTypeNameByContext($productType);
            $requiredContexts = VariantElement::gqlScopesByContext($productType);

            if (!Gql::isSchemaAwareOf($requiredContexts)) {
                continue;
            }

            $layout = $productType->getVariantFieldLayout();
            $contentFields = $layout->getCustomFields();
            $contentFieldGqlTypes = [];

            /** @var Field $contentField */
            foreach ($contentFields as $contentField) {
                $contentFieldGqlTypes[$contentField->handle] = $contentField->getContentGqlType();
            }

            $fields = Craft::$app->getGql()->prepareFieldDefinitions(array_merge(VariantInterface::getFieldDefinitions(), $contentFieldGqlTypes), $typeName);

            // Generate a type for each product type
            $gqlTypes[$typeName] = GqlEntityRegistry::getEntity($typeName) ?: GqlEntityRegistry::createEntity($typeName, new Variant([
                'name' => $typeName,
                'fields' => fn() => $fields,
            ]));
        }

        return $gqlTypes;
    }
}
