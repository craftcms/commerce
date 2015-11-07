<?php

namespace Commerce\Interfaces;

/**
 * Interface ShippingMethod
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://craftcommerce.com/license Craft Commerce License Agreement
 * @see       http://craftcommerce.com
 * @package   Commerce\Interfaces
 * @since     1.0
 */
interface ShippingMethod
{
    /**
     * Returns the type of Shipping Method this is
     * @return string
     */
    public function getType();

    /**
     * Returns the ID of this Shipping Method, if it is managed by Craft Commerce.
     * @return int|null The shipping method ID, or null if it is not managed by Craft Commerce
     */
    public function getId();

    /**
     * Returns the name of this Shipping Method
     * @return string
     */
    public function getName();

    /**
     * Returns the unique handle of this Shipping Method
     * @return string
     */
    public function getHandle();

    /**
     * Returns the control panel URL to manage this method and it's rules.
     * An empty string will result in no link.
     *
     * @return string
     */
    public function getCpEditUrl();

    /**
     *
     * @return \Commerce\Interfaces\ShippingRules[]
     */
    public function getRules();

    /**
     * Is this shipping method enabled for listing and selection
     *
     * @return bool
     */
    public function getIsEnabled();
}
