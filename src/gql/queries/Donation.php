<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\gql\queries;

use craft\commerce\gql\arguments\elements\Donation as DonationArguments;
use craft\commerce\gql\interfaces\elements\Donation as DonationInterface;
use craft\commerce\gql\resolvers\elements\Donation as DonationResolver;
use craft\gql\base\Query;
use craft\helpers\Gql as GqlHelper;

/**
 * Class Donation
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.8.0
 */
class Donation extends Query
{
    /**
     * @inheritdoc
     */
    public static function getQueries(bool $checkToken = true): array
    {
        if ($checkToken && !GqlHelper::canSchema('donations.all')) {
            return [];
        }

        return [
            'donation' => [
                'type' => DonationInterface::getType(),
                'args' => DonationArguments::getArguments(),
                'resolve' => DonationResolver::class . '::resolveOne',
                'description' => 'This query is used to query for the donation.',
            ],
        ];
    }
}
