<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\fieldlayoutelements;

use Craft;
use craft\base\ElementInterface;
use craft\elements\Address;
use craft\elements\User;
use craft\fieldlayoutelements\BaseField;
use craft\helpers\Cp;
use yii\base\InvalidArgumentException;

/**
 * Class UserAddressSettings
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.0.0
 */
class UserAddressSettings extends BaseField
{
    /**
     * @inheritdoc
     */
    public function attribute(): string
    {
        return 'commerceSettings';
    }

    /**
     * @inheritdoc
     */
    public function mandatory(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function hasCustomWidth(): bool
    {
        return false;
    }

    protected function useFieldset(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    protected function defaultLabel(ElementInterface $element = null, bool $static = false): ?string
    {
        return Craft::t('commerce', 'Commerce Settings');
    }

    /**
     * @inheritdoc
     */
    protected function inputHtml(?ElementInterface $element = null, bool $static = false): ?string
    {
        if (!$element instanceof Address) {
            throw new InvalidArgumentException('UserAddressSettings can only be used in the address field layout.');
        }

        $owner = $element->getOwner();

        if (!$owner instanceof User) {
            return null;
        }

        return
            Cp::checkboxFieldHtml([
                'checkboxLabel' => Craft::t('commerce', 'Use as the primary billing address'),
                'name' => 'isPrimaryBilling',
            ]) .
            Cp::checkboxFieldHtml([
                'checkboxLabel' => Craft::t('commerce', 'Use as the primary shipping address'),
                'name' => 'isPrimaryShipping',
            ]);
    }
}
