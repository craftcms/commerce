<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\gql\resolvers\elements;

use craft\commerce\db\Table;
use craft\commerce\elements\Variant as VariantElement;
use craft\commerce\helpers\Gql as GqlHelper;
use craft\elements\db\ElementQuery;
use craft\gql\base\ElementResolver;
use craft\helpers\Db;

/**
 * Class Variant
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.1
 */
class Variant extends ElementResolver
{
    /**
     * @inheritdoc
     */
    public static function prepareQuery(mixed $source, array $arguments, $fieldName = null): mixed
    {
        // If this is the beginning of a resolver chain, start fresh
        if ($source === null) {
            $query = VariantElement::find();
        // If not, get the prepared element query
        } else {
            $query = $source->$fieldName;
        }

        // If it's preloaded, it's preloaded.
        if (!$query instanceof ElementQuery) {
            return $query;
        }

        foreach ($arguments as $key => $value) {
            if (method_exists($query, $key)) {
                $query->$key($value);
            } elseif (property_exists($query, $key)) {
                $query->$key = $value;
            } else {
                // Catch custom field queries
                $query->$key($value);
            }
        }

        $pairs = GqlHelper::extractAllowedEntitiesFromSchema();

        if (!GqlHelper::canQueryProducts()) {
            return [];
        }

        $query->innerJoin(Table::PRODUCTS . ' p', '[[p.id]] = [[commerce_variants.primaryOwnerId]]');
        $query->andWhere(['in', '[[p.typeId]]', array_values(Db::idsByUids(Table::PRODUCTTYPES, $pairs['productTypes']))]);

        return $query;
    }
}
