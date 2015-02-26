<?php

namespace craft;

use Omnipay\Common\CreditCard;
use Omnipay\Common\Message\ResponseInterface;

/**
 * Class Market_PaymentService
 * @package craft
 */
class Market_PaymentService extends BaseApplicationComponent
{
    public function processPayment(Market_OrderModel $order, Market_TransactionModel $transaction, Market_PaymentFormModel $paymentForm)
    {
        $gateway = $order->paymentMethod->getGateway();

        if(!$gateway->supportsAuthorize()) {
            //TODO give detailed error
            return false;
        }

        craft()->market_transaction->save($transaction);

        $card = $this->createCard($order, $paymentForm);
        $request = $gateway->authorize([
            'card' => $card,
            'amount' => $order->finalPrice,
            'transactionId' => $transaction->id,
            'clientIp' => craft()->request->getIpAddress(),
            'returnUrl' => '',
            'cancelUrl' => '',
        ]);

        // try {
        $response = $request->send();
        $this->updateTransaction($transaction, $response);

        if ($transaction->status == Market_TransactionRecord::REDIRECT) {
            // redirect to off-site gateway
            return $response->redirect();
        }

        // exception required for SagePay Server
//        if (method_exists($response, 'confirm')) {
//            $response->confirm($this->buildReturnUrl($transaction));
//        }
        //} catch (\Exception $e) {
        //     $transaction->status = Market_TransactionModel::FAILED;
        //     //$transaction->message = lang('store.payment.communication_error');
        //     $transaction->message = 'store.payment.communication_error';
        //     $transaction->save();
        // }

        $gateways_which_call_us_directly = [
            'AuthorizeNet_SIM',
            'Realex_Redirect',
            'SecurePay_DirectPost',
            'WorldPay',
        ];
        if (in_array($transaction->payment_method, $gateways_which_call_us_directly)) {
            // send the customer's browser to our return URL instead of letting the
            // gateway display the page directly to the customer, otherwise they
            // end up on our payment failed or order complete page without their
            // session cookie which obviously won't work
            $this->redirectForm($this->buildReturnUrl($transaction));
        }

        if ($transaction->status == Market_TransactionModel::SUCCESS) {
            craft()->request->redirect($transaction->order->returnUrl);
        } else {
            craft()->userSession->setError(Craft::t("Couldn't save payment : ".$transaction->message));
            craft()->request->redirect($transaction->order->cancelUrl);
        }
    }

    /**
     * @param Market_TransactionModel $transaction
     * @param ResponseInterface       $response
     * @throws Exception
     */
    private function updateTransaction(Market_TransactionModel &$transaction, ResponseInterface $response)
    {
        if ($response->isSuccessful()) {
            $transaction->status = Market_TransactionRecord::SUCCESS;
        } elseif ($response->isRedirect()) {
            $transaction->status = Market_TransactionRecord::REDIRECT;
        } else {
            $transaction->status = Market_TransactionRecord::FAILED;
        }

        $transaction->reference = $response->getTransactionReference();
        $transaction->message = $response->getMessage();

        craft()->market_transaction->save($transaction);
    }

    /**
     * @param Market_OrderModel       $order
     * @param Market_PaymentFormModel $paymentForm
     * @return CreditCard
     */
    private function createCard(Market_OrderModel $order, Market_PaymentFormModel $paymentForm)
    {
        $card = new CreditCard;

        $card->firstName = $paymentForm->firstName;
        $card->lastName = $paymentForm->lastName;
        $card->number = $paymentForm->number;
        $card->expiryMonth = $paymentForm->month;
        $card->expiryYear = $paymentForm->year;
        $card->cvv = $paymentForm->cvv;

        $billingAddress = $order->billingAddress;
        $card->billingAddress1 = $billingAddress->address1;
        $card->billingAddress2 = $billingAddress->address2;
        $card->billingPostcode = $billingAddress->zipCode;
        $card->billingState = $billingAddress->getStateText();
        $card->billingCountry = $billingAddress->country->name;
        $card->billingPhone = $billingAddress->phone;

        $shippingAddress = $order->shippingAddress;
        $card->shippingAddress1 = $shippingAddress->address1;
        $card->shippingAddress2 = $shippingAddress->address2;
        $card->shippingPostcode = $shippingAddress->zipCode;
        $card->shippingState = $shippingAddress->getStateText();
        $card->shippingCountry = $shippingAddress->country->name;
        $card->shippingPhone = $shippingAddress->phone;
        $card->company = $shippingAddress->company;

        $user = craft()->userSession->getUser();
        $card->email = $user ? $user->email : '';

        return $card;
    }
}