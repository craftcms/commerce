<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\helpers;

use craft\commerce\models\ProductType;
use craft\commerce\Plugin;
use craft\helpers\Gql as GqlHelper;
use craft\models\GqlSchema;
use yii\base\InvalidConfigException;

/**
 * Class Commerce Gql
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0
 */
class Gql extends GqlHelper
{
    /**
     * Return true if active schema can query products.
     */
    public static function canQueryProducts(): bool
    {
        $allowedEntities = self::extractAllowedEntitiesFromSchema();
        return isset($allowedEntities['productTypes']);
    }

    /**
     * @param GqlSchema|null $schema
     * @return array|ProductType[]
     * @throws InvalidConfigException
     * @since 5.5.0
     */
    public static function getSchemaContainedProductTypes(?GqlSchema $schema = null): array
    {
        return array_filter(
            Plugin::getInstance()->getProductTypes()->getAllProductTypes(),
            fn(ProductType $productType) => static::isSchemaAwareOf("productTypes.$productType->uid", $schema),
        );
    }
}
