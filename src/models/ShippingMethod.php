<?php

namespace craft\commerce\models;

use craft\commerce\base\Model;
use craft\commerce\base\ShippingMethodInterface;
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
     * @var bool Required
     */
    public $required;

    /**
     * @var bool Default;
     */
    public $default;

    /**
     * @return string
     */
    public function getType()
    {
        return Craft::t('commerce', 'commerce', 'Custom');
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
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getHandle()
    {
        return $this->handle;
    }

    /**
     * @return \craft\commerce\models\ShippingRule[]
     */
    public function getShippingRules()
    {
        return Plugin::getInstance()->getShippingRules()->getAllShippingRulesByShippingMethodId($this->id);
    }

    /**
     * @return bool
     */
    public function getIsEnabled()
    {
        return $this->enabled;
    }

    /**
     * Not applicable since we link to our own.
     *
     * @return string
     */
    public function getCpEditUrl()
    {
        return UrlHelper::cpUrl('commerce/settings/shippingmethods/'.$this->id);
    }
}
