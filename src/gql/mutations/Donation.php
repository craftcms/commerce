<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\gql\mutations;

use Craft;
use craft\commerce\gql\resolvers\mutations\Donation as DonationMutationResolver;
use craft\commerce\gql\types\generators\DonationType;
use craft\gql\base\Mutation;
use craft\helpers\Gql;
use GraphQL\Type\Definition\Type;

/**
 * Class Donation
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.8.0
 */
class Donation extends Mutation
{
    /**
     * @inheritdoc
     */
    public static function getMutations(): array
    {
        if (!Gql::canSchema('donations.all', 'save')) {
            return [];
        }

        return [
            'save_Donation' => [
                'name' => 'save_Donation',
                'description' => 'Save the donation.',
                'args' => [
                    'sku' => [
                        'name' => 'sku',
                        'type' => Type::string(),
                        'description' => 'The donation’s SKU.',
                    ],
                    'availableForPurchase' => [
                        'name' => 'availableForPurchase',
                        'type' => Type::boolean(),
                        'description' => 'Whether donations are available for purchase.',
                    ],
                    'enabled' => [
                        'name' => 'enabled',
                        'type' => Type::boolean(),
                        'description' => 'Whether the donation is enabled.',
                    ],
                ],
                'resolve' => [Craft::createObject(DonationMutationResolver::class), 'saveDonation'],
                'type' => DonationType::generateType(null),
            ],
        ];
    }
}
