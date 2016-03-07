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

        craft()->deprecator->log('Commerce_CartPaymentController::actionPay():removed', 'You should no longer use controller form action `cartPayment/pay` to pay and complete carts. Controller action `payments/pay` should be used.');

        $this->forward('commerce/payments/pay');
    }

    /**
     * @throws HttpException
     */
    public function actionCompletePayment()
    {
        craft()->deprecator->log('Commerce_CartPaymentController::actionCompletePayment():removed', 'You should no longer use controller form action `cartPayment/completePayment` to complete payments. Controller action `payments/completePayment` should be used.');

        $this->forward('commerce/payments/completePayment');
    }
}
