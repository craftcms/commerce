<?php

namespace craft\commerce\services;

use Craft;
use craft\commerce\base\RequestResponseInterface;
use craft\commerce\elements\Order;
use craft\commerce\events\GatewayRequestEvent;
use craft\commerce\events\TransactionEvent;
use craft\commerce\base\Gateway;
use craft\commerce\models\payments\BasePaymentForm;
use craft\commerce\models\Transaction;
use craft\commerce\Plugin;
use craft\commerce\records\Transaction as TransactionRecord;
use craft\db\Query;
use craft\errors\GatewayRequestCancelledException;
use craft\helpers\DateTimeHelper;
use craft\helpers\UrlHelper;
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
     * @param Order           $order
     * @param BasePaymentForm $form
     * @param string|null     &$redirect
     * @param string|null     &$customError
     *
     * @return bool
     * @throws Exception
     * @throws \Exception
     */
    public function processPayment(Order $order, BasePaymentForm $form, &$redirect = null, &$customError = null) {
        // Order could have zero totalPrice and already considered 'paid'. Free orders complete immediately.
        if ($order->isPaid()) {
            if (!$order->datePaid) {
                $order->datePaid = DateTimeHelper::currentTimeStamp();
            }

            if (!$order->isCompleted) {
                $order->markAsComplete();
            }

            return true;
        }

        /** @var Gateway $gateway */
        $gateway = $order->getGateway();

        //choosing default action
        $defaultAction = $gateway->paymentType;
        $defaultAction = ($defaultAction === TransactionRecord::TYPE_PURCHASE) ? $defaultAction : TransactionRecord::TYPE_AUTHORIZE;

        if ($defaultAction === TransactionRecord::TYPE_AUTHORIZE) {
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

        try {
            /** @var RequestResponseInterface $response */
            switch ($defaultAction) {
                case TransactionRecord::TYPE_PURCHASE:
                    $response = $gateway->purchase($transaction, $form);
                    break;
                case TransactionRecord::TYPE_AUTHORIZE:
                    $response = $gateway->authorize($transaction, $form);
                    break;
            }

            $this->updateTransaction($transaction, $response);

            if ($response->isRedirect()) {
                return $this->_handleRedirect($response, $redirect);
            }

            if ($transaction->status !== TransactionRecord::STATUS_SUCCESS) {
                $customError = $transaction->message;
                return false;
            }

            $success = true;
        } catch (GatewayRequestCancelledException $e) {
            $transaction->status = TransactionRecord::STATUS_FAILED;
            $this->saveTransaction($transaction);
            $success = false;
        } catch (\Exception $e) {
            $transaction->status = TransactionRecord::STATUS_FAILED;
            $this->saveTransaction($transaction);
            $success = false;
            $customError = $e->getMessage();

            if (!$e instanceof GatewayRequestCancelledException) {
                Craft::error($e->getMessage());
            }
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
     * Updates a transaction.
     * 
     * @param Transaction       $transaction
     * @param RequestResponseInterface $response
     * 
     * @return void
     */
    private function updateTransaction(Transaction $transaction, RequestResponseInterface $response) {
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
        // Raise 'beforeCaptureTransaction' event
        if ($this->hasEventHandlers(self::EVENT_BEFORE_CAPTURE_TRANSACTION)) {
            $this->trigger(self::EVENT_BEFORE_CAPTURE_TRANSACTION, new TransactionEvent([
                'transaction' => $transaction
            ]));
        }

        $transaction = $this->processCaptureOrRefund($transaction, TransactionRecord::TYPE_CAPTURE);

        // Raise 'afterCaptureTransaction' event
        if ($this->hasEventHandlers(self::EVENT_AFTER_CAPTURE_TRANSACTION)) {
            $this->trigger(self::EVENT_AFTER_CAPTURE_TRANSACTION, new TransactionEvent([
                'transaction' => $transaction
            ]));
        }

        return $transaction;
    }

    /**
     * @param Transaction $parent
     * @param string      $action
     *
     * @return Transaction
     * @throws Exception
     */
    private function processCaptureOrRefund(Transaction $parent, $action ) {
        if (!in_array($action, [TransactionRecord::TYPE_CAPTURE, TransactionRecord::TYPE_REFUND], false)) {
            throw new Exception('Wrong action: '.$action);
        }

        $order = $parent->order;
        $child = Plugin::getInstance()->getTransactions()->createTransaction($order);
        $child->parentId = $parent->id;
        $child->gatewayId = $parent->gatewayId;
        $child->type = $action;
        $child->amount = $parent->amount;
        $child->paymentAmount = $parent->paymentAmount;
        $child->currency = $parent->currency;
        $child->paymentCurrency = $parent->paymentCurrency;
        $child->paymentRate = $parent->paymentRate;
        $this->saveTransaction($child);

        $gateway = $parent->getGateway();
        $request = $gateway->$action($this->buildPaymentRequest($child));
        $request->setTransactionReference($parent->reference);

        $order->returnUrl = $order->getCpEditUrl();
        Craft::$app->getElements()->saveElement($order);

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
        // Raise 'beforeRefundTransaction' event
        if ($this->hasEventHandlers(self::EVENT_BEFORE_REFUND_TRANSACTION)) {
            $this->trigger(self::EVENT_BEFORE_REFUND_TRANSACTION, new TransactionEvent([
                'transaction' => $transaction
            ]));
        }

        $transaction = $this->processCaptureOrRefund($transaction, TransactionRecord::TYPE_REFUND);

        /// Raise 'afterRefundTransaction' event
        if ($this->hasEventHandlers(self::EVENT_AFTER_REFUND_TRANSACTION)) {
            $this->trigger(self::EVENT_AFTER_REFUND_TRANSACTION, new TransactionEvent([
                'transaction' => $transaction
            ]));
        }

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
            $transaction->order->updateOrderPaidTotal();
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
            $transaction->order->updateOrderPaidTotal();
        }

        $url = UrlHelper::actionUrl('commerce/payments/completePayment', [
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
                'status' => TransactionRecord::STATUS_SUCCESS,
                'type' => [TransactionRecord::TYPE_PURCHASE, TransactionRecord::TYPE_CAPTURE]
            ])
            ->groupBy('orderId')
            ->one();

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
                'status' => TransactionRecord::STATUS_SUCCESS,
                'type' => [TransactionRecord::TYPE_AUTHORIZE, TransactionRecord::TYPE_PURCHASE, TransactionRecord::TYPE_CAPTURE]
            ])
            ->groupBy('orderId')
            ->one();

        if ($transaction) {
            return $transaction['total'];
        }

        return 0;
    }

    /**
     * Handle a redirect.
     *
     * @param RequestResponseInterface $response
     * @param string|null              $redirect
     */
    private function _handleRedirect(RequestResponseInterface $response, &$redirect = null)
    {
        // redirect to off-site gateway
        if ($response->getRedirectMethod() === 'GET') {
            $redirect = $response->getRedirectUrl();
        } else {

            $gatewayPostRedirectTemplate = Plugin::getInstance()->getSettings()->gatewayPostRedirectTemplate;

            if (!empty($gatewayPostRedirectTemplate)) {
                $variables = [];
                $hiddenFields = '';

                // Gather all post hidden data inputs.
                foreach ($response->getRedirectData() as $key => $value) {
                    $hiddenFields .= sprintf('<input type="hidden" name="%1$s" value="%2$s" />', htmlentities($key, ENT_QUOTES, 'UTF-8', false), htmlentities($value, ENT_QUOTES, 'UTF-8', false) )."\n";
                }

                $variables['inputs'] = $hiddenFields;

                // Set the action url to the responses redirect url
                $variables['actionUrl'] = $response->getRedirectUrl();

                // Set Craft to the site template mode
                $templatesService = Craft::$app->getView();
                $oldTemplateMode = $templatesService->getTemplateMode();
                $templatesService->setTemplateMode($templatesService::TEMPLATE_MODE_SITE);

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
    }
}
