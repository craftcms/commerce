<?php

namespace craft\commerce\services;

use craft\commerce\gateway\models\BasePaymentFormModel;
use Craft;
use craft\commerce\elements\Order;
use craft\commerce\events\GatewayRequestEvent;
use craft\commerce\events\TransactionEvent;
use craft\commerce\helpers\Currency;
use craft\commerce\models\Transaction;
use craft\commerce\Plugin;
use craft\commerce\records\Transaction as TransactionRecord;
use craft\db\Query;
use craft\helpers\DateTimeHelper;
use Omnipay\Common\CreditCard;
use Omnipay\Common\ItemBag;
use Omnipay\Common\Message\RequestInterface;
use Omnipay\Common\Message\ResponseInterface;
use yii\base\Component;

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
class Payments extends Component
{
    // Constants
    // =========================================================================

    /**
     * @event GatewayRequestEvent The event that is triggered before a gateway request is sent
     *
     * You may set [[GatewayRequestEvent::isValid]] to `false` to prevent the request from being sent.
     */
    const EVENT_BEFORE_GATEWAY_REQUEST_SEND = 'beforeGatewayRequestSend';

    /**
     * @event TransactionEvent The event that is triggered before a transaction is captured
     */
    const EVENT_BEFORE_CAPTURE_TRANSACTION = 'beforeCaptureTransaction';

    /**
     * @event TransactionEvent The event that is triggered after a transaction is captured
     */
    const EVENT_AFTER_CAPTURE_TRANSACTION = 'afterCaptureTransaction';

    /**
     * @event TransactionEvent The event that is triggered before a transaction is refunded
     */
    const EVENT_BEFORE_REFUND_TRANSACTION = 'beforeRefundTransaction';

    /**
     * @event TransactionEvent The event that is triggered after a transaction is refunded
     */
    const EVENT_AFTER_REFUND_TRANSACTION = 'afterRefundTransaction';

    /**
     * @event ItemBagEvent The event that is triggered after an item bag is created
     */
    const EVENT_AFTER_CREATE_ITEM_BAG = 'afterCreateItemBag';

    /**
     * @event BuildPaymentRequestEvent The event that is triggered after a payment request is being built
     */
    const EVENT_BUILD_PAYMENT_REQUEST = 'afterBuildPaymentRequest';

    /**
     * @event SendPaymentRequestEvent The event that is triggered right before a payment request is being sent
     */
    const EVENT_BEFORE_SEND_PAYMENT_REQUEST = 'beforeSendPaymentRequest';

    // Public Methods
    // =========================================================================

    /**
     * @param Order                $order
     * @param BasePaymentFormModel $form
     * @param string|null          &$redirect
     * @param string|null          &$customError
     *
     * @return bool
     * @throws Exception
     * @throws \Exception
     */
    public function processPayment(
        Order $order,
        BasePaymentFormModel $form,
        &$redirect = null,
        &$customError = null
    ) {
        // Order could have zero totalPrice and already considered 'paid'. Free orders complete immediately.
        if ($order->isPaid()) {
            if (!$order->datePaid) {
                $order->datePaid = DateTimeHelper::currentTimeStamp();
            }

            if (!$order->isCompleted) {
                Plugin::getInstance()->getOrders()->completeOrder($order);
            }

            return true;
        }

        //choosing default action
        $defaultAction = $order->paymentMethod->paymentType;
        $defaultAction = ($defaultAction === TransactionRecord::TYPE_PURCHASE) ? $defaultAction : TransactionRecord::TYPE_AUTHORIZE;
        $gateway = $order->paymentMethod->getGateway();

        if ($defaultAction == TransactionRecord::TYPE_AUTHORIZE) {
            if (!$gateway->supportsAuthorize()) {
                $customError = Craft::t("commerce", "Gateway doesn’t support authorize");

                return false;
            }
        } else {
            if (!$gateway->supportsPurchase()) {
                $customError = Craft::t("commerce", "Gateway doesn’t support purchase");

                return false;
            }
        }

        //creating order, transaction and request
        $transaction = Plugin::getInstance()->getTransactions()->createTransaction($order);
        $transaction->type = $defaultAction;
        $this->saveTransaction($transaction);

        $card = $this->createCard($order, $form);

        $itemBag = $this->createItemBag($order);

        $request = $gateway->$defaultAction($this->buildPaymentRequest($transaction, $card, $itemBag));

        // Let the payment methods gateway adapter do anything else to the request
        // including populating the request with things other than the card data.
        $order->paymentMethod->populateRequest($request, $form);

        try {
            $success = $this->sendPaymentRequest($order, $request, $transaction, $redirect, $customError);

            if ($success) {
                Plugin::getInstance()->getOrders()->updateOrderPaidTotal($order);
            }
        } catch (\Exception $e) {
            $success = false;
            $customError = $e->getMessage();
        }

        return $success;
    }

