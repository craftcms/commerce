<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\gql\arguments\elements;

use Craft;
use craft\base\GqlInlineFragmentFieldInterface;
use craft\commerce\elements\Transfer as TransferElement;
use craft\gql\base\ElementArguments;
use craft\gql\types\QueryArgument;
use GraphQL\Type\Definition\Type;

/**
 * Class Transfer
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.8.0
 */
class Transfer extends ElementArguments
{
    /**
     * @inheritdoc
     */
    public static function getArguments(): array
    {
        return array_merge(parent::getArguments(), self::getContentArguments(), [
            'transferStatus' => [
                'name' => 'transferStatus',
                'type' => Type::listOf(Type::string()),
                'description' => 'Narrows the query results based on the transfers’ statuses (`draft`, `pending`, `partial`, or `received`).',
            ],
            'originLocation' => [
                'name' => 'originLocation',
                'type' => Type::listOf(QueryArgument::getType()),
                'description' => 'Narrows the query results based on the IDs of the inventory locations items are transferred from.',
            ],
            'destinationLocation' => [
                'name' => 'destinationLocation',
                'type' => Type::listOf(QueryArgument::getType()),
                'description' => 'Narrows the query results based on the IDs of the inventory locations items are transferred to.',
            ],
        ]);
    }

    /**
     * @inheritdoc
     */
    public static function getContentArguments(): array
    {
        $contentArguments = [];

        foreach (Craft::$app->getFields()->getLayoutByType(TransferElement::class)->getCustomFields() as $contentField) {
            if (!$contentField instanceof GqlInlineFragmentFieldInterface) {
                $contentArguments[$contentField->handle] = $contentField->getContentGqlQueryArgumentType();
            }
        }

        return array_merge(parent::getContentArguments(), $contentArguments);
    }
}
