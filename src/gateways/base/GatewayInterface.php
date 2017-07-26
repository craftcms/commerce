<?php

namespace craft\commerce\gateways\base;

use craft\base\SavableComponentInterface;

/**
 * VolumeInterface defines the common interface to be implemented by volume classes.
 *
 * A class implementing this interface should also use [[SavableComponentTrait]] and [[VolumeTrait]].
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since  3.0
 */
interface GatewayInterface extends SavableComponentInterface
{
    // Public Methods
    // =========================================================================

    public function purchase();
    public function authorize();
    public function refund();
    public function capture();
    public function supportsRefund();
}
