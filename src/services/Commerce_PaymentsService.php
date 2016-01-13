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
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
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
        craft()->commerce_orders->saveOrder($cart);


        // Cart could have zero totalPrice and already considered 'paid'. Free carts complete immediately.
        if ($cart->isPaid()) {
            if(!$cart->datePaid){
                $cart->datePaid = DateTimeHelper::currentTimeForDb();
            }
            
            craft()->commerce_orders->completeOrder($cart);

            if ($cart->returnUrl) {
                craft()->request->redirect($cart->returnUrl);
            }

            return true;
        }

        // Validate card if no token provided
        if (!$form->token && $cart->paymentMethod->requiresCard()) {
            if (!$form->validate()) {
                $customError = Craft::t("Invalid payment information.");
                return false;
            }
        }

        //choosing default action
        $defaultAction = $cart->paymentMethod->paymentType;
        $defaultAction = ($defaultAction === Commerce_TransactionRecord::TYPE_PURCHASE) ? $defaultAction : Commerce_TransactionRecord::TYPE_AUTHORIZE;
        $gateway = $cart->paymentMethod->getGatewayAdapter()->getGateway();

        if ($defaultAction == Commerce_TransactionRecord::TYPE_AUTHORIZE) {
            if (!$gateway->supportsAuthorize()) {
                $customError = Craft::t("Gateway doesn’t support authorize");

                return false;
            }
        } else {
            if (!$gateway->supportsPurchase()) {
                $customError = Craft::t("Gateway doesn’t support purchase");

                return false;
            }
        }

        //creating cart, transaction and request
        $transaction = craft()->commerce_transactions->createTransaction($cart);
        $transaction->type = $defaultAction;
        $this->saveTransaction($transaction);

        $card = $this->createCard($cart, $form);

        $request = $gateway->$defaultAction($this->buildPaymentRequest($transaction, $card));

        // set token directly on request if available (not in card)
        if ($form->token) {
            $request->setToken($form->token);
        }

        try {
            $redirect = $this->sendPaymentRequest($request, $transaction);

            if ($transaction->status == Commerce_TransactionRecord::STATUS_SUCCESS) {
                craft()->commerce_orders->updateOrderPaidTotal($cart);
            }
            if ($transaction->status == Commerce_TransactionRecord::STATUS_FAILED) {
                $customError = $transaction->message;
            }
        } catch (\Exception $e) {
            $customError = $e->getMessage();

            return false;
        }

        if ($redirect) {
            craft()->request->redirect($redirect);
        }

        return ($transaction->status == Commerce_TransactionRecord::STATUS_SUCCESS);
    }

    /**
     * @param Commerce_TransactionModel $child
     *
     * @throws Exception
     */
    private function saveTransaction($child)
    {
        if (!craft()->commerce_transactions->saveTransaction($child)) {
            throw new Exception(Craft::t('Error saving transaction: ') . implode(', ',
                    $child->getAllErrors()));
        }
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
            if ($billingAddress) {
                $card->setBillingAddress1($billingAddress->address1);
                $card->setBillingAddress2($billingAddress->address2);
                $card->setBillingCity($billingAddress->city);
                $card->setBillingPostcode($billingAddress->zipCode);
                $card->setBillingState($billingAddress->getStateText());
                $card->setBillingCountry($billingAddress->getCountry()->iso);
                $card->setBillingPhone($billingAddress->phone);
                $card->setBillingCompany($billingAddress->businessName);
                $card->setCompany($billingAddress->businessName);
            }
        }

        if ($order->shippingAddressId) {
            $shippingAddress = $order->shippingAddress;
            if ($shippingAddress) {
                $card->setShippingAddress1($shippingAddress->address1);
                $card->setShippingAddress2($shippingAddress->address2);
                $card->setShippingCity($shippingAddress->city);
                $card->setShippingPostcode($shippingAddress->zipCode);
                $card->setShippingState($shippingAddress->getStateText());
                $card->setShippingCountry($shippingAddress->getCountry()->iso);
                $card->setShippingPhone($shippingAddress->phone);
                $card->setShippingCompany($shippingAddress->businessName);
            }
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
            'description' => Craft::t('Order') . ' #' . $transaction->orderId,
            'clientIp' => craft()->request->getIpAddress(),
            'transactionReference' => $transaction->hash,
            'returnUrl' => UrlHelper::getActionUrl('commerce/cartPayment/complete',
                ['id' => $transaction->id, 'hash' => $transaction->hash]),
            'cancelUrl' => UrlHelper::getSiteUrl($transaction->order->cancelUrl),
        ];

        $request['notifyUrl'] = $request['returnUrl'];

        // custom gateways may wish to access the order directly
        $request['order'] = $transaction->order;
        $request['orderId'] = $transaction->order->id;

        // Paypal only params
        $request['noShipping'] = 1;
        $request['allowNote'] = 0;
        $request['addressOverride'] = 1;

        if ($card) {
            $request['card'] = $card;
        }

        $pluginRequest = craft()->plugins->callFirst('commerce_modifyPaymentRequest', [$request]);

        if ($pluginRequest) {
            $request = array_merge($request, $pluginRequest);
        }

        return $request;
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
        //raising event
        $event = new Event($this, [
            'type' => $transaction->type,
            'request' => $request,
            'transaction' => $transaction
        ]);
        $this->onBeforeGatewayRequestSend($event);

        if (!$event->performAction) {
            $transaction->status = Commerce_TransactionRecord::STATUS_FAILED;
            $this->saveTransaction($transaction);
        }

        if ($event->performAction) {
            try {
                /** @var ResponseInterface $response */
                $response = $request->send();
                $this->updateTransaction($transaction, $response);

                if ($response->isRedirect()) {
                    // redirect to off-site gateway
                    $response->redirect();
                }
            } catch (\Exception $e) {
                $transaction->status = Commerce_TransactionRecord::STATUS_FAILED;
                $transaction->message = $e->getMessage();
                $this->saveTransaction($transaction);
            }
        }

        $order = $transaction->order;

        if ($transaction->status == Commerce_TransactionRecord::STATUS_SUCCESS) {
            return $order->returnUrl;
        } else {
            craft()->userSession->setError(Craft::t('Payment error: {message}', ['message' => $transaction->message]));

            return $order->cancelUrl;
        }
    }

    /**
     * Event: before sending a payment request to the gateway
     * Event params: type(string)
     *               request(AbstractRequest)
     *               transaction(Commerce_TransactionModel)
     *
     * @param \CEvent $event
     *
     * @throws \CException
     */
    public function onBeforeGatewayRequestSend(\CEvent $event)
    {
        $params = $event->params;

        if (empty($params['type'])) {
            throw new Exception('onBeforeGatewayRequestSend event requires "type" param');
        }

        if (empty($params['request']) || !($params['request'] instanceof AbstractRequest)) {
            throw new Exception('onBeforeGatewayRequestSend event requires "request" param as AbstractRequest');
        }

        if (empty($params['transaction']) || !($params['transaction'] instanceof Commerce_TransactionModel)) {
            throw new Exception('onBeforeGatewayRequestSend event requires "request" param as AbstractRequest');
        }

        $this->raiseEvent('onBeforeGatewayRequestSend', $event);
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
            $transaction->status = Commerce_TransactionRecord::STATUS_SUCCESS;
        } elseif ($response->isRedirect()) {
            $transaction->status = Commerce_TransactionRecord::STATUS_REDIRECT;
        } else {
            $transaction->status = Commerce_TransactionRecord::STATUS_FAILED;
        }

        $transaction->reference = $response->getTransactionReference();
        $transaction->message = $response->getMessage();

        if ($response->isSuccessful()) {
            craft()->commerce_orders->updateOrderPaidTotal($transaction->order);
        }

        $this->saveTransaction($transaction);
    }

    /**
     * @param Commerce_TransactionModel $transaction
     *
     * @return Commerce_TransactionModel
     */
    public function captureTransaction(Commerce_TransactionModel $transaction)
    {
        return $this->processCaptureOrRefund($transaction,
            Commerce_TransactionRecord::TYPE_CAPTURE);
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
            Commerce_TransactionRecord::TYPE_CAPTURE,
            Commerce_TransactionRecord::TYPE_REFUND
        ])
        ) {
            throw new Exception('Wrong action: ' . $action);
        }

        $order = $parent->order;
        $child = craft()->commerce_transactions->createTransaction($order);
        $child->parentId = $parent->id;
        $child->paymentMethodId = $parent->paymentMethodId;
        $child->type = $action;
        $child->amount = $parent->amount;
        $this->saveTransaction($child);

        $gateway = $parent->paymentMethod->getGatewayAdapter()->getGateway();
        $request = $gateway->$action($this->buildPaymentRequest($child));
        $request->setTransactionReference($parent->reference);

        $order->returnUrl = $order->getCpEditUrl();
        craft()->commerce_orders->saveOrder($order);

        try {

            //raising event
            $event = new Event($this, [
                'type' => $child->type,
                'request' => $request,
                'transaction' => $child
            ]);
            $this->onBeforeGatewayRequestSend($event);

            // Don't send the request
            if (!$event->performAction) {
                $child->status = Commerce_TransactionRecord::STATUS_FAILED;
                $this->saveTransaction($child);
            }

            // Send the request!
            if ($event->performAction) {
                $response = $request->send();
                $this->updateTransaction($child, $response);
            }

        } catch (\Exception $e) {
            $child->status = Commerce_TransactionRecord::STATUS_FAILED;
            $child->message = $e->getMessage();

            $this->saveTransaction($child);
        }

        return $child;
    }

    /**
     * @param Commerce_TransactionModel $transaction
     *
     * @return Commerce_TransactionModel
     */
    public function refundTransaction(Commerce_TransactionModel $transaction)
    {
        return $this->processCaptureOrRefund($transaction,
            Commerce_TransactionRecord::TYPE_REFUND);
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
        if ($transaction->status != Commerce_TransactionRecord::STATUS_REDIRECT) {
            if ($transaction->status == Commerce_TransactionRecord::STATUS_SUCCESS) {
                craft()->request->redirect($order->returnUrl);
            } else {
                craft()->userSession->setError(Craft::t('Payment error: {message}', ['message' => $transaction->message]));
                craft()->request->redirect($order->cancelUrl);
            }
        }

        // load payment driver
        $gateway = $transaction->paymentMethod->getGatewayAdapter()->getGateway();

        $action = 'complete' . ucfirst($transaction->type);
        $supportsAction = 'supports' . ucfirst($action);
        if ($gateway->$supportsAction()) {

            $params = $this->buildPaymentRequest($transaction);

            // If MOLLIE, the transactionReference will be theirs
            $name = $transaction->paymentMethod->getGatewayAdapter()->getGateway()->getName();
            if ($name == 'Mollie_Ideal' || $name == 'Mollie' || $name == 'SagePay_Server') {
                $params['transactionReference'] = $transaction->reference;
            }

            // don't send notifyUrl for completePurchase
            unset($params['notifyUrl']);

            $request = $gateway->$action($params);
            $redirect = $this->sendPaymentRequest($request, $transaction);

            if ($transaction->status == Commerce_TransactionRecord::STATUS_SUCCESS) {
                craft()->commerce_orders->updateOrderPaidTotal($order);
            }
            craft()->request->redirect($redirect);
        } else {
            throw new Exception('Payment Gateway does not support: '.$supportsAction);
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
            'status' => Commerce_TransactionRecord::STATUS_SUCCESS
        ];
        $criteria->addInCondition('type', [Commerce_TransactionRecord::TYPE_PURCHASE, Commerce_TransactionRecord::TYPE_CAPTURE]);
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
            'status' => Commerce_TransactionRecord::STATUS_SUCCESS
        ];
        $criteria->addInCondition('type', [Commerce_TransactionRecord::TYPE_AUTHORIZE, Commerce_TransactionRecord::TYPE_PURCHASE, Commerce_TransactionRecord::TYPE_CAPTURE]);
        $criteria->group = 'orderId';

        $transaction = Commerce_TransactionRecord::model()->find($criteria);

        if ($transaction) {
            return $transaction->total;
        }

        return 0;
    }
}