    /**
     * @param Transaction $child
     *
     * @throws Exception
     */
    private function saveTransaction($child)
    {
        if (!Plugin::getInstance()->getTransactions()->saveTransaction($child)) {
            throw new Exception('Error saving transaction: '.implode(', ', $child->errors));
        }
    }

    /**
     * @param Order               $order
     * @param                     $paymentForm
     *
     * @return CreditCard
     */
    private function createCard(
        Order $order,
        $paymentForm
    ) {
        $card = new CreditCard;

        $order->paymentMethod->populateCard($card, $paymentForm);

        if ($order->billingAddressId) {
            $billingAddress = $order->billingAddress;
            if ($billingAddress) {
                // Set top level names to the billing names
                $card->setFirstName($billingAddress->firstName);
                $card->setLastName($billingAddress->lastName);

                $card->setBillingFirstName($billingAddress->firstName);
                $card->setBillingLastName($billingAddress->lastName);
                $card->setBillingAddress1($billingAddress->address1);
                $card->setBillingAddress2($billingAddress->address2);
                $card->setBillingCity($billingAddress->city);
                $card->setBillingPostcode($billingAddress->zipCode);
                if ($billingAddress->getCountry()) {
                    $card->setBillingCountry($billingAddress->getCountry()->iso);
                }
                if ($billingAddress->getState()) {
                    $state = $billingAddress->getState()->abbreviation ?: $billingAddress->getState()->name;
                    $card->setBillingState($state);
                } else {
                    $card->setBillingState($billingAddress->getStateText());
                }
                $card->setBillingPhone($billingAddress->phone);
                $card->setBillingCompany($billingAddress->businessName);
                $card->setCompany($billingAddress->businessName);
            }
        }

        if ($order->shippingAddressId) {
            $shippingAddress = $order->shippingAddress;
            if ($shippingAddress) {
                $card->setShippingFirstName($shippingAddress->firstName);
                $card->setShippingLastName($shippingAddress->lastName);
                $card->setShippingAddress1($shippingAddress->address1);
                $card->setShippingAddress2($shippingAddress->address2);
                $card->setShippingCity($shippingAddress->city);
                $card->setShippingPostcode($shippingAddress->zipCode);

                if ($shippingAddress->getCountry()) {
                    $card->setShippingCountry($shippingAddress->getCountry()->iso);
                }

                if ($shippingAddress->getState()) {
                    $state = $shippingAddress->getState()->abbreviation ?: $shippingAddress->getState()->name;
                    $card->setShippingState($state);
                } else {
                    $card->setShippingState($shippingAddress->getStateText());
                }

                $card->setShippingPhone($shippingAddress->phone);
                $card->setShippingCompany($shippingAddress->businessName);
            }
        }

        $card->setEmail($order->email);

        return $card;
    }

