<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\gql\arguments\elements;

use Craft;
use craft\base\GqlInlineFragmentFieldInterface;
use craft\commerce\elements\Order as OrderElement;
use craft\gql\base\ElementArguments;
use craft\gql\types\QueryArgument;
use GraphQL\Type\Definition\Type;

/**
 * Class Order
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.8.0
 */
class Order extends ElementArguments
{
    /**
     * @inheritdoc
     */
    public static function getArguments(): array
    {
        return array_merge(parent::getArguments(), self::getContentArguments(), [
            'number' => [
                'name' => 'number',
                'type' => Type::listOf(Type::string()),
                'description' => 'Narrows the query results based on the order number.',
            ],
            'shortNumber' => [
                'name' => 'shortNumber',
                'type' => Type::listOf(Type::string()),
                'description' => 'Narrows the query results based on the short order number.',
            ],
            'reference' => [
                'name' => 'reference',
                'type' => Type::listOf(Type::string()),
                'description' => 'Narrows the query results based on the order reference.',
            ],
            'email' => [
                'name' => 'email',
                'type' => Type::listOf(Type::string()),
                'description' => 'Narrows the query results based on the customers’ email addresses.',
            ],
            'couponCode' => [
                'name' => 'couponCode',
                'type' => Type::listOf(Type::string()),
                'description' => 'Narrows the query results based on the coupon code applied to the order.',
            ],
            'isCompleted' => [
                'name' => 'isCompleted',
                'type' => Type::boolean(),
                'description' => 'Narrows the query results to only orders that are completed (or not).',
            ],
            'isPaid' => [
                'name' => 'isPaid',
                'type' => Type::boolean(),
                'description' => 'Narrows the query results to only orders that are paid.',
            ],
            'isUnpaid' => [
                'name' => 'isUnpaid',
                'type' => Type::boolean(),
                'description' => 'Narrows the query results to only orders that are not paid.',
            ],
            'hasLineItems' => [
                'name' => 'hasLineItems',
                'type' => Type::boolean(),
                'description' => 'Narrows the query results to only orders that have line items.',
            ],
            'dateOrdered' => [
                'name' => 'dateOrdered',
                'type' => Type::listOf(QueryArgument::getType()),
                'description' => 'Narrows the query results based on the orders’ completion dates.',
            ],
            'datePaid' => [
                'name' => 'datePaid',
                'type' => Type::listOf(QueryArgument::getType()),
                'description' => 'Narrows the query results based on the orders’ paid dates.',
            ],
            'dateAuthorized' => [
                'name' => 'dateAuthorized',
                'type' => Type::listOf(QueryArgument::getType()),
                'description' => 'Narrows the query results based on the orders’ authorized dates.',
            ],
            'orderStatus' => [
                'name' => 'orderStatus',
                'type' => Type::listOf(Type::string()),
                'description' => 'Narrows the query results based on the order statuses, per their handles.',
            ],
            'orderStatusId' => [
                'name' => 'orderStatusId',
                'type' => Type::listOf(QueryArgument::getType()),
                'description' => 'Narrows the query results based on the order statuses, per their IDs.',
            ],
            'origin' => [
                'name' => 'origin',
                'type' => Type::listOf(Type::string()),
                'description' => 'Narrows the query results based on the origin (`web`, `cp`, or `remote`).',
            ],
            'customerId' => [
                'name' => 'customerId',
                'type' => Type::listOf(QueryArgument::getType()),
                'description' => 'Narrows the query results based on the customers, per their user IDs.',
            ],
            'storeId' => [
                'name' => 'storeId',
                'type' => Type::int(),
                'description' => 'Narrows the query results based on the store, per its ID.',
            ],
        ]);
    }

    /**
     * @inheritdoc
     */
    public static function getContentArguments(): array
    {
        $contentArguments = [];

        foreach (Craft::$app->getFields()->getLayoutByType(OrderElement::class)->getCustomFields() as $contentField) {
            if (!$contentField instanceof GqlInlineFragmentFieldInterface) {
                $contentArguments[$contentField->handle] = $contentField->getContentGqlQueryArgumentType();
            }
        }

        return array_merge(parent::getContentArguments(), $contentArguments);
    }
}
