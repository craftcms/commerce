<?php
namespace Craft;

use Omnipay\Common\CreditCard;
use Omnipay\Common\Message\AbstractRequest;
use Omnipay\Common\Message\ResponseInterface;

/**
 * Payments service.
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://craftcommerce.com/license Craft Commerce License Agreement
 * @see       http://craftcommerce.com
 * @package   craft.plugins.commerce.services
 * @since     1.0
 */
class Commerce_PaymentsService extends BaseApplicationComponent
{
    /**
     * @param Commerce_OrderModel $cart
     * @param Commerce_PaymentFormModel $form
     *
     * @param                         $redirect
     * @param                         $cancelUrl
     * @param string $customError
     *
     * @return bool
     * @throws Exception
     * @throws \Exception
     */
    public function processPayment(
        Commerce_OrderModel $cart,
        Commerce_PaymentFormModel $form,
        $redirect,
        $cancelUrl,
        &$customError = ''
    )
    {
        //saving cancelUrl and redirect to cart
        $cart->returnUrl = craft()->templates->renderObjectTemplate($redirect, $cart);
        $cart->cancelUrl = craft()->templates->renderObjectTemplate($cancelUrl, $cart);
        craft()->commerce_orders->save($cart);


        // Cart could have zero totalPrice and already considered 'paid'. Free carts complete immediately.
        if ($cart->isPaid()) {
            craft()->commerce_orders->complete($cart);
            craft()->request->redirect($cart->returnUrl);
        }

        //validating card
        if ($cart->paymentMethod->requiresCard()) {
            if (!$form->validate()) {
                return false;
            }
        } else {
            $form->attributes = [];
        }

        //choosing default action
        $defaultAction = craft()->commerce_settings->getOption('paymentMethod');
        $defaultAction = ($defaultAction === Commerce_TransactionRecord::PURCHASE) ? $defaultAction : Commerce_TransactionRecord::AUTHORIZE;
        $gateway = $cart->paymentMethod->getGatewayAdapter()->getGateway();

        if ($defaultAction == Commerce_TransactionRecord::AUTHORIZE) {
            if (!$gateway->supportsAuthorize()) {
                $customError = Craft::t("Gateway doesn't support authorize");

                return false;
            }
        } else {
            if (!$gateway->supportsPurchase()) {
                $customError = Craft::t("Gateway doesn't support purchase");

                return false;
            }
        }

        //creating cart, transaction and request
        $transaction = craft()->commerce_transactions->create($cart);
        $transaction->type = $defaultAction;
        $this->saveTransaction($transaction);

        $card = $this->createCard($cart, $form);

        $request = $gateway->$defaultAction($this->buildPaymentRequest($transaction,
            $card));

        try {
            $redirect = $this->sendPaymentRequest($request, $transaction);

            if ($transaction->status == Commerce_TransactionRecord::SUCCESS) {
                craft()->commerce_orders->updateOrderPaidTotal($cart);
            }
        } catch (\Exception $e) {
            $customError = $e->getMessage();
            craft()->commerce_transactions->delete($transaction);

            return false;
        }

        craft()->request->redirect($redirect);

        return true;
    }

    /**
     * Send a payment request to the gateway, and redirect appropriately
     *
     * @param AbstractRequest $request
     * @param Commerce_TransactionModel $transaction
     *
     * @return string
     */
    private function sendPaymentRequest(
        AbstractRequest $request,
        Commerce_TransactionModel $transaction
    )
    {
        try {
            /** @var ResponseInterface $response */
            $response = $request->send();
            $this->updateTransaction($transaction, $response);

            if ($response->isRedirect()) {
                // redirect to off-site gateway
                return $response->redirect();
            }
        }catch(\Exception $e){
            $transaction->status = Commerce_TransactionRecord::FAILED;
            $transaction->message = $e->getMessage();
            $this->saveTransaction($transaction);
        }
        $order = $transaction->order;

        if ($transaction->status == Commerce_TransactionRecord::SUCCESS) {
            return $order->returnUrl;
        } else {
            craft()->userSession->setError(Craft::t("Payment error: " . $transaction->message));

            return $order->cancelUrl;
        }
    }

    /**
     * @param Commerce_TransactionModel $transaction
     *
     * @return Commerce_TransactionModel
     */
    public function captureTransaction(Commerce_TransactionModel $transaction)
    {
        return $this->processCaptureOrRefund($transaction,
            Commerce_TransactionRecord::CAPTURE);
    }

