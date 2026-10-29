<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\gql\resolvers\mutations;

use Craft;
use craft\commerce\elements\Donation as DonationElement;
use craft\gql\base\ElementMutationResolver;
use GraphQL\Type\Definition\ResolveInfo;
use Throwable;

/**
 * Class Donation
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.8.0
 */
class Donation extends ElementMutationResolver
{
    /**
     * Saves the donation using the passed arguments.
     *
     * There’s only ever one donation, which is created if it doesn’t exist yet.
     *
     * @param mixed $source
     * @param array $arguments
     * @param mixed $context
     * @param ResolveInfo $resolveInfo
     * @return DonationElement
     * @throws Throwable
     */
    public function saveDonation(mixed $source, array $arguments, mixed $context, ResolveInfo $resolveInfo): DonationElement
    {
        $this->requireSchemaAction('donations.all', 'save');

        $donation = DonationElement::find()->status(null)->one() ?? new DonationElement([
            'siteId' => Craft::$app->getSites()->getPrimarySite()->id,
        ]);

        $donation = $this->populateElementWithData($donation, $arguments, $resolveInfo);
        $donation = $this->saveElement($donation);

        return DonationElement::find()->id($donation->id)->status(null)->one();
    }
}