    /**
     * @param Order $order
     *
     * @return null
     */
    private function createItemBag(Order $order)
    {

        if (!Plugin::getInstance()->getSettings()->sendCartInfoToGateways) {
            return null;
        }

        $items = $order->getPaymentMethod()->getGatewayAdapter()->createItemBag();

        $priceCheck = 0;

        $count = -1;
        /** @var LineItem $item */
        foreach ($order->lineItems as $item) {
            $price = Currency::round($item->salePrice);
            // Can not accept zero amount items. See item (4) here:
            // https://developer.paypal.com/docs/classic/express-checkout/integration-guide/ECCustomizing/#setting-order-details-on-the-paypal-review-page
            if ($price != 0) {
                $count++;
                $purchasable = $item->getPurchasable();
                $defaultDescription = Craft::t('commerce', 'Item ID')." ".$item->id;
                $purchasableDescription = $purchasable ? $purchasable->getDescription() : $defaultDescription;
                $description = isset($item->snapshot['description']) ? $item->snapshot['description'] : $purchasableDescription;
                $description = empty($description) ? "Item ".$count : $description;
                $items->add([
                    'name' => $description,
                    'description' => $description,
                    'quantity' => $item->qty,
                    'price' => $price,
                ]);
                $priceCheck = $priceCheck + ($item->qty * $item->salePrice);
            }
        }

        $count = -1;
        /** @var OrderAdjustment $adjustment */
        foreach ($order->adjustments as $adjustment) {
            $price = Currency::round($adjustment->amount);

            // Do not include the 'included' adjustments, and do not send zero value items
            // See item (4) https://developer.paypal.com/docs/classic/express-checkout/integration-guide/ECCustomizing/#setting-order-details-on-the-paypal-review-page
            if (($adjustment->included == 0 || $adjustment->included == false) && $price != 0) {
                $count++;
                $items->add([
                    'name' => empty($adjustment->name) ? $adjustment->type." ".$count : $adjustment->name,
                    'description' => empty($adjustment->description) ? $adjustment->type." ".$count : $adjustment->description,
                    'quantity' => 1,
                    'price' => $price,
                ]);
                $priceCheck = $priceCheck + $adjustment->amount;
            }
        }

        $priceCheck = Currency::round($priceCheck);
        $totalPrice = Currency::round($order->totalPrice);
        $same = (bool)($priceCheck == $totalPrice);

        if (!$same) {
            Craft::error('Item bag total price does not equal the orders totalPrice, some payment gateways will complain.', __METHOD__);
        }

        $event = new ItemBagEvent([
            'items' => $items,
            'order' => $order
        ]);;
        $this->trigger(self::EVENT_AFTER_CREATE_ITEM_BAG, $event);
        
        return $items;
    }

    /**
     * @param Transaction $transaction
     * @param CreditCard  $card
     * @param ItemBag     $itemBag
     *
     * @return array
     */
    private function buildPaymentRequest(
        Transaction $transaction,
        CreditCard $card = null,
        ItemBag $itemBag = null
    ) {
        $request = [
            'amount' => $transaction->paymentAmount,
            'currency' => $transaction->paymentCurrency,
            'transactionId' => $transaction->id,
            'description' => Craft::t('commerce', 'Order').' #'.$transaction->orderId,
            'clientIp' => Craft::$app->getRequest()->userIP,
            'transactionReference' => $transaction->hash,
            'returnUrl' => UrlHelper::getActionUrl('commerce/payments/completePayment', ['commerceTransactionId' => $transaction->id, 'commerceTransactionHash' => $transaction->hash]),
            'cancelUrl' => UrlHelper::getSiteUrl($transaction->order->cancelUrl),
        ];

        // Each gateway adapter needs to know whether to use our acceptNotification handler because most omnipay gateways
        // implement the notification API differently. Hoping Omnipay v3 will improve this.
        // For now, the standard paymentComplete handler is the default unless the gateway has been tested with our acceptNotification handler.
        // TODO: move the handler logic into the gateway adapter itself if the Omnipay v2 interface cannot standardise.
        if ($transaction->paymentMethod->getGatewayAdapter()->useNotifyUrl()) {
            $request['notifyUrl'] = UrlHelper::getActionUrl('commerce/payments/acceptNotification', ['commerceTransactionId' => $transaction->id, 'commerceTransactionHash' => $transaction->hash]);
            unset($request['returnUrl']);
        } else {
            $request['notifyUrl'] = $request['returnUrl'];
        }

        // Do not use IPv6 loopback
        if ($request['clientIp'] == "::1") {
            $request['clientIp'] = '127.0.0.1';
        }

        // custom gateways may wish to access the order directly
        $request['order'] = $transaction->order;
        $request['orderId'] = $transaction->order->id;

        // Stripe only params
        $request['receiptEmail'] = $transaction->order->email;

        // Paypal only params
        $request['noShipping'] = 1;
        $request['allowNote'] = 0;
        $request['addressOverride'] = 1;
        $request['buttonSource'] = 'ccommerce_SP';

        if ($card) {
            $request['card'] = $card;
        }

        if ($itemBag) {
            $request['items'] = $itemBag;
        }

        $event = new BuildPaymentRequestEvent([
            'params' => $request
        ]);
        $this->trigger(self::EVENT_BUILD_PAYMENT_REQUEST, $event);

        return $event->params;
    }

