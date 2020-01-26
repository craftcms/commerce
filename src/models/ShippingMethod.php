<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\models;

use Craft;
use craft\commerce\base\ShippingMethod as BaseShippingMethod;
use craft\commerce\Plugin;
use craft\commerce\records\ShippingMethod as ShippingMethodRecord;
use craft\helpers\UrlHelper;
use craft\validators\UniqueValidator;

/**
 * Shipping method model.
 *
 * @property string $cpEditUrl the control panel URL to manage this method and its rules
 * @property bool $isEnabled whether the shipping method is enabled for listing and selection by customers
 * @property array|ShippingRule[] $shippingRules rules that meet the `ShippingRules` interface
 * @property string $type the type of Shipping Method
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class ShippingMethod extends BaseShippingMethod
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function getType(): string
    {
        return Craft::t('commerce', 'Custom');
    }

    /**
     * @inheritdoc
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @inheritdoc
     */
    public function getName(): string
    {
        return (string)$this->name;
    }

    /**
     * @inheritdoc
     */
    public function getHandle(): string
    {
        return (string)$this->handle;
    }

    /**
     * @inheritdoc
     */
    public function getShippingRules(): array
    {
        return Plugin::getInstance()->getShippingRules()->getAllShippingRulesByShippingMethodId($this->id);
    }

    /**
     * @inheritdoc
     */
    public function getIsEnabled(): bool
    {
        return (bool)$this->enabled;
    }

    /**
     * @inheritdoc
     */
    public function getCpEditUrl(): string
    {
        return UrlHelper::cpUrl('commerce/shipping/shippingmethods/' . $this->id);
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        $rules = parent::rules();

        $rules[] = [['name', 'handle'], 'required'];
        $rules[] = [['name'], UniqueValidator::class, 'targetClass' => ShippingMethodRecord::class];
        $rules[] = [['handle'], UniqueValidator::class, 'targetClass' => ShippingMethodRecord::class];

        return $rules;
    }
}
