<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\gql\mutations;

use Craft;
use craft\commerce\elements\Order as OrderElement;
use craft\commerce\gql\resolvers\mutations\Order as OrderMutationResolver;
use craft\commerce\gql\types\generators\OrderType;
use craft\commerce\gql\types\input\Address;
use craft\commerce\gql\types\input\LineItem;
use craft\commerce\Plugin;
use craft\gql\base\ElementMutationResolver;
use craft\gql\base\Mutation;
use craft\gql\base\MutationArguments;
use craft\helpers\Gql;
use GraphQL\Type\Definition\Type;

/**
 * Class Order
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.8.0
 */
class Order extends Mutation
{
    /**
     * @inheritdoc
     */
    public static function getMutations(): array
    {
        $canSave = false;
        $canDelete = false;

        foreach (Plugin::getInstance()->getStores()->getAllStores() as $store) {
            $scope = "orders.$store->uid";
            $canSave = $canSave || Gql::canSchema($scope, 'create') || Gql::canSchema($scope, 'save');
            $canDelete = $canDelete || Gql::canSchema($scope, 'delete');
        }

        $mutationList = [];

        if ($canSave) {
            /** @var OrderMutationResolver $resolver */
            $resolver = Craft::createObject(OrderMutationResolver::class);
            static::prepareResolver($resolver, Craft::$app->getFields()->getLayoutByType(OrderElement::class)->getCustomFields());

            $mutationList['save_Order'] = [
                'name' => 'save_Order',
                'description' => 'Save an order.',
                'args' => array_merge(self::arguments(), $resolver->getResolutionData(ElementMutationResolver::CONTENT_FIELD_KEY)),
                'resolve' => [$resolver, 'saveOrder'],
                'type' => OrderType::generateType(null),
            ];
        }

        if ($canDelete) {
            $mutationList['deleteOrder'] = [
                'name' => 'deleteOrder',
                'args' => [
                    'id' => Type::nonNull(Type::int()),
                    'hardDelete' => Type::boolean(),
                ],
                'resolve' => [Craft::createObject(OrderMutationResolver::class), 'deleteOrder'],
                'description' => 'Delete an order.',
                'type' => Type::boolean(),
            ];
        }

        return $mutationList;
    }

    /**
     * Returns the arguments for the order save mutation.
     *
     * @return array
     */
    private static function arguments(): array
    {
        return array_merge(MutationArguments::getArguments(), [
            'storeId' => [
                'name' => 'storeId',
                'type' => Type::int(),
                'description' => 'The ID of the store a new order should belong to. Defaults to the primary store.',
            ],
            'customerId' => [
                'name' => 'customerId',
                'type' => Type::int(),
                'description' => 'The ID of the customer’s user account.',
            ],
            'email' => [
                'name' => 'email',
                'type' => Type::string(),
                'description' => 'The customer’s email address. A user account is created for it if one doesn’t exist yet.',
            ],
            'orderStatusId' => [
                'name' => 'orderStatusId',
                'type' => Type::int(),
                'description' => 'The ID of the order’s status.',
            ],
            'orderSiteId' => [
                'name' => 'orderSiteId',
                'type' => Type::int(),
                'description' => 'The ID of the site the order was made in.',
            ],
            'message' => [
                'name' => 'message',
                'type' => Type::string(),
                'description' => 'The order status change message.',
            ],
            'couponCode' => [
                'name' => 'couponCode',
                'type' => Type::string(),
                'description' => 'The coupon code to apply to the order.',
            ],
            'shippingMethodHandle' => [
                'name' => 'shippingMethodHandle',
                'type' => Type::string(),
                'description' => 'The handle of the order’s shipping method.',
            ],
            'recalculationMode' => [
                'name' => 'recalculationMode',
                'type' => Type::string(),
                'description' => 'How the order should be recalculated (`all`, `adjustmentsOnly`, or `none`). Defaults to `all` for carts and `none` for completed orders.',
            ],
            'isCompleted' => [
                'name' => 'isCompleted',
                'type' => Type::boolean(),
                'description' => 'Set to `true` to mark the order as complete.',
            ],
            'lineItems' => [
                'name' => 'lineItems',
                'type' => Type::listOf(Type::nonNull(LineItem::getType())),
                'description' => 'The order’s line items. Line items with an `id` are updated, line items without one are created, and any existing line items that aren’t included are removed.',
            ],
            'billingAddress' => [
                'name' => 'billingAddress',
                'type' => Address::getType(),
                'description' => 'The order’s billing address.',
            ],
            'shippingAddress' => [
                'name' => 'shippingAddress',
                'type' => Address::getType(),
                'description' => 'The order’s shipping address.',
            ],
        ]);
    }
}
