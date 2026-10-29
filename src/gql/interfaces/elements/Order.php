<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\gql\interfaces\elements;

use Craft;
use craft\commerce\gql\types\generators\OrderType;
use craft\commerce\gql\types\LineItem;
use craft\commerce\gql\types\OrderStatus;
use craft\commerce\gql\types\Store;
use craft\gql\GqlEntityRegistry;
use craft\gql\interfaces\Element;
use craft\gql\interfaces\elements\Address;
use craft\gql\types\DateTime;
use craft\helpers\Gql;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\Type;

/**
 * Class Order
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.8.0
 */
class Order extends Element
{
    /**
     * @inheritdoc
     */
    public static function getTypeGenerator(): string
    {
        return OrderType::class;
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
            'description' => 'This is the interface implemented by all orders.',
            'resolveType' => self::class . '::resolveElementTypeName',
        ]));

        OrderType::generateTypes();

        return $type;
    }

    /**
     * @inheritdoc
     */
    public static function getName(): string
    {
        return 'OrderInterface';
    }

    /**
     * @inheritdoc
     */
    public static function getFieldDefinitions(): array
    {
        return Craft::$app->getGql()->prepareFieldDefinitions(array_merge(parent::getFieldDefinitions(), self::getConditionalFields(), [
            'number' => [
                'name' => 'number',
                'type' => Type::string(),
                'description' => 'The order number.',
            ],
            'shortNumber' => [
                'name' => 'shortNumber',
                'type' => Type::string(),
                'description' => 'The short order number.',
            ],
            'reference' => [
                'name' => 'reference',
                'type' => Type::string(),
                'description' => 'The order reference.',
            ],
            'email' => [
                'name' => 'email',
                'type' => Type::string(),
                'description' => 'The customer’s email address.',
            ],
            'customerId' => [
                'name' => 'customerId',
                'type' => Type::int(),
                'description' => 'The ID of the customer’s user account.',
            ],
            'storeId' => [
                'name' => 'storeId',
                'type' => Type::int(),
                'description' => 'The ID of the store the order belongs to.',
            ],
            'orderStatusId' => [
                'name' => 'orderStatusId',
                'type' => Type::int(),
                'description' => 'The ID of the order’s status.',
            ],
            'isCompleted' => [
                'name' => 'isCompleted',
                'type' => Type::boolean(),
                'description' => 'Whether the order has been completed.',
            ],
            'dateOrdered' => [
                'name' => 'dateOrdered',
                'type' => DateTime::getType(),
                'description' => 'The date the order was completed.',
            ],
            'datePaid' => [
                'name' => 'datePaid',
                'type' => DateTime::getType(),
                'description' => 'The date the order was paid in full.',
            ],
            'dateAuthorized' => [
                'name' => 'dateAuthorized',
                'type' => DateTime::getType(),
                'description' => 'The date the order was authorized in full.',
            ],
            'paidStatus' => [
                'name' => 'paidStatus',
                'type' => Type::string(),
                'description' => 'The order’s paid status (`paid`, `partial`, `unpaid`, or `overPaid`).',
            ],
            'origin' => [
                'name' => 'origin',
                'type' => Type::string(),
                'description' => 'Where the order was created (`web`, `cp`, or `remote`).',
            ],
            'couponCode' => [
                'name' => 'couponCode',
                'type' => Type::string(),
                'description' => 'The coupon code applied to the order.',
            ],
            'currency' => [
                'name' => 'currency',
                'type' => Type::string(),
                'description' => 'The ISO code of the order’s currency.',
            ],
            'paymentCurrency' => [
                'name' => 'paymentCurrency',
                'type' => Type::string(),
                'description' => 'The ISO code of the currency the order is paid in.',
            ],
            'orderLanguage' => [
                'name' => 'orderLanguage',
                'type' => Type::string(),
                'description' => 'The language the order was made in.',
            ],
            'orderSiteId' => [
                'name' => 'orderSiteId',
                'type' => Type::int(),
                'description' => 'The ID of the site the order was made in.',
            ],
            'shippingMethodHandle' => [
                'name' => 'shippingMethodHandle',
                'type' => Type::string(),
                'description' => 'The handle of the order’s shipping method.',
            ],
            'shippingMethodName' => [
                'name' => 'shippingMethodName',
                'type' => Type::string(),
                'description' => 'The name of the order’s shipping method.',
            ],
            'totalQty' => [
                'name' => 'totalQty',
                'type' => Type::int(),
                'description' => 'The total quantity of items in the order.',
            ],
            'totalWeight' => [
                'name' => 'totalWeight',
                'type' => Type::float(),
                'description' => 'The total weight of the order’s items.',
            ],
            'itemSubtotal' => [
                'name' => 'itemSubtotal',
                'type' => Type::float(),
                'description' => 'The sum of the line items’ subtotals.',
            ],
            'itemTotal' => [
                'name' => 'itemTotal',
                'type' => Type::float(),
                'description' => 'The sum of the line items’ totals.',
            ],
            'totalDiscount' => [
                'name' => 'totalDiscount',
                'type' => Type::float(),
                'description' => 'The order’s total discount amount.',
            ],
            'totalShippingCost' => [
                'name' => 'totalShippingCost',
                'type' => Type::float(),
                'description' => 'The order’s total shipping cost.',
            ],
            'totalTax' => [
                'name' => 'totalTax',
                'type' => Type::float(),
                'description' => 'The order’s total tax amount.',
            ],
            'totalTaxIncluded' => [
                'name' => 'totalTaxIncluded',
                'type' => Type::float(),
                'description' => 'The order’s total included tax amount.',
            ],
            'total' => [
                'name' => 'total',
                'type' => Type::float(),
                'description' => 'The order’s total.',
            ],
            'totalPrice' => [
                'name' => 'totalPrice',
                'type' => Type::float(),
                'description' => 'The order’s total price, which is never below zero.',
            ],
            'totalPaid' => [
                'name' => 'totalPaid',
                'type' => Type::float(),
                'description' => 'The total amount paid toward the order.',
            ],
            'lineItems' => [
                'name' => 'lineItems',
                'type' => Type::nonNull(Type::listOf(Type::nonNull(LineItem::getType()))),
                'description' => 'The order’s line items.',
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
        ]), self::getName());
    }

    /**
     * @inheritdoc
     */
    protected static function getConditionalFields(): array
    {
        $fields = [];

        if (Gql::canSchema('settings.stores')) {
            $fields['store'] = [
                'name' => 'store',
                'type' => Type::nonNull(Store::getType()),
                'description' => 'The store the order belongs to.',
            ];
        }

        if (Gql::canSchema('settings.orderstatuses')) {
            $fields['orderStatus'] = [
                'name' => 'orderStatus',
                'type' => OrderStatus::getType(),
                'description' => 'The order’s status.',
            ];
        }

        return $fields;
    }
}
