<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\gql\arguments\elements;

use Craft;
use craft\base\GqlInlineFragmentFieldInterface;
use craft\commerce\elements\Donation as DonationElement;
use craft\gql\base\ElementArguments;

/**
 * Class Donation
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.8.0
 */
class Donation extends ElementArguments
{
    /**
     * @inheritdoc
     */
    public static function getArguments(): array
    {
        return array_merge(parent::getArguments(), self::getContentArguments(), [

        ]);
    }

    /**
     * @inheritdoc
     */
    public static function getContentArguments(): array
    {
        $contentArguments = [];

        foreach (Craft::$app->getFields()->getLayoutByType(DonationElement::class)->getCustomFields() as $contentField) {
            if (!$contentField instanceof GqlInlineFragmentFieldInterface) {
                $contentArguments[$contentField->handle] = $contentField->getContentGqlQueryArgumentType();
            }
        }

        return array_merge(parent::getContentArguments(), $contentArguments);
    }
}