    /**
     * Send a payment request to the gateway, and redirect appropriately
     *
     * @param Order            $order
     * @param RequestInterface $request
     * @param Transaction      $transaction
     * @param string|null      &$redirect
     * @param string           &$customError
     *
     * @return bool
     */
    private function sendPaymentRequest(
        Order $order,
        RequestInterface $request,
        Transaction $transaction,
        &$redirect = null,
        &$customError = null
    ) {

        //raising event
        $event = new GatewayRequestEvent([
            'type' => $transaction->type,
            'request' => $request,
            'transaction' => $transaction
        ]);

        $this->trigger(self::EVENT_BEFORE_GATEWAY_REQUEST_SEND, $event);

        if (!$event->isValid) {
            $transaction->status = TransactionRecord::STATUS_FAILED;
            $this->saveTransaction($transaction);
        } else {
            try {

                $response = $this->_sendRequest($request, $transaction);

                $this->updateTransaction($transaction, $response);

                if ($response->isRedirect()) {
                    // redirect to off-site gateway
                    if ($response->getRedirectMethod() == 'GET') {
                        $redirect = $response->getRedirectUrl();
                    } else {

                        $gatewayPostRedirectTemplate = Plugin::getInstance()->getSettings()->gatewayPostRedirectTemplate;

                        if (!empty($gatewayPostRedirectTemplate)) {
                            $variables = [];
                            $hiddenFields = '';

                            // Gather all post hidden data inputs.
                            foreach ($response->getRedirectData() as $key => $value) {
                                $hiddenFields .= sprintf(
                                        '<input type="hidden" name="%1$s" value="%2$s" />',
                                        htmlentities($key, ENT_QUOTES, 'UTF-8', false),
                                        htmlentities($value, ENT_QUOTES, 'UTF-8', false)
                                    )."\n";
                            }
                            $variables['inputs'] = $hiddenFields;

                            // Set the action url to the responses redirect url
                            $variables['actionUrl'] = $response->getRedirectUrl();

                            // Set Craft to the site template mode
                            $templatesService = Craft::$app->getView();
                            $oldTemplateMode = $templatesService->getTemplateMode();
                            $templatesService->setTemplateMode(TemplateMode::Site);

                            $template = $templatesService->render($gatewayPostRedirectTemplate, $variables);

                            // Restore the original template mode
                            $templatesService->setTemplateMode($oldTemplateMode);

                            // Send the template back to the user.
                            ob_start();
                            echo $template;
                            Craft::$app->end();
                        }

                        // If the developer did not provide a gatewayPostRedirectTemplate, use the built in Omnipay Post html form.
                        $response->redirect();
                    }

                    return true;
                }
            } catch (\Exception $e) {
                $transaction->status = TransactionRecord::STATUS_FAILED;
                $transaction->message = $e->getMessage();
                Craft::error("Omnipay Gateway Communication Error: ".$e->getMessage(), __METHOD__);
                $this->saveTransaction($transaction);
            }
        }

        if ($transaction->status == TransactionRecord::STATUS_SUCCESS) {
            return true;
        }

        $customError = $transaction->message;
        return false;

    }

