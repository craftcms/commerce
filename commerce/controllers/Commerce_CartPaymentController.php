<?php
namespace Craft;

/**
 * Class Commerce_PaymentController
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.controllers
 * @since     1.0
 */
class Commerce_CartPaymentController extends Commerce_BaseFrontEndController
{
    /**
     * @throws HttpException
     */
    public function actionPay()
    {
        $this->forward('commerce/payments/pay');
    }
}
