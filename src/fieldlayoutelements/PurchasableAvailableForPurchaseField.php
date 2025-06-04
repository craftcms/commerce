<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\fieldlayoutelements;

use Craft;
use craft\base\ElementInterface;
use craft\commerce\base\Purchasable;
use craft\commerce\helpers\Purchasable as PurchasableHelper;
use craft\fieldlayoutelements\BaseNativeField;
use craft\helpers\Cp;
use yii\base\InvalidArgumentException;

/**
 * PurchasableAvailableForPurchaseField represents an Available for Purchase lightswitch field that is included within a variant field layout designer.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.0.0
 */
class PurchasableAvailableForPurchaseField extends BaseNativeField
{
    /**
     * @inheritdoc
     */
    public bool $mandatory = true;

    /**
     * @inheritdoc
     */
    public string $attribute = 'availableForPurchase';

    /**
     * @var bool Whether the field should be checked by default when creating a new purchasable.
     */
    public bool $defaultAvailableForPurchase = false;

    /**
     * @inheritdoc
     */
    public function __construct(array $config = [])
    {
        unset($config['required']);
        parent::__construct($config);
    }

    /**
     * @inheritdoc
     */
    public function inputHtml(ElementInterface $element = null, bool $static = false): ?string
    {
        if (!$element instanceof Purchasable) {
            throw new InvalidArgumentException(static::class . ' can only be used in purchasable field layouts.');
        }

        return PurchasableHelper::availableForPurchaseInputHtml($element->getIsFresh() ? $this->defaultAvailableForPurchase : $element->availableForPurchase, [
            'disabled' => $static,
        ]);
    }

    public function settingsHtml(): string
    {
        return parent::settingsHtml() . Cp::lightswitchHtml(
            [
                'id' => 'defaultAvailableForPurchase',
                'name' => 'defaultAvailableForPurchase',
                'label' => Craft::t('app', 'Default Value'),
                'on' => $this->defaultAvailableForPurchase,
            ]
        );
    }

    /**
     * @inheritdoc
     */
    protected function defaultLabel(?ElementInterface $element = null, bool $static = false): ?string
    {
        return Craft::t('commerce', 'Available for purchase');
    }
}
