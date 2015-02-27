<?php

namespace Craft;

use Omnipay\Common\CreditCard;
use Omnipay\Common\Message\RequestInterface;
use Omnipay\Common\Message\ResponseInterface;

/**
 * Class Market_PaymentService
 * @package craft
 */
class Market_PaymentService extends BaseApplicationComponent
{
    /**
     * @param Market_PaymentFormModel $form
     * @return bool
     * @throws Exception
     */
    public function processPayment(Market_PaymentFormModel $form, &$customError = '')
    {
        if(!$form->validate()) {
            return false;
        }

        $cart = craft()->market_cart->getCart();
        $transaction = craft()->market_transaction->create($cart);
        $transaction->type = Market_TransactionRecord::AUTHORIZE;

        if(!craft()->market_transaction->save($transaction)){
            throw new Exception('Error saving transaction: ' . implode(', ', $transaction->getAllErrors()));
        }

        $gateway = $cart->paymentMethod->getGateway();

        if(!$gateway->supportsAuthorize()) {
            $customError = 'Gateway doesn\'t support authorize';
            craft()->market_transaction->delete($transaction);
            return false;
        }


        $card = $this->createCard($cart, $form);
        $request = $gateway->authorize([
            'card' => $card,
            'amount' => $cart->finalPrice,
            'currency' => 'USD', //TODO refine
            'transactionId' => $transaction->id,
            'clientIp' => craft()->request->getIpAddress(),
            'returnUrl' => $this->getReturnUrl($transaction),
            'cancelUrl' => $this->getCancelUrl($transaction),
        ]);

        try{
            $redirect =  $this->sendPaymentRequest($request, $transaction);
        } catch (\Exception $e) {
            $customError = $e->getMessage();
            craft()->market_transaction->delete($transaction);
            return false;
        }

        craft()->request->redirect($redirect);
        return true;
    }

    public function processPayment1(Market_OrderModel $order, Market_TransactionModel $transaction, Market_PaymentFormModel $paymentForm)
    {

    }

    /**
     * Send a payment request to the gateway, and redirect appropriately
     * @param RequestInterface        $request
     * @param Market_TransactionModel $transaction
     * @return string
     */
    public function sendPaymentRequest(RequestInterface $request, Market_TransactionModel $transaction)
    {
        // try {
        /** @var ResponseInterface $response */
        $response = $request->send();
        $this->updateTransaction($transaction, $response);

        if ($response->isRedirect()) {
            // redirect to off-site gateway
            return $response->redirect();
        }

        // exception required for SagePay Server
//        if (method_exists($response, 'confirm')) {
//            $response->confirm($this->buildReturnUrl($transaction));
//        }
        // } catch (\Exception $e) {
        //     $transaction->status = Market_TransactionModel::FAILED;
        //     //$transaction->message = lang('store.payment.communication_error');
        //     $transaction->message = 'store.payment.communication_error';
        //     $transaction->save();
        // }

//        $gateways_which_call_us_directly = [
//            'AuthorizeNet_SIM',
//            'Realex_Redirect',
//            'SecurePay_DirectPost',
//            'WorldPay',
//        ];
//        if (in_array($transaction->paymentMethod, $gateways_which_call_us_directly)) {
//            // send the customer's browser to our return URL instead of letting the
//            // gateway display the page directly to the customer, otherwise they
//            // end up on our payment failed or order complete page without their
//            // session cookie which obviously won't work
//            $this->redirectForm($this->buildReturnUrl($transaction));
//        }

        if ($response->isSuccessful()) {
            return $this->getReturnUrl($transaction);
        } else {
            craft()->userSession->setError(Craft::t("Couldn't save payment : ".$transaction->message));
            return $this->getCancelUrl($transaction);
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

        if(!craft()->market_transaction->save($transaction)){
            throw new Exception('Error saving transaction: ' . implode(', ', $transaction->getAllErrors()));
        }
    }

    /**
     * @param Market_OrderModel       $order
     * @param Market_PaymentFormModel $paymentForm
     * @return CreditCard
     */
    private function createCard(Market_OrderModel $order, Market_PaymentFormModel $paymentForm)
    {
        $card = new CreditCard;

        $card->setFirstName($paymentForm->firstName);
        $card->setLastName($paymentForm->lastName);
        $card->setNumber($paymentForm->number);
        $card->setExpiryMonth($paymentForm->month);
        $card->setExpiryYear($paymentForm->year);
        $card->setCvv($paymentForm->cvv);

        $billingAddress = $order->billingAddress;
        $card->setBillingAddress1($billingAddress->address1);
        $card->setBillingAddress2($billingAddress->address2);
        $card->setBillingPostcode($billingAddress->zipCode);
        $card->setBillingState($billingAddress->getStateText());
        $card->setBillingCountry($billingAddress->country->name);
        $card->setBillingPhone($billingAddress->phone);

        $shippingAddress = $order->shippingAddress;
        $card->setShippingAddress1($shippingAddress->address1);
        $card->setShippingAddress2($shippingAddress->address2);
        $card->setShippingPostcode($shippingAddress->zipCode);
        $card->setShippingState($shippingAddress->getStateText());
        $card->setShippingCountry($shippingAddress->country->name);
        $card->setShippingPhone($shippingAddress->phone);
        $card->setCompany($shippingAddress->company);

        $user = craft()->userSession->getUser();
        $card->setEmail($user ? $user->email : '');

        return $card;
    }

    /**
     * @param Market_TransactionModel $transaction
     * @return string
     */
    private function getReturnUrl(Market_TransactionModel $transaction)
    {
        return UrlHelper::getActionUrl('market/cartPayment/success', ['id' => $transaction->id, 'hash' => $transaction->hash]);
    }

    /**
     * @param Market_TransactionModel $transaction
     * @return string
     */
    private function getCancelUrl(Market_TransactionModel $transaction)
    {
        return UrlHelper::getActionUrl('market/cartPayment/cancel', ['id' => $transaction->id, 'hash' => $transaction->hash]);
    }
}