<?php

namespace craft\commerce\base;

use craft\commerce\models\payments\OffsitePaymentForm;

/**
 * This is an abstract class to be used by offsite gateways
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2017, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.commerce
 * @since     2.0
 */
trait OffsiteGatewayTrait
{
    /**
     * @inheritdoc
     */
    public function getPaymentFormModel()
    {
        return new OffsitePaymentForm();
    }


}
