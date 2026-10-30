<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\gql\arguments\mutations;

use craft\gql\base\ElementMutationArguments;
use craft\gql\types\DateTime;
use GraphQL\Type\Definition\Type;

/**
 * Class Product
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.8.0
 */
class Product extends ElementMutationArguments
{
    /**
     * @inheritdoc
     */
    public static function getArguments(): array
    {
        return array_merge(parent::getArguments(), [
            'slug' => [
                'name' => 'slug',
                'type' => Type::string(),
                'description' => 'The product’s slug.',
            ],
            'postDate' => [
                'name' => 'postDate',
                'type' => DateTime::getType(),
                'description' => 'When the product should be posted.',
            ],
            'expiryDate' => [
                'name' => 'expiryDate',
                'type' => DateTime::getType(),
                'description' => 'When the product should expire.',
            ],
            'siteId' => [
                'name' => 'siteId',
                'type' => Type::int(),
                'description' => 'Determines which site the product should be saved to. Defaults to the primary site.',
            ],
            'revisionNotes' => [
                'name' => 'revisionNotes',
                'type' => Type::string(),
                'description' => 'Sets the revision notes for the product.',
            ],
        ]);
    }
}
