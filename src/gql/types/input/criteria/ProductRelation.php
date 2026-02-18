<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\gql\types\input\criteria;

use craft\commerce\gql\arguments\elements\Product as ProductArguments;
use craft\gql\arguments\RelationCriteria;
use craft\gql\GqlEntityRegistry;
use GraphQL\Type\Definition\InputObjectType;

/**
 * Class ProductRelation
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.6.0
 */
class ProductRelation extends InputObjectType
{
    /**
     * @return mixed
     */
    public static function getType(): mixed
    {
        $typeName = 'ProductRelationCriteriaInput';

        return GqlEntityRegistry::getOrCreate($typeName, fn() => new InputObjectType([
            'name' => $typeName,
            'fields' => fn() => [
                ...ProductArguments::getArguments(),
                ...ProductArguments::getContentArguments(),
                ...RelationCriteria::getArguments(),
            ],
        ]));
    }
}
