<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\gql\queries;

use craft\commerce\gql\arguments\elements\Variant as VariantArguments;
use craft\commerce\gql\interfaces\elements\Variant as VariantInterface;
use craft\commerce\gql\resolvers\elements\Variant as VariantResolver;
use craft\commerce\helpers\Gql as GqlHelper;
use craft\gql\base\Query;
use GraphQL\Type\Definition\Type;

/**
 * Class Variant
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.1
 */
class Variant extends Query
{
    /**
     * @inheritdoc
     */
    public static function getQueries($checkToken = true): array
    {
        if ($checkToken && !GqlHelper::canQueryProducts()) {
            return [];
        }

        return [
            'variants' => [
                'type' => Type::listOf(VariantInterface::getType()),
                'args' => VariantArguments::getArguments(),
                'resolve' => VariantResolver::class . '::resolve',
                'description' => 'This query is used to query for variants.'
            ],
            'variant' => [
                'type' => VariantInterface::getType(),
                'args' => VariantArguments::getArguments(),
                'resolve' => VariantResolver::class . '::resolveOne',
                'description' => 'This query is used to query for a variant.'
            ],
        ];
    }
}