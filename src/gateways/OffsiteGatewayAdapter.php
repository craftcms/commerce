<?php

namespace craft\commerce\gateways;

use Craft;
use craft\commerce\gateway\models\BasePaymentFormModel;
use craft\commerce\gateway\models\CreditCardPaymentFormModel;
use craft\commerce\gateway\models\OffsitePaymentFormModel;
use Omnipay\Common\CreditCard;
use Omnipay\Manual\Message\Request;

abstract class OffsiteGatewayAdapter extends BaseGatewayAdapter
{
    /**
     * @return bool
     */
    public function requiresCreditCard()
    {
        return false;
    }

    public function cpPaymentsEnabled()
    {
        return true;
    }

    /**
     * @return OffsitePaymentFormModel
     */
    public function getPaymentFormModel()
    {
        return new OffsitePaymentFormModel();
    }

    /**
     * @param array $params
     *
     * @return string
     */
    public function getPaymentFormHtml(array $params)
    {
        $defaults = [
            'paymentMethod' => $this->getPaymentMethod(),
            'paymentForm' => $this->getPaymentMethod()->getPaymentFormModel(),
            'adapter' => $this
        ];

        $params = array_merge($defaults, $params);

        return Craft::$app->getView()->render('commerce/_gateways/_paymentforms/offsite', $params);
    }

    /**
     * @param CreditCard $card
     * @param CreditCardPaymentFormModel $paymentForm
     *
     * @return void
     */
    public function populateCard(CreditCard $card, CreditCardPaymentFormModel $paymentForm)
    {
    }

    /**
     * @param Request $card
     * @param BasePaymentFormModel $paymentForm
     *
     * @return void
     */
    public function populateRequest(Request $request, BasePaymentFormModel $paymentForm)
    {
    }
}