    /**
     * @param $request
     * @param $transaction
     *
     * @return mixed
     */
    private function _sendRequest($request, $transaction)
    {
        $data = $request->getData();

        $event = new SendPaymentRequestEvent([
            'requestData' => $data
        ]);
        $this->trigger(self::EVENT_BEFORE_SEND_PAYMENT_REQUEST, $event);
        
        // We can't merge the $data with $modifiedData since the $data is not always an array.
        // For example it could be a XML object, json, or anything else really.
        if ($event->modifiedRequestData !== null) {
            return $request->sendData($event->modifiedRequestData);
        }

        return $request->send();
    }

    /**
     * @param Transaction       $transaction
     * @param ResponseInterface $response
     *
     * @throws Exception
     */
    private function updateTransaction(
        Transaction $transaction,
        ResponseInterface $response
    ) {
        if ($response->isSuccessful()) {
            $transaction->status = TransactionRecord::STATUS_SUCCESS;
        } elseif ($response->isRedirect()) {
            $transaction->status = TransactionRecord::STATUS_REDIRECT;
        } else {
            $transaction->status = TransactionRecord::STATUS_FAILED;
        }

        $transaction->response = $response->getData();
        $transaction->code = $response->getCode();
        $transaction->reference = $response->getTransactionReference();
        $transaction->message = $response->getMessage();

        $this->saveTransaction($transaction);
    }

    /**
     * @param Transaction $transaction
     *
     * @return Transaction
     */
    public function captureTransaction(Transaction $transaction)
    {
        //raising event
        $event = new TransactionEvent([
            'transaction' => $transaction
        ]);

        $this->trigger(self::EVENT_BEFORE_CAPTURE_TRANSACTION, $event);

        $transaction = $this->processCaptureOrRefund($transaction, TransactionRecord::TYPE_CAPTURE);

        //raising event
        $event = new TransactionEvent([
            'transaction' => $transaction
        ]);
        $this->trigger(self::EVENT_AFTER_CAPTURE_TRANSACTION, $event);

        return $transaction;
    }

    /**
     * @param Transaction $parent
     * @param string      $action
     *
     * @return Transaction
     * @throws Exception
     */
    private function processCaptureOrRefund(
        Transaction $parent,
        $action
    ) {
        if (!in_array($action, [
            TransactionRecord::TYPE_CAPTURE,
            TransactionRecord::TYPE_REFUND
        ])
        ) {
            throw new Exception('Wrong action: '.$action);
        }

        $order = $parent->order;
        $child = Plugin::getInstance()->getTransactions()->createTransaction($order);
        $child->parentId = $parent->id;
        $child->paymentMethodId = $parent->paymentMethodId;
        $child->type = $action;
        $child->amount = $parent->amount;
        $child->paymentAmount = $parent->paymentAmount;
        $child->currency = $parent->currency;
        $child->paymentCurrency = $parent->paymentCurrency;
        $child->paymentRate = $parent->paymentRate;
        $this->saveTFransaction($child);

        $gateway = $parent->paymentMethod->getGateway();
        $request = $gateway->$action($this->buildPaymentRequest($child));
        $request->setTransactionReference($parent->reference);

        $order->returnUrl = $order->getCpEditUrl();
        Plugin::getInstance()->getOrders()->saveOrder($order);

        try {

            //raising event
            $event = new GatewayRequestEvent([
                'type' => $child->type,
                'request' => $request,
                'transaction' => $child
            ]);
            $this->trigger(self::EVENT_BEFORE_GATEWAY_REQUEST_SEND, $event);

            // Check if perhaps we shouldn't send the request
            if (!$event->isValid) {
                $child->status = TransactionRecord::STATUS_FAILED;
                $this->saveTransaction($child);
            } else {
                $response = $this->_sendRequest($request, $child);
                $this->updateTransaction($child, $response);
            }
        } catch (\Exception $e) {
            $child->status = TransactionRecord::STATUS_FAILED;
            $child->message = $e->getMessage();

            $this->saveTransaction($child);
        }

        return $child;
    }

