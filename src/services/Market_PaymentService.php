<?php
namespace Craft;

use Omnipay\Common\CreditCard;
use Omnipay\Common\Message\AbstractRequest;
use Omnipay\Common\Message\ResponseInterface;

/**
 * Class Market_PaymentService
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://buildwithcraft.com/license Craft License Agreement
 * @see       http://buildwithcraft.com/commerce
 * @package   craft.plugins.commerce.services
 * @since     1.0
 */
class Market_PaymentService extends BaseApplicationComponent
{
    /**
     * @param Market_OrderModel       $cart
     * @param Market_PaymentFormModel $form
     *
     * @param                         $redirect
     * @param                         $cancelUrl
     * @param string                  $customError
     *
     * @return bool
     * @throws Exception
     * @throws \Exception
     */
    public function processPayment(
        Market_OrderModel $cart,
        Market_PaymentFormModel $form,
        $redirect,
        $cancelUrl,
        &$customError = ''
    ) {

        //saving cancelUrl and redirect to cart
        $cart->returnUrl = craft()->templates->renderObjectTemplate($redirect, $cart);
        $cart->cancelUrl = craft()->templates->renderObjectTemplate($cancelUrl, $cart);
        craft()->market_order->save($cart);


        // Cart could have zero totalPrice and already considered 'paid'. Free carts complete immediately.
        if($cart->isPaid()){
            craft()->market_order->complete($cart);
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
        $defaultAction = craft()->market_settings->getOption('paymentMethod');
        $defaultAction = ($defaultAction === Market_TransactionRecord::PURCHASE) ? $defaultAction : Market_TransactionRecord::AUTHORIZE;
        $gateway       = $cart->paymentMethod->getGateway();

        if ($defaultAction == Market_TransactionRecord::AUTHORIZE) {
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
        $transaction       = craft()->market_transaction->create($cart);
        $transaction->type = $defaultAction;
        $this->saveTransaction($transaction);

        $card = $this->createCard($cart, $form);

        $request = $gateway->$defaultAction($this->buildPaymentRequest($transaction,
            $card));

        try {
            $redirect = $this->sendPaymentRequest($request, $transaction);

            if ($transaction->status == Market_TransactionRecord::SUCCESS) {
                craft()->market_order->updateOrderPaidTotal($cart);
            }
        } catch (\Exception $e) {
            $customError = $e->getMessage();
            craft()->market_transaction->delete($transaction);

            return false;
        }

        craft()->request->redirect($redirect);

        return true;
    }

    /**
     * Send a payment request to the gateway, and redirect appropriately
     *
     * @param AbstractRequest         $request
     * @param Market_TransactionModel $transaction
     *
     * @return string
     */
    private function sendPaymentRequest(
        AbstractRequest $request,
        Market_TransactionModel $transaction
    ) {
        // try {
        /** @var ResponseInterface $response */
        $response = $request->send();
        $this->updateTransaction($transaction, $response);

        if ($response->isRedirect()) {
            // redirect to off-site gateway
            return $response->redirect();
        }

        $order = $transaction->order;

        if ($response->isSuccessful()) {
            return $order->returnUrl;
        } else {
            craft()->userSession->setError(Craft::t("Payment error: " . $transaction->message));

            return $order->cancelUrl;
        }
    }

    /**
     * @param Market_TransactionModel $transaction
     *
     * @return Market_TransactionModel
     */
    public function captureTransaction(Market_TransactionModel $transaction)
    {
        return $this->processCaptureOrRefund($transaction,
            Market_TransactionRecord::CAPTURE);
    }

    /**
     * @param Market_TransactionModel $transaction
     *
     * @return Market_TransactionModel
     */
    public function refundTransaction(Market_TransactionModel $transaction)
    {
        return $this->processCaptureOrRefund($transaction,
            Market_TransactionRecord::REFUND);
    }

    /**
     * @param Market_TransactionModel $parent
     * @param string                  $action
     *
     * @return Market_TransactionModel
     * @throws Exception
     */
    private function processCaptureOrRefund(
        Market_TransactionModel $parent,
        $action
    ) {
        if (!in_array($action, [
            Market_TransactionRecord::CAPTURE,
            Market_TransactionRecord::REFUND
        ])
        ) {
            throw new Exception('Wrong action: ' . $action);
        }

        $order                  = $parent->order;
        $child                  = craft()->market_transaction->create($order);
        $child->parentId        = $parent->id;
        $child->paymentMethodId = $parent->paymentMethodId;
        $child->type            = $action;
        $child->amount          = $parent->amount;
        $this->saveTransaction($child);

        $gateway = $parent->paymentMethod->getGateway();
        $request = $gateway->$action($this->buildPaymentRequest($child));
        $request->setTransactionReference($parent->reference);

        $order->returnUrl = $order->getCpEditUrl();
        craft()->market_order->save($order);

        try {
            $response = $request->send();
            $this->updateTransaction($child, $response);
        } catch (\Exception $e) {
            $child->status  = Market_TransactionRecord::FAILED;
            $child->message = $e->getMessage();

            $this->saveTransaction($child);
        }

        return $child;
    }

    /**
     * Process return from off-site payment
     *
     * @param Market_TransactionModel $transaction
     *
     * @throws Exception
     */
    public function completePayment(Market_TransactionModel $transaction)
    {
        $order = $transaction->order;

        // ignore already processed transactions
        if ($transaction->status != Market_TransactionRecord::REDIRECT) {
            if ($transaction->status == Market_TransactionRecord::SUCCESS) {
                craft()->request->redirect($order->returnUrl);
            } else {
                craft()->userSession->setError('Payment error: ' . $transaction->message);
                craft()->request->redirect($order->cancelUrl);
            }
        }

        // load payment driver
        $gateway = $transaction->paymentMethod->getGateway();

        $action         = 'complete' . ucfirst($transaction->type);
        $supportsAction = 'supports' . ucfirst($action);
        if ($gateway->$supportsAction()) {
            // don't send notifyUrl for completePurchase
            $params = $this->buildPaymentRequest($transaction);

            // If MOLLIE, the transactionReference will be theirs
            $name = $transaction->paymentMethod->class;
            if ( $name == 'Mollie_Ideal' || $name == 'Mollie' || $name == 'SagePay_Server') {
                $params['transactionReference'] = $transaction->reference;
            }

            unset($params['notifyUrl']);

            $request  = $gateway->$action($params);
            $redirect = $this->sendPaymentRequest($request, $transaction);

            if ($transaction->status == Market_TransactionRecord::SUCCESS) {
                craft()->market_order->updateOrderPaidTotal($order);
            }
            craft()->request->redirect($redirect);
        } else {
            throw new Exception('Payment return not supported');
        }
    }

    /**
     * @param Market_TransactionModel $transaction
     * @param ResponseInterface       $response
     *
     * @throws Exception
     */
    private function updateTransaction(
        Market_TransactionModel $transaction,
        ResponseInterface $response
    ) {
        if ($response->isSuccessful()) {
            $transaction->status = Market_TransactionRecord::SUCCESS;
        } elseif ($response->isRedirect()) {
            $transaction->status = Market_TransactionRecord::REDIRECT;
        } else {
            $transaction->status = Market_TransactionRecord::FAILED;
        }

        $transaction->reference = $response->getTransactionReference();
        $transaction->message   = $response->getMessage();

        if($response->isSuccessful()){
            craft()->market_order->updateOrderPaidTotal($transaction->order);
        }

        $this->saveTransaction($transaction);
    }

    /**
     * @param Market_OrderModel       $order
     * @param Market_PaymentFormModel $paymentForm
     *
     * @return CreditCard
     */
    private function createCard(
        Market_OrderModel $order,
        Market_PaymentFormModel $paymentForm
    ) {
        $card = new CreditCard;

        $card->setFirstName($paymentForm->firstName);
        $card->setLastName($paymentForm->lastName);
        $card->setNumber($paymentForm->number);
        $card->setExpiryMonth($paymentForm->month);
        $card->setExpiryYear($paymentForm->year);
        $card->setCvv($paymentForm->cvv);


        if($order->billingAddressId) {
            $billingAddress = $order->billingAddress;
            $card->setBillingAddress1($billingAddress->address1);
            $card->setBillingAddress2($billingAddress->address2);
            $card->setBillingCity($billingAddress->city);
            $card->setBillingPostcode($billingAddress->zipCode);
            $card->setBillingState($billingAddress->getStateText());
            $card->setBillingCountry($billingAddress->getCountryText());
            $card->setBillingPhone($billingAddress->phone);
        }

        if($order->shippingAddressId) {
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
     * @param Market_TransactionModel $transaction
     * @param CreditCard              $card
     *
     * @return array
     */
    private function buildPaymentRequest(
        Market_TransactionModel $transaction,
        CreditCard $card = null
    ) {
        $request = [
            'amount'        => $transaction->amount,
            'currency'      => craft()->market_settings->getOption('defaultCurrency'),
            'transactionId' => $transaction->id,
            'description'   => Craft::t('Order') . ' #'.$transaction->orderId,
            'clientIp'      => craft()->request->getIpAddress(),
            'transactionReference' => $transaction->reference,
            'returnUrl'     => UrlHelper::getActionUrl('market/cartPayment/complete',
                ['id' => $transaction->id, 'hash' => $transaction->hash]),
            'cancelUrl'     => UrlHelper::getSiteUrl($transaction->order->cancelUrl),
        ];

        $request['notifyUrl'] = $request['returnUrl'];

        // custom gateways may wish to access the order directly
        $request['order'] = $transaction->order;
        $request['orderId'] = $transaction->order->id;

        // Params only used for paypal
        $request['noShipping'] = 1;
        $request['allowNote'] = 0;
        $request['addressOverride'] = 1;

        if ($card) {
            $request['card'] = $card;
        }

        $pluginRequest = craft()->plugins->callFirst('modifyCommercePaymentRequest',$request);

        if($pluginRequest){
            $request = array_merge($request,$pluginRequest);
        }

        return $request;
    }

    /**
     * @param Market_TransactionModel $child
     *
     * @throws Exception
     */
    private function saveTransaction($child)
    {
        if (!craft()->market_transaction->save($child)) {
            throw new Exception(Craft::t('Error saving transaction: ') . implode(', ',
                    $child->getAllErrors()));
        }
    }

    /**
     *
     * Gets the total transactions amount really paid (not authorized)
     *
     * @param Market_OrderModel $order
     * @return static[]
     */
    public function getTotalPaidForOrder(Market_OrderModel $order)
    {
        $criteria = new \CDbCriteria();
        $criteria->select = 'sum(amount) AS total, orderId';
        $criteria->addCondition(['status = :status','orderId = :orderId']);
        $criteria->params = [
            'orderId' => $order->id,
            'status' => Market_TransactionRecord::SUCCESS
        ];
        $criteria->addInCondition('type',[Market_TransactionRecord::PURCHASE,Market_TransactionRecord::CAPTURE]);
        $criteria->group ='orderId';

        $transaction = Market_TransactionRecord::model()->find($criteria);

        if($transaction){
            return $transaction->total;
        }

        return 0;
    }

    /**
     * Gets the total transactions amount with authorized
     *
     * @param Market_OrderModel $order
     * @return static[]
     */
    public function getTotalAuthorizedForOrder(Market_OrderModel $order)
    {
        $criteria = new \CDbCriteria();
        $criteria->select = 'sum(amount) AS total, orderId';
        $criteria->addCondition(['status = :status','orderId = :orderId']);
        $criteria->params = [
            'orderId' => $order->id,
            'status' => Market_TransactionRecord::SUCCESS
        ];
        $criteria->addInCondition('type',[Market_TransactionRecord::AUTHORIZE,Market_TransactionRecord::PURCHASE,Market_TransactionRecord::CAPTURE]);
        $criteria->group ='orderId';

        $transaction = Market_TransactionRecord::model()->find($criteria);

        if($transaction){
            return $transaction->total;
        }

        return 0;
    }
}
