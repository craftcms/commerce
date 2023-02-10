<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\models;

use craft\commerce\base\Zone;
use craft\helpers\UrlHelper;

/**
 * Shipping zone model.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 *
 * @property-read string $cpEditUrl
 */
class ShippingAddressZone extends Zone
{
    /**
     * @return string
     */
    public function getCpEditUrl(): string
    {
        return UrlHelper::cpUrl('commerce/store-settings/' . $this->getStore()->handle . '/shippingzones/' . $this->id);
    }
}