    /**
     * @param Transaction $transaction
     *
     * @return Transaction
     */
    public function refundTransaction(Transaction $transaction)
    {
        //raising event
        $event = new TransactionEvent([
            'transaction' => $transaction
        ]);

        $this->trigger(self::EVENT_BEFORE_REFUND_TRANSACTION, $event);

        $transaction = $this->processCaptureOrRefund($transaction, TransactionRecord::TYPE_REFUND);

        //raising event
        //raising event
        $event = new TransactionEvent([
            'transaction' => $transaction
        ]);

        $this->trigger(self::EVENT_AFTER_REFUND_TRANSACTION, $event);

        return $transaction;
    }

    /**
     * Process return from off-site payment
     *
     * @param Transaction $transaction
     * @param string|null &$customError
     *
     * @return bool
     * @throws Exception
     */
    public function completePayment(
        Transaction $transaction,
        &$customError = null
    ) {
        $order = $transaction->order;

        // ignore already processed transactions
        if ($transaction->status != TransactionRecord::STATUS_REDIRECT) {
            if ($transaction->status == TransactionRecord::STATUS_SUCCESS) {
                return true;
            } else {
                $customError = $transaction->message;

                return false;
            }
        }

        // Load payment driver for the transaction we are trying to complete
        $gateway = $transaction->paymentMethod->getGateway();

        // Check if the driver supports completePurchase or completeAuthorize
        $action = 'complete'.ucfirst($transaction->type);

        $supportsAction = 'supports'.ucfirst($action);
        if (!$gateway->$supportsAction()) {
            $message = 'Payment Gateway does not support: '.$supportsAction;
            Craft::error($message, __METHOD__);
            throw new Exception($message);
        }

        // Some gateways need the cart data again on the order complete
        $itemBag = $this->createItemBag($order);
        $params = $this->buildPaymentRequest($transaction, null, $itemBag);

        // If MOLLIE, the transactionReference will be theirs
        // Netbanx Hosted requires the transactionReference is the same
        // Authorize.net SIM https://github.com/thephpleague/omnipay-authorizenet/issues/19
        // TODO: Move this into the gateway adapter.
        $handle = $transaction->paymentMethod->getGatewayAdapter()->handle();
        if ($handle == 'Mollie_Ideal' || $handle == 'Mollie' || $handle == 'NetBanx_Hosted' || $handle == 'AuthorizeNet_SIM') {
            $params['transactionReference'] = $transaction->reference;
        }

        // Don't send notifyUrl for completePurchase or completeAuthorize
        unset($params['notifyUrl']);

        $request = $gateway->$action($params);

        // Success can mean 2 things in this context.
        // 1) The transaction completed successfully with the gateway, and is now marked as complete.
        // 2) The result of the gateway request was successful but also got a redirect response. We now need to redirect if $redirect is not null.
        $success = $this->sendPaymentRequest($order, $request, $transaction, $redirect, $customError);

        if ($success && $transaction->status == TransactionRecord::STATUS_SUCCESS) {
            Plugin::getInstance()->getOrders()->updateOrderPaidTotal($transaction->order);
        }

        // For gateways that call us directly and usually do not like redirects.
        // TODO: Move this into the gateway adapter interface.
        $gateways = [
            'AuthorizeNet_SIM',
            'Realex_Redirect',
            'SecurePay_DirectPost',
            'WorldPay',
        ];

        if (in_array($transaction->paymentMethod->getGatewayAdapter()->handle(), $gateways)) {

            // Need to turn devMode off so the redirect template does not have any debug data attached.
            Craft::$app->getConfig()->set('devMode', false);

            // We redirect to a place that take us back to the completePayment endpoint, but this is ok
            // as complete payment can return early if the transaction was marked successful the previous time.
            $url = UrlHelper::getActionUrl('commerce/payments/completePayment', ['commerceTransactionId' => $transaction->id, 'commerceTransactionHash' => $transaction->hash]);
            $url = htmlspecialchars($url, ENT_QUOTES);

            $template = <<<EOF
<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="refresh" content="1;URL=$url" />
    <title>Redirecting...</title>
</head>
<body onload="document.payment.submit();">
    <p>Please wait while we redirect you back...</p>
    <form name="payment" action="$url" method="post">
        <p><input type="submit" value="Continue" /></p>
    </form>
</body>
</html>
EOF;

            ob_start();
            echo $template;
            Craft::$app->end();
        }

        if ($success && $redirect && $transaction->status == TransactionRecord::STATUS_REDIRECT) {
            Craft::$app->getRequest()->redirect($redirect);
        }

        return $success;
    }

