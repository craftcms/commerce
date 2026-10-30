<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\gql\interfaces\elements;

use Craft;
use craft\commerce\gql\types\generators\TransferType;
use craft\commerce\gql\types\TransferDetail;
use craft\gql\GqlEntityRegistry;
use craft\gql\interfaces\Element;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\Type;

/**
 * Class Transfer
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.8.0
 */
class Transfer extends Element
{
    /**
     * @inheritdoc
     */
    public static function getTypeGenerator(): string
    {
        return TransferType::class;
    }

    /**
     * @inheritdoc
     */
    public static function getType(): Type
    {
        if ($type = GqlEntityRegistry::getEntity(self::getName())) {
            return $type;
        }

        $type = GqlEntityRegistry::createEntity(self::getName(), new InterfaceType([
            'name' => static::getName(),
            'fields' => self::class . '::getFieldDefinitions',
            'description' => 'This is the interface implemented by all inventory transfers.',
            'resolveType' => self::class . '::resolveElementTypeName',
        ]));

        TransferType::generateTypes();

        return $type;
    }

    /**
     * @inheritdoc
     */
    public static function getName(): string
    {
        return 'TransferInterface';
    }

    /**
     * @inheritdoc
     */
    public static function getFieldDefinitions(): array
    {
        return Craft::$app->getGql()->prepareFieldDefinitions(array_merge(parent::getFieldDefinitions(), [
            'transferStatus' => [
                'name' => 'transferStatus',
                'type' => Type::string(),
                'description' => 'The transfer’s status (`draft`, `pending`, `partial`, or `received`).',
            ],
            'originLocationId' => [
                'name' => 'originLocationId',
                'type' => Type::int(),
                'description' => 'The ID of the inventory location the items are transferred from.',
            ],
            'destinationLocationId' => [
                'name' => 'destinationLocationId',
                'type' => Type::int(),
                'description' => 'The ID of the inventory location the items are transferred to.',
            ],
            'totalQuantity' => [
                'name' => 'totalQuantity',
                'type' => Type::int(),
                'description' => 'The total quantity of items being transferred.',
            ],
            'totalReceived' => [
                'name' => 'totalReceived',
                'type' => Type::int(),
                'description' => 'The total quantity of items received so far.',
            ],
            'details' => [
                'name' => 'details',
                'type' => Type::nonNull(Type::listOf(Type::nonNull(TransferDetail::getType()))),
                'description' => 'The items being transferred.',
            ],
        ]), self::getName());
    }
}
