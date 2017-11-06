<?php

namespace craft\commerce\models;

use Craft;
use craft\commerce\base\Model;
use craft\commerce\base\ShippingMethodInterface;
use craft\commerce\Plugin;
use craft\helpers\UrlHelper;

/**
 * Shipping method model.
 *
 * @property string               $cpEditUrl
 * @property array|ShippingRule[] $shippingRules
 * @property bool                 $isEnabled
 * @property string               $type
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since  2.0
 */
class ShippingMethod extends Model implements ShippingMethodInterface
{
    // Properties
    // =========================================================================

    /**
     * @var int ID
     */
    public $id;

    /**
     * @var string Name
     */
    public $name;

    /**
     * @var string Handle
     */
    public $handle;

    /**
     * @var bool Enabled
     */
    public $enabled;

    // Public Methods
    // =========================================================================

    /**
     * @return string
     */
    public function getType(): string
    {
        return Craft::t('commerce', 'Custom');
    }

    /**
     * @return int|null
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getHandle(): string
    {
        return $this->handle;
    }

    /**
     * @return \craft\commerce\models\ShippingRule[]
     */
    public function getShippingRules(): array
    {
        return Plugin::getInstance()->getShippingRules()->getAllShippingRulesByShippingMethodId($this->id);
    }

    /**
     * @return bool
     */
    public function getIsEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Not applicable since we link to our own.
     *
     * @return string
     */
    public function getCpEditUrl(): string
    {
        return UrlHelper::cpUrl('commerce/settings/shippingmethods/'.$this->id);
    }
}