    /**
     * This is a special handler for gateway which support the notification API in Omnipay.
     * TODO: Currently only tested with SagePay. Will likely need to be modified for other gateways with different notification API
     *
     * @param $transactionHash
     */
    public function acceptNotification($transactionHash)
    {
        $transaction = Plugin::getInstance()->getTransactions()->getTransactionByHash($transactionHash);

        // We need to turn devMode off because the gateways return text strings and we don't want `<script..` tags appending on the end.
        Craft::$app->getConfig()->set('devMode', false);

        // load payment driver
        $gateway = $transaction->paymentMethod->getGateway();

        $request = $gateway->acceptNotification();

        $request->setTransactionReference($transaction->reference);

        $response = $request->send();

        if (!$request->isValid()) {
            $url = UrlHelper::getSiteUrl($transaction->order->cancelUrl);
            Craft::error('Notification request is not valid: '.json_encode($request->getData(), JSON_PRETTY_PRINT), __METHOD__);
            $response->invalid($url, 'Signature not valid - goodbye');
        }

        // All raw data - just log it for later analysis:
        $request->getData();

        $status = $request->getTransactionStatus();

        if ($status == $request::STATUS_COMPLETED) {
            $transaction->status = TransactionRecord::STATUS_SUCCESS;
        } elseif ($status == $request::STATUS_PENDING) {
            $transaction->pending = TransactionRecord::STATUS_SUCCESS;
        } elseif ($status == $request::STATUS_FAILED) {
            $transaction->status = TransactionRecord::STATUS_FAILED;
        }

        $transaction->response = $response->getData();
        $transaction->code = $response->getCode();
        $transaction->reference = $request->getTransactionReference();
        $transaction->message = $request->getMessage();
        $this->saveTransaction($transaction);

        if ($transaction->status == TransactionRecord::STATUS_SUCCESS) {
            Plugin::getInstance()->getOrders()->updateOrderPaidTotal($transaction->order);
        }

        $url = UrlHelper::getActionUrl('commerce/payments/completePayment', [
            'commerceTransactionId' => $transaction->id,
            'commerceTransactionHash' => $transaction->hash
        ]);

        Craft::info('Confirming Notification: '.json_encode($request->getData(), JSON_PRETTY_PRINT), __METHOD__);

        $response->confirm($url);
    }

    /**
     *
     * Gets the total transactions amount really paid (not authorized)
     *
     * @param Order $order
     *
     * @return float
     */
    public function getTotalPaidForOrder(Order $order)
    {
        $transaction = (new Query())
            ->select('sum(amount) AS total, orderId')
            ->from(['{{%commerce_transactions}}'])
            ->where([
                'orderId' => $order->id,
                'status' => TransactionRecord::STATUS_SUCCESS
            ])
            ->andWhere('type', [TransactionRecord::TYPE_PURCHASE, TransactionRecord::TYPE_CAPTURE])
            ->groupBy('orderId')
            ->all();

        if ($transaction) {

            return $transaction['total'];
        }

        return 0;
    }

    /**
     * Gets the total transactions amount with authorized
     *
     * @param Order $order
     *
     * @return float
     */
    public function getTotalAuthorizedForOrder(Order $order)
    {
        $transaction = (new Query())
            ->select('sum(amount) AS total, orderId')
            ->from(['{{%commerce_transactions}}'])
            ->where([
                'orderId' => $order->id,
                'status' => TransactionRecord::STATUS_SUCCESS
            ])
            ->andWhere('type', [TransactionRecord::TYPE_AUTHORIZE, TransactionRecord::TYPE_PURCHASE, TransactionRecord::TYPE_CAPTURE])
            ->groupBy('orderId')
            ->all();

        if ($transaction) {
            return $transaction['total'];
        }

        return 0;
    }
}
