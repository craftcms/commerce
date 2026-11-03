<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\gql\queries;

use craft\commerce\gql\arguments\elements\Transfer as TransferArguments;
use craft\commerce\gql\interfaces\elements\Transfer as TransferInterface;
use craft\commerce\gql\resolvers\elements\Transfer as TransferResolver;
use craft\gql\base\Query;
use craft\helpers\Gql as GqlHelper;
use GraphQL\Type\Definition\Type;

/**
 * Class Transfer
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.8.0
 */
class Transfer extends Query
{
    /**
     * @inheritdoc
     */
    public static function getQueries(bool $checkToken = true): array
    {
        if ($checkToken && !GqlHelper::canSchema('transfers.all')) {
            return [];
        }

        return [
            'transfers' => [
                'type' => Type::listOf(TransferInterface::getType()),
                'args' => TransferArguments::getArguments(),
                'resolve' => TransferResolver::class . '::resolve',
                'description' => 'This query is used to query for inventory transfers.',
            ],
            'transferCount' => [
                'type' => Type::nonNull(Type::int()),
                'args' => TransferArguments::getArguments(),
                'resolve' => TransferResolver::class . '::resolveCount',
                'description' => 'This query is used to return the number of inventory transfers.',
            ],
            'transfer' => [
                'type' => TransferInterface::getType(),
                'args' => TransferArguments::getArguments(),
                'resolve' => TransferResolver::class . '::resolveOne',
                'description' => 'This query is used to query for an inventory transfer.',
            ],
        ];
    }
}
