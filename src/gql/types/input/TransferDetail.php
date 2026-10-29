<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\gql\types\input;

use craft\gql\GqlEntityRegistry;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\Type;

/**
 * Class TransferDetail
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.8.0
 */
class TransferDetail extends InputObjectType
{
    /**
     * @return mixed
     */
    public static function getType(): mixed
    {
        $typeName = 'TransferDetailInput';

        return GqlEntityRegistry::getOrCreate($typeName, fn() => new InputObjectType([
            'name' => $typeName,
            'fields' => [
                'inventoryItemId' => [
                    'name' => 'inventoryItemId',
                    'type' => Type::int(),
                    'description' => 'The ID of the inventory item to transfer. Either this or `purchasableId` is required.',
                ],
                'purchasableId' => [
                    'name' => 'purchasableId',
                    'type' => Type::int(),
                    'description' => 'The ID of the purchasable whose inventory item should be transferred.',
                ],
                'quantity' => [
                    'name' => 'quantity',
                    'type' => Type::nonNull(Type::int()),
                    'description' => 'The quantity to transfer.',
                ],
            ],
        ]));
    }
}
