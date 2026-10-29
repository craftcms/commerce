<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\gql\mutations;

use Craft;
use craft\commerce\elements\Transfer as TransferElement;
use craft\commerce\gql\resolvers\mutations\Transfer as TransferMutationResolver;
use craft\commerce\gql\types\generators\TransferType;
use craft\commerce\gql\types\input\TransferDetail;
use craft\gql\base\ElementMutationResolver;
use craft\gql\base\Mutation;
use craft\gql\base\MutationArguments;
use craft\helpers\Gql;
use GraphQL\Type\Definition\Type;

/**
 * Class Transfer
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.8.0
 */
class Transfer extends Mutation
{
    /**
     * @inheritdoc
     */
    public static function getMutations(): array
    {
        $mutationList = [];

        if (Gql::canSchema('transfers.all', 'create') || Gql::canSchema('transfers.all', 'save')) {
            /** @var TransferMutationResolver $resolver */
            $resolver = Craft::createObject(TransferMutationResolver::class);
            static::prepareResolver($resolver, Craft::$app->getFields()->getLayoutByType(TransferElement::class)->getCustomFields());

            $mutationList['save_Transfer'] = [
                'name' => 'save_Transfer',
                'description' => 'Save an inventory transfer. Only draft transfers’ locations and items can be changed.',
                'args' => array_merge(MutationArguments::getArguments(), [
                    'originLocationId' => [
                        'name' => 'originLocationId',
                        'type' => Type::int(),
                        'description' => 'The ID of the inventory location to transfer the items from.',
                    ],
                    'destinationLocationId' => [
                        'name' => 'destinationLocationId',
                        'type' => Type::int(),
                        'description' => 'The ID of the inventory location to transfer the items to.',
                    ],
                    'details' => [
                        'name' => 'details',
                        'type' => Type::listOf(Type::nonNull(TransferDetail::getType())),
                        'description' => 'The items to transfer. Replaces any existing items.',
                    ],
                    'markAsPending' => [
                        'name' => 'markAsPending',
                        'type' => Type::boolean(),
                        'description' => 'Set to `true` to mark a draft transfer as pending, which updates the locations’ inventory.',
                    ],
                ], $resolver->getResolutionData(ElementMutationResolver::CONTENT_FIELD_KEY)),
                'resolve' => [$resolver, 'saveTransfer'],
                'type' => TransferType::generateType(null),
            ];
        }

        if (Gql::canSchema('transfers.all', 'delete')) {
            $mutationList['deleteTransfer'] = [
                'name' => 'deleteTransfer',
                'args' => [
                    'id' => Type::nonNull(Type::int()),
                    'hardDelete' => Type::boolean(),
                ],
                'resolve' => [Craft::createObject(TransferMutationResolver::class), 'deleteTransfer'],
                'description' => 'Delete a draft inventory transfer.',
                'type' => Type::boolean(),
            ];
        }

        return $mutationList;
    }
}
