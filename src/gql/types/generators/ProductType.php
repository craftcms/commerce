<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\gql\types\generators;

use Craft;
use craft\base\Field;
use craft\commerce\elements\Product as ProductElement;
use craft\commerce\gql\interfaces\elements\Product as ProductInterface;
use craft\commerce\gql\types\elements\Product as ProductTypeElement;
use craft\commerce\helpers\Gql as CommerceGqlHelper;
use craft\commerce\models\ProductType as ProductTypeModel;
use craft\commerce\Plugin;
use craft\gql\base\GeneratorInterface;
use craft\gql\GqlEntityRegistry;

/**
 * Class ProductType
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0
 */
class ProductType implements GeneratorInterface
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
            $typeName = ProductElement::gqlTypeNameByContext($productType);
            $requiredContexts = ProductElement::gqlScopesByContext($productType);

            if (!CommerceGqlHelper::isSchemaAwareOf($requiredContexts)) {
                continue;
            }

            $contentFields = $productType->getCustomFields();
            $contentFieldGqlTypes = [];

            /** @var Field $contentField */
            foreach ($contentFields as $contentField) {
                $contentFieldGqlTypes[$contentField->handle] = $contentField->getContentGqlType();
            }

            $productTypeFields = Craft::$app->getGql()->prepareFieldDefinitions(array_merge(ProductInterface::getFieldDefinitions(), $contentFieldGqlTypes), $typeName);

            // Generate a type for each product type
            $gqlTypes[$typeName] = GqlEntityRegistry::getEntity($typeName) ?: GqlEntityRegistry::createEntity($typeName, new ProductTypeElement([
                'name' => $typeName,
                'fields' => fn() => $productTypeFields,
            ]));
        }

        return $gqlTypes;
    }
}
