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

        craft()->deprecator->log('Commerce_CartPaymentController::actionPay():removed', 'The ”commerce/cartPayment/pay” controller action has been deprecated. Please use ”commerce/payments/pay” instead.');

        $this->forward('commerce/payments/pay');
    }

    /**
     * @throws HttpException
     */
    public function actionCompletePayment()
    {
        craft()->deprecator->log('Commerce_CartPaymentController::actionCompletePayment():removed', 'The “commerce/cartPayment/completePayment” controller action has been deprecated. Please use “commerce/payments/completePayment” instead.');

        $this->forward('commerce/payments/completePayment');
    }
}