    /**
     * @param Commerce_TransactionModel $transaction
     *
     * @return Commerce_TransactionModel
     */
    public function refundTransaction(Commerce_TransactionModel $transaction)
    {
        return $this->processCaptureOrRefund($transaction,
            Commerce_TransactionRecord::REFUND);
    }

    /**
     * @param Commerce_TransactionModel $parent
     * @param string $action
     *
     * @return Commerce_TransactionModel
     * @throws Exception
     */
    private function processCaptureOrRefund(
        Commerce_TransactionModel $parent,
        $action
    )
    {
        if (!in_array($action, [
            Commerce_TransactionRecord::CAPTURE,
            Commerce_TransactionRecord::REFUND
        ])
        ) {
            throw new Exception('Wrong action: ' . $action);
        }

        $order = $parent->order;
        $child = craft()->commerce_transactions->create($order);
        $child->parentId = $parent->id;
        $child->paymentMethodId = $parent->paymentMethodId;
        $child->type = $action;
        $child->amount = $parent->amount;
        $this->saveTransaction($child);

        $gateway = $parent->paymentMethod->getGatewayAdapter()->getGateway();
        $request = $gateway->$action($this->buildPaymentRequest($child));
        $request->setTransactionReference($parent->reference);

        $order->returnUrl = $order->getCpEditUrl();
        craft()->commerce_orders->save($order);

        try {
            $response = $request->send();
            $this->updateTransaction($child, $response);
        } catch (\Exception $e) {
            $child->status = Commerce_TransactionRecord::FAILED;
            $child->message = $e->getMessage();

            $this->saveTransaction($child);
        }

        return $child;
    }

    /**
     * Process return from off-site payment
     *
     * @param Commerce_TransactionModel $transaction
     *
     * @throws Exception
     */
    public function completePayment(Commerce_TransactionModel $transaction)
    {
        $order = $transaction->order;

        // ignore already processed transactions
        if ($transaction->status != Commerce_TransactionRecord::REDIRECT) {
            if ($transaction->status == Commerce_TransactionRecord::SUCCESS) {
                craft()->request->redirect($order->returnUrl);
            } else {
                craft()->userSession->setError('Payment error: ' . $transaction->message);
                craft()->request->redirect($order->cancelUrl);
            }
        }

        // load payment driver
        $gateway = $transaction->paymentMethod->getGatewayAdapter()->getGateway();

        $action = 'complete' . ucfirst($transaction->type);
        $supportsAction = 'supports' . ucfirst($action);
        if ($gateway->$supportsAction()) {
            // don't send notifyUrl for completePurchase
            $params = $this->buildPaymentRequest($transaction);

            // If MOLLIE, the transactionReference will be theirs
            $name = $transaction->paymentMethod->getGatewayAdapter()->getGateway()->getName();
            if ( $name == 'Mollie_Ideal' || $name == 'Mollie' || $name == 'SagePay_Server') {
                $params['transactionReference'] = $transaction->reference;
            }

            unset($params['notifyUrl']);

            $request = $gateway->$action($params);
            $redirect = $this->sendPaymentRequest($request, $transaction);

            if ($transaction->status == Commerce_TransactionRecord::SUCCESS) {
                craft()->commerce_orders->updateOrderPaidTotal($order);
            }
            craft()->request->redirect($redirect);
        } else {
            throw new Exception('Payment return not supported');
        }
    }

    /**
     * @param Commerce_TransactionModel $transaction
     * @param ResponseInterface $response
     *
     * @throws Exception
     */
    private function updateTransaction(
        Commerce_TransactionModel $transaction,
        ResponseInterface $response
    )
    {
        if ($response->isSuccessful()) {
            $transaction->status = Commerce_TransactionRecord::SUCCESS;
        } elseif ($response->isRedirect()) {
            $transaction->status = Commerce_TransactionRecord::REDIRECT;
        } else {
            $transaction->status = Commerce_TransactionRecord::FAILED;
        }

        $transaction->reference = $response->getTransactionReference();
        $transaction->message = $response->getMessage();

        if ($response->isSuccessful()) {
            craft()->commerce_orders->updateOrderPaidTotal($transaction->order);
        }

        $this->saveTransaction($transaction);
    }

