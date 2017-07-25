<?php

namespace craft\commerce\gateways;

use Craft;
use craft\commerce\gateway\models\BasePaymentFormModel;
use craft\commerce\gateway\models\CreditCardPaymentFormModel;
use Omnipay\Common\CreditCard;
use Omnipay\Manual\Message\Request;

/**
 * Class CreditCardGatewayAdapter
 *
 * @package Commerce\Gateways
 *
 */
abstract class CreditCardGatewayAdapter extends BaseGatewayAdapter
{

    public function getPaymentFormModel()
    {
        return new CreditCardPaymentFormModel();
    }

    public function cpPaymentsEnabled()
    {
        return true;
    }

    /**
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

        return Craft::$app->getView()->render('commerce/_gateways/_paymentforms/creditcard', $params);
    }

    /**
     * @param CreditCard                 $card
     * @param CreditCardPaymentFormModel $paymentForm
     *
     * @return void
     */
    public function populateCard(CreditCard $card, CreditCardPaymentFormModel $paymentForm)
    {
        $card->setFirstName($paymentForm->firstName);
        $card->setLastName($paymentForm->lastName);
        $card->setNumber($paymentForm->number);
        $card->setExpiryMonth($paymentForm->month);
        $card->setExpiryYear($paymentForm->year);
        $card->setCvv($paymentForm->cvv);
    }

    /**
     * @param Request $request
     * @param BasePaymentFormModel      $paymentForm
     *
     * @return void
     */
    public function populateRequest(Request $request, BasePaymentFormModel $paymentForm)
    {
        if ($paymentForm->token) {
            $request->setToken($paymentForm->token);
        }
    }
}