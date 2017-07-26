<?php

namespace craft\commerce\gateways;;

use Craft;
use craft\base\MissingComponentInterface;
use craft\base\MissingComponentTrait;
use craft\commerce\gateway\models\BasePaymentFormModel;
use craft\commerce\gateways\base\BaseGateway;

/**
 * MissingGateway represents a gateway with an invalid class.
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2017, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.commerce
 * @since     2.0
 */
class MissingGateway extends BaseGateway implements MissingComponentInterface
{
    // Traits
    // =========================================================================

    use MissingComponentTrait;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    protected function gateway()
    {
        return null;
    }

    /**
     * @inheritdoc
     */
    protected function getGatewayClassName()
    {
        return null;
    }

    /**
     * @inheritdoc
     */
    public function getPaymentFormHtml($params)
    {
        return null;
    }

    /**
     * @inheritdoc
     */
    public function getPaymentFormModel()
    {
        return null;
    }

    /**
     * @inheritdoc
     */
    public function populateCard($card, $paymentForm)
    {
        return null;
    }

    /**
     * @inheritdoc
     */
    public function populateRequest($request, $form)
    {
        return null;
    }


}
