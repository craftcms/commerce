<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\plugin;

use craft\commerce\elements\Donation;

/**
 * Trait Variables
 *
 * @property Donation $donation the address service
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
trait Variables
{
    /**
     * Returns the donation purchasable
     *
     * @return Donation The donation purchasable
     * @todo in 4.0 change return to be nullable
     */
    public function getDonation(): Donation
    {
        return Donation::find()->status(null)->one();
    }
}
