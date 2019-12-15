<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\helpers;

use Craft;
use craft\errors\GqlException;
use craft\gql\GqlEntityRegistry;
use craft\models\GqlSchema;
use GraphQL\Type\Definition\UnionType;
use \craft\helpers\Gql as CraftGqlHelper;

/**
 * Class Commerce Gql Helper
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0
 */
class Gql extends CraftGqlHelper
{

    /**
     * Return true if active schema can query categories.
     *
     * @return bool
     */
    public static function canQueryProducts(): bool
    {
        return isset(self::extractAllowedEntitiesFromSchema()['producttypes']);
    }
}
