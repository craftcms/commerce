<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\fields;

use Craft;
use craft\base\Element;
use craft\base\ElementInterface;
use craft\base\Field;
use craft\commerce\Plugin;
use craft\commerce\web\assets\commercecp\CommerceCpAsset;
use craft\elements\User;

/**
 * Class Customer
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class Customer extends Field
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('commerce', 'Commerce Customer Info');
    }

    /**
     * @inheritdoc
     */
    public static function hasContentColumn(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function getInputHtml($value, ElementInterface $element = null): string
    {
        Craft::$app->getDeprecator()->log('commerceCustomerInfoField', 'The Commerce Customer Info custom field will be removed in Commerce 3.0');

        if (!($element instanceof User)) {
            return '<span style="color: #da5a47">' . Craft::t('commerce', 'Commerce Customer Info field is for user profiles only.') . '</span>';
        }

        Craft::$app->getView()->registerAssetBundle(CommerceCpAsset::class);

        return Craft::$app->getView()->renderTemplate('commerce/_fieldtypes/customer/_input', [
            'customer' => Plugin::getInstance()->getCustomers()->getCustomerByUserId($element->id)
        ]);
    }

    /**
     * @inheritdoc
     */
    public function normalizeValue($value, ElementInterface $element = null)
    {
        /** @var Element|null $element */
        if ($element && $element->id) {
            return Plugin::getInstance()->getCustomers()->getCustomerByUserId($element->id);
        }

        return null;
    }
}
