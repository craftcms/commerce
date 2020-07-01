<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\gql\types\input;

use craft\commerce\gql\arguments\elements\Variant as VariantArguments;
use craft\gql\GqlEntityRegistry;
use craft\gql\types\QueryArgument;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\Type;

/**
 * Class Variant
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.x
 */
class Variant extends InputObjectType
{
    /**
     * @return bool|mixed
     */
    public static function getType()
    {
        $typeName = 'VariantInput';

        return GqlEntityRegistry::getEntity($typeName) ?: GqlEntityRegistry::createEntity($typeName, new InputObjectType([
            'name' => $typeName,
            'fields' => VariantArguments::getArguments()
        ]));
    }
}