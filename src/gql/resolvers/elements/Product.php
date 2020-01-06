<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\gql\resolvers\elements;

use craft\commerce\db\Table;
use craft\commerce\elements\Product as ProductElement;
use craft\commerce\helpers\Gql as GqlHelper;
use craft\gql\base\ElementResolver;
use craft\helpers\Db;

/**
 * Class Product
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0
 */
class Product extends ElementResolver
{
    /**
     * @inheritdoc
     */
    public static function prepareQuery($source, array $arguments, $fieldName = null)
    {
        // If this is the beginning of a resolver chain, start fresh
        if ($source === null) {
            $query = ProductElement::find();
            // If not, get the prepared element query
        } else {
            $query = $source->$fieldName;
        }

        // If it's preloaded, it's preloaded.
        if (is_array($query)) {
            return $query;
        }

        foreach ($arguments as $key => $value) {
            $query->$key($value);
        }

        $pairs = GqlHelper::extractAllowedEntitiesFromSchema('read');

        if (!GqlHelper::canQueryProducts()) {
            return [];
        }

        $query->andWhere(['in', 'typeId', array_values(Db::idsByUids(Table::PRODUCTTYPES, $pairs['productTypes']))]);

        return $query;
    }
}
