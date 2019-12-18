<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\helpers;

use Craft;
use craft\helpers\Gql as GqlHelper;
use craft\errors\GqlException;
use craft\gql\GqlEntityRegistry;
use craft\models\GqlSchema;
use GraphQL\Type\Definition\UnionType;

/**
 * Class Commerce Gql
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0
 */
class Gql extends GqlHelper
{
    /**
     * Return true if active schema can query entries.
     *
     * @return bool
     */
    public static function canQueryProducts(): bool
    {
        $allowedEntities = self::extractAllowedEntitiesFromSchema();
        return isset($allowedEntities['productTypes']);
    }
}