    /**
     * @param Commerce_OrderModel $order
     * @param Commerce_PaymentFormModel $paymentForm
     *
     * @return CreditCard
     */
    private function createCard(
        Commerce_OrderModel $order,
        Commerce_PaymentFormModel $paymentForm
    )
    {
        $card = new CreditCard;

        $card->setFirstName($paymentForm->firstName);
        $card->setLastName($paymentForm->lastName);
        $card->setNumber($paymentForm->number);
        $card->setExpiryMonth($paymentForm->month);
        $card->setExpiryYear($paymentForm->year);
        $card->setCvv($paymentForm->cvv);


        if ($order->billingAddressId) {
            $billingAddress = $order->billingAddress;
            $card->setBillingAddress1($billingAddress->address1);
            $card->setBillingAddress2($billingAddress->address2);
            $card->setBillingCity($billingAddress->city);
            $card->setBillingPostcode($billingAddress->zipCode);
            $card->setBillingState($billingAddress->getStateText());
            $card->setBillingCountry($billingAddress->getCountryText());
            $card->setBillingPhone($billingAddress->phone);
        }

        if ($order->shippingAddressId) {
            $shippingAddress = $order->shippingAddress;
            $card->setShippingAddress1($shippingAddress->address1);
            $card->setShippingAddress2($shippingAddress->address2);
            $card->setShippingCity($shippingAddress->city);
            $card->setShippingPostcode($shippingAddress->zipCode);
            $card->setShippingState($shippingAddress->getStateText());
            $card->setShippingCountry($shippingAddress->getCountryText());
            $card->setShippingPhone($shippingAddress->phone);
            $card->setCompany($shippingAddress->company);
        }

        $card->setEmail($order->email);

        return $card;
    }

    /**
     * @param Commerce_TransactionModel $transaction
     * @param CreditCard $card
     *
     * @return array
     */
    private function buildPaymentRequest(
        Commerce_TransactionModel $transaction,
        CreditCard $card = null
    )
    {
        $request = [
            'amount' => $transaction->amount,
            'currency' => craft()->commerce_settings->getOption('defaultCurrency'),
            'transactionId' => $transaction->id,
            'description'   => Craft::t('Order') . ' #'.$transaction->orderId,
            'clientIp' => craft()->request->getIpAddress(),
            'gatewayReference' => $transaction->reference,
            'returnUrl' => UrlHelper::getActionUrl('commerce/cartPayment/complete',
                ['id' => $transaction->id, 'hash' => $transaction->hash]),
            'cancelUrl' => UrlHelper::getSiteUrl($transaction->order->cancelUrl),
        ];
        if ($card) {
            $request['card'] = $card;
        }

        return $request;
    }

    /**
     * @param Commerce_TransactionModel $child
     *
     * @throws Exception
     */
    private function saveTransaction($child)
    {
        if (!craft()->commerce_transactions->save($child)) {
            throw new Exception(Craft::t('Error saving transaction: ') . implode(', ',
                    $child->getAllErrors()));
        }
    }

    /**
     *
     * Gets the total transactions amount really paid (not authorized)
     *
     * @param Commerce_OrderModel $order
     *
     * @return static[]
     */
    public function getTotalPaidForOrder(Commerce_OrderModel $order)
    {
        $criteria = new \CDbCriteria();
        $criteria->select = 'sum(amount) AS total, orderId';
        $criteria->addCondition(['status = :status', 'orderId = :orderId']);
        $criteria->params = [
            'orderId' => $order->id,
            'status' => Commerce_TransactionRecord::SUCCESS
        ];
        $criteria->addInCondition('type', [Commerce_TransactionRecord::PURCHASE, Commerce_TransactionRecord::CAPTURE]);
        $criteria->group = 'orderId';

        $transaction = Commerce_TransactionRecord::model()->find($criteria);

        if ($transaction) {
            return $transaction->total;
        }

        return 0;
    }

    /**
     * Gets the total transactions amount with authorized
     *
     * @param Commerce_OrderModel $order
     *
     * @return static[]
     */
    public function getTotalAuthorizedForOrder(Commerce_OrderModel $order)
    {
        $criteria = new \CDbCriteria();
        $criteria->select = 'sum(amount) AS total, orderId';
        $criteria->addCondition(['status = :status', 'orderId = :orderId']);
        $criteria->params = [
            'orderId' => $order->id,
            'status' => Commerce_TransactionRecord::SUCCESS
        ];
        $criteria->addInCondition('type', [Commerce_TransactionRecord::AUTHORIZE, Commerce_TransactionRecord::PURCHASE, Commerce_TransactionRecord::CAPTURE]);
        $criteria->group = 'orderId';

        $transaction = Commerce_TransactionRecord::model()->find($criteria);

        if ($transaction) {
            return $transaction->total;
        }

        return 0;
    }
}
