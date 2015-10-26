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
