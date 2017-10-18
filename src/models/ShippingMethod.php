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
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.models
 * @since     1.0
 */
class ShippingMethod extends Model implements ShippingMethodInterface
{

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
