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
 * Class LineItem
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.8.0
 */
class LineItem extends InputObjectType
{
    /**
     * @return mixed
     */
    public static function getType(): mixed
    {
        $typeName = 'LineItemInput';

        return GqlEntityRegistry::getOrCreate($typeName, fn() => new InputObjectType([
            'name' => $typeName,
            'fields' => [
                'id' => [
                    'name' => 'id',
                    'type' => Type::int(),
                    'description' => 'The ID of an existing line item on the order.',
                ],
                'purchasableId' => [
                    'name' => 'purchasableId',
                    'type' => Type::int(),
                    'description' => 'The ID of the purchasable. Required for new line items.',
                ],
                'qty' => [
                    'name' => 'qty',
                    'type' => Type::int(),
                    'description' => 'The quantity. Defaults to 1 for new line items.',
                ],
                'note' => [
                    'name' => 'note',
                    'type' => Type::string(),
                    'description' => 'The customer’s note for the line item.',
                ],
                'privateNote' => [
                    'name' => 'privateNote',
                    'type' => Type::string(),
                    'description' => 'A private note for the line item.',
                ],
                'options' => [
                    'name' => 'options',
                    'type' => Type::string(),
                    'description' => 'The line item’s options, JSON-encoded.',
                ],
            ],
        ]));
    }
}
