<?php

namespace craft\commerce\services;

use Craft;
use craft\commerce\base\Gateway;
use craft\commerce\base\RequestResponseInterface;
use craft\commerce\elements\Order;
use craft\commerce\errors\GatewayRequestCancelledException;
use craft\commerce\errors\PaymentException;
use craft\commerce\errors\TransactionException;
use craft\commerce\events\ProcessPaymentEvent;
use craft\commerce\events\TransactionEvent;
use craft\commerce\models\payments\BasePaymentForm;
use craft\commerce\models\Transaction;
use craft\commerce\Plugin;
use craft\commerce\records\Transaction as TransactionRecord;
use craft\db\Query;
use craft\helpers\Db;
use yii\base\Component;

/**
 * Payments service.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class Payments extends Component
{
    // Constants
    // =========================================================================

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
     * @event ProcessPaymentEvent The event is triggered before a payment is being processed
     */
    const EVENT_BEFORE_PROCESS_PAYMENT_EVENT = 'beforeProcessPaymentEvent';

    // Public Methods
    // =========================================================================

    /**
     * @param Order $order
     * @param BasePaymentForm $form
     * @param string|null &$redirect
     * @param Transaction|null &$transaction
     * @return bool
     * @throws \Exception
     */
    public function processPayment(Order $order, BasePaymentForm $form, &$redirect, &$transaction): bool
    {
        // Raise the 'beforeProcessPaymentEvent' event
        $event = new ProcessPaymentEvent([
            'order' => $order,
        ]);

        $this->trigger(self::EVENT_BEFORE_PROCESS_PAYMENT_EVENT, $event);

        if (!$event->isValid) {
            // This error potentially is going to be displayed in the frontend, so we have to be vague about it.
            // Long story short - a plugin said "no."
            throw new PaymentException(Craft::t('commerce', 'Unable to make payment at this time.'));
        }

        // Order could have zero totalPrice and already considered 'paid'. Free orders complete immediately.
        if ($order->isPaid()) {
            if (!$order->datePaid) {
                $order->datePaid = Db::prepareDateForDb(new \DateTime());
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
                throw new PaymentException(Craft::t('commerce', 'Gateway doesn’t support authorize'));
            }
        } else {
            if (!$gateway->supportsPurchase()) {
                throw new PaymentException(Craft::t('commerce', 'Gateway doesn’t support purchase'));
            }
        }

        //creating order, transaction and request
        $transaction = Plugin::getInstance()->getTransactions()->createTransaction($order);
        $transaction->type = $defaultAction;

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

            $this->_updateTransaction($transaction, $response);

            // For redirects or unsuccessful transactions, save the transaction before bailing
            if ($response->isRedirect()) {
                return $this->_handleRedirect($response, $redirect);
            }

            if ($transaction->status !== TransactionRecord::STATUS_SUCCESS) {
                throw new PaymentException($transaction->message);
            }

            // Success!
            $order->updateOrderPaidTotal();
            $success = true;
        } catch (GatewayRequestCancelledException $e) {
            $transaction->status = TransactionRecord::STATUS_FAILED;
            $transaction->message = $e->getMessage();
            $this->_saveTransaction($transaction);
            $success = false;
        } catch (\Exception $e) {
            $transaction->status = TransactionRecord::STATUS_FAILED;
            $transaction->message = $e->getMessage();

            // If this transactions is already saved, don't even try.
            if (!$transaction->id) {
                $this->_saveTransaction($transaction);
            }

            Craft::error($e->getMessage());
            throw new PaymentException($transaction->message);
        }

        return $success;
    }

    /**
     * @param Transaction $transaction
     * @return Transaction
     */
    public function captureTransaction(Transaction $transaction): Transaction
    {
        // Raise 'beforeCaptureTransaction' event
        if ($this->hasEventHandlers(self::EVENT_BEFORE_CAPTURE_TRANSACTION)) {
            $this->trigger(self::EVENT_BEFORE_CAPTURE_TRANSACTION, new TransactionEvent([
                'transaction' => $transaction
            ]));
        }

        $transaction = $this->_captureTransaction($transaction);

        // Raise 'afterCaptureTransaction' event
        if ($this->hasEventHandlers(self::EVENT_AFTER_CAPTURE_TRANSACTION)) {
            $this->trigger(self::EVENT_AFTER_CAPTURE_TRANSACTION, new TransactionEvent([
                'transaction' => $transaction
            ]));
        }

        return $transaction;
    }

    /**
     * @param Transaction $transaction
     * @param float|null $amount
     * @return Transaction
     */
    public function refundTransaction(Transaction $transaction, $amount = null): Transaction
    {
        // Raise 'beforeRefundTransaction' event
        if ($this->hasEventHandlers(self::EVENT_BEFORE_REFUND_TRANSACTION)) {
            $this->trigger(self::EVENT_BEFORE_REFUND_TRANSACTION, new TransactionEvent([
                'transaction' => $transaction
            ]));
        }

        $transaction = $this->_refund($transaction, $amount);

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
     * @return bool
     */
    public function completePayment(Transaction $transaction, &$customError): bool
    {
        // Only transactions with the status of "redirect" can be completed
        if (!in_array($transaction->status, [TransactionRecord::STATUS_REDIRECT, TransactionRecord::STATUS_SUCCESS], true)) {
            $customError = $transaction->message;

            return false;
        }

        // If it's successful already, we're good.
        if (Plugin::getInstance()->getTransactions()->isTransactionSuccessful($transaction)) {
            return true;
        }

        // Load payment driver for the transaction we are trying to complete
        $gateway = $transaction->getGateway();

        switch ($transaction->type) {
            case TransactionRecord::TYPE_PURCHASE:
                $response = $gateway->completePurchase($transaction);
                break;
            case TransactionRecord::TYPE_AUTHORIZE:
                $response = $gateway->completeAuthorize($transaction);
                break;
            default:
                return false;
        }

        $childTransaction = Plugin::getInstance()->getTransactions()->createTransaction(null, $transaction);
        $childTransaction->type = $transaction->type;
        $this->_updateTransaction($childTransaction, $response);

        // Success can mean 2 things in this context.
        // 1) The transaction completed successfully with the gateway, and is now marked as complete.
        // 2) The result of the gateway request was successful but also got a redirect response. We now need to redirect if $redirect is not null.
        $success = $response->isSuccessful() || $response->isProcessing();

        if ($success && $transaction->status === TransactionRecord::STATUS_SUCCESS) {
            $transaction->order->updateOrderPaidTotal();
        }

        if ($success && $transaction->status === TransactionRecord::STATUS_PROCESSING) {
            $transaction->order->markAsComplete();
        }

        if ($response->isRedirect() && $transaction->status === TransactionRecord::STATUS_REDIRECT) {
            $this->_handleRedirect($response, $redirect);
            Craft::$app->getResponse()->redirect($redirect);
            Craft::$app->end();
        }

        return $success;
    }

    /**
     * Gets the total transactions amount really paid (not authorized).
     *
     * @param Order $order
     * @return float
     */
    public function getTotalPaidForOrder(Order $order): float
    {
        return (float)(new Query())
            ->from(['{{%commerce_transactions}}'])
            ->where([
                'orderId' => $order->id,
                'status' => TransactionRecord::STATUS_SUCCESS,
                'type' => [TransactionRecord::TYPE_PURCHASE, TransactionRecord::TYPE_CAPTURE]
            ])
            ->sum('amount');

        if (!$paid) {
            return 0;
        }

        return $paid;
    }

    /**
     * Gets the total transactions amount with authorized.
     *
     * @param Order $order
     * @return float
     */
    public function getTotalAuthorizedForOrder(Order $order): float
    {
        return (float)(new Query())
            ->from(['{{%commerce_transactions}}'])
            ->where([
                'orderId' => $order->id,
                'status' => TransactionRecord::STATUS_SUCCESS,
                'type' => [TransactionRecord::TYPE_AUTHORIZE, TransactionRecord::TYPE_PURCHASE, TransactionRecord::TYPE_CAPTURE]
            ])
            ->sum('amount');
    }

    // Private Methods
    // =========================================================================

    /**
     * Handles a redirect.
     *
     * @param RequestResponseInterface $response
     * @param                          $redirect
     * @return mixed
     */
    private function _handleRedirect(RequestResponseInterface $response, &$redirect)
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
                    $hiddenFields .= sprintf('<input type="hidden" name="%1$s" value="%2$s" />', htmlentities($key, ENT_QUOTES, 'UTF-8', false), htmlentities($value, ENT_QUOTES, 'UTF-8', false))."\n";
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

        return true;
    }

    /**
     * Process a capture or refund exception.
     *
     * @param Transaction $parent
     * @param string $action
     * @return Transaction
     * @throws TransactionException
     */
    private function _capture(Transaction $parent): Transaction
    {
        $order = $parent->order;
        $child = Plugin::getInstance()->getTransactions()->createTransaction(null, $parent);
        $child->type = TransactionRecord::TYPE_CAPTURE;

        $gateway = $parent->getGateway();

        $order->returnUrl = $order->getCpEditUrl();
        Craft::$app->getElements()->saveElement($order);

        try {
            $response = $gateway->capture($child, $parent->reference);
            $this->_updateTransaction($child, $response);
        } catch (\Exception $e) {
            $child->status = TransactionRecord::STATUS_FAILED;
            $child->message = $e->getMessage();
            $this->_saveTransaction($child);

            Craft::error($e->getMessage());
        }

        return $child;
    }

    /**
     * Process a capture or refund exception.
     *
     * @param Transaction $parent
     * @param float|null $amount
     * @return Transaction
     * @throws TransactionException
     */
    private function _refund(Transaction $parent, $amount): Transaction
    {
        $order = $parent->order;
        $child = Plugin::getInstance()->getTransactions()->createTransaction(null, $parent);
        $child->type = TransactionRecord::TYPE_REFUND;
        $child->paymentAmount = ($amount * $parent->paymentRate);
        $child->amount = $amount;

        $gateway = $parent->getGateway();

        $order->returnUrl = $order->getCpEditUrl();
        Craft::$app->getElements()->saveElement($order);

        try {
            $response = $gateway->refund($child, $child->paymentAmount);
            $this->_updateTransaction($child, $response);
        } catch (\Exception $e) {
            $child->status = TransactionRecord::STATUS_FAILED;
            $child->message = $e->getMessage();
            $this->_saveTransaction($child);

            Craft::error($e->getMessage());
        }

        return $child;
    }

    /**
     * @param Transaction $child
     * @throws TransactionException
     */
    private function _saveTransaction($child)
    {
        if (!Plugin::getInstance()->getTransactions()->saveTransaction($child)) {
            throw new TransactionException('Error saving transaction: '.implode(', ', $child->errors));
        }
    }

    /**
     * Updates a transaction.
     *
     * @param Transaction $transaction
     * @param RequestResponseInterface $response
     */
    private function _updateTransaction(Transaction $transaction, RequestResponseInterface $response)
    {
        if ($response->isRedirect()) {
            $transaction->status = TransactionRecord::STATUS_REDIRECT;
        } elseif ($response->isSuccessful()) {
            $transaction->status = TransactionRecord::STATUS_SUCCESS;
        } elseif ($response->isProcessing()) {
            $transaction->status = TransactionRecord::STATUS_PROCESSING;
        } else {
            $transaction->status = TransactionRecord::STATUS_FAILED;
        }

        $transaction->response = $response->getData();
        $transaction->code = $response->getCode();
        $transaction->reference = $response->getTransactionReference();
        $transaction->message = $response->getMessage();

        $this->_saveTransaction($transaction);
    }
}
