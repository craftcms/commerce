<?php

namespace craft\commerce\base;

use Craft;
use craft\base\SavableComponent;
use craft\commerce\elements\Order;
use craft\commerce\events\GatewayRequestEvent;
use craft\commerce\events\ItemBagEvent;
use craft\commerce\helpers\Currency;
use craft\commerce\models\LineItem;
use craft\commerce\models\OrderAdjustment;
use craft\commerce\models\payments\BasePaymentForm;
use craft\commerce\models\Transaction;
use craft\errors\GatewayRequestCancelledException;
use craft\helpers\UrlHelper;
use yii\base\NotSupportedException;

/**
 * Class Payment Method Model
 *
 * @package   Craft
 *
 * @property int                $id
 * @property string             $class
 * @property string             $name
 * @property string             $paymentType
 * @property array              $settings
 * @property bool               $frontendEnabled
 * @property bool               $sendCartInfo
 * @property bool               $isArchived
 * @property bool               $dateArchived
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2017, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.commerce
 * @since     2.0
 */
abstract class Gateway extends SavableComponent implements GatewayInterface
{
    // Constants
    // =========================================================================

    /**
     * @event ItemBagEvent The event that is triggered after an item bag is created
     */
    const EVENT_AFTER_CREATE_ITEM_BAG = 'afterCreateItemBag';

    /**
     * @event GatewayRequestEvent The event that is triggered before a gateway request is sent
     *
     * You may set [[GatewayRequestEvent::isValid]] to `false` to prevent the request from being sent.
     */
    const EVENT_BEFORE_GATEWAY_REQUEST_SEND = 'beforeGatewayRequestSend';

    // Traits
    // =========================================================================

    use GatewayTrait;

    // Protected methods
    // =========================================================================

    /**
     * Create a gateway specific item bag for the order.
     *
     * @param Order $order The order.
     *
     * @return mixed
     */
    protected function createItemBagForOrder(Order $order)
    {
        return $this->getItemListForOrder($order);
    }

    /**
     * Get the item bag for the order.
     *
     * @param Order $order
     *
     * @return mixed
     */
    protected function getItemBagForOrder(Order $order)
    {
        $itemBag = $this->createItemBagForOrder($order);

        $event = new ItemBagEvent([
            'items' => $itemBag,
            'order' => $order
        ]);
        $this->trigger(self::EVENT_AFTER_CREATE_ITEM_BAG, $event);

        return $event->items;
    }
    
    /**
     * Generate the item list for an Order.
     *
     * @param Order $order
     *
     * @return array
     */
    protected function getItemListForOrder(Order $order): array
    {
        $items = [];

        $priceCheck = 0;
        $count = -1;

        /** @var LineItem $item */
        foreach ($order->lineItems as $item) {
            $price = Currency::round($item->salePrice);
            // Can not accept zero amount items. See item (4) here:
            // https://developer.paypal.com/docs/classic/express-checkout/integration-guide/ECCustomizing/#setting-order-details-on-the-paypal-review-page

            if ($price !== 0) {
                $count++;
                /** @var Purchasable $purchasable */
                $purchasable = $item->getPurchasable();
                $defaultDescription = Craft::t('commerce', 'Item ID').' '.$item->id;
                $purchasableDescription = $purchasable ? $purchasable->getDescription() : $defaultDescription;
                $description = isset($item->snapshot['description']) ? $item->snapshot['description'] : $purchasableDescription;
                $description = empty($description) ? 'Item '.$count : $description;
                $items[] = [
                    'name' => $description,
                    'description' => $description,
                    'quantity' => $item->qty,
                    'price' => $price,
                ];

                $priceCheck += ($item->qty * $item->salePrice);
            }
        }

        $count = -1;

        /** @var OrderAdjustment $adjustment */
        foreach ($order->adjustments as $adjustment) {
            $price = Currency::round($adjustment->amount);

            // Do not include the 'included' adjustments, and do not send zero value items
            // See item (4) https://developer.paypal.com/docs/classic/express-checkout/integration-guide/ECCustomizing/#setting-order-details-on-the-paypal-review-page
            if (($adjustment->included == 0 || $adjustment->included == false) && $price !== 0) {
                $count++;
                $items[] = [
                    'name' => empty($adjustment->name) ? $adjustment->type." ".$count : $adjustment->name,
                    'description' => empty($adjustment->description) ? $adjustment->type.' '.$count : $adjustment->description,
                    'quantity' => 1,
                    'price' => $price,
                ];
                $priceCheck += $adjustment->amount;
            }
        }

        $priceCheck = Currency::round($priceCheck);
        $totalPrice = Currency::round($order->totalPrice);
        $same = (bool)($priceCheck === $totalPrice);

        if (!$same) {
            Craft::error('Item bag total price does not equal the orders totalPrice, some payment gateways will complain.', __METHOD__);
        }

        return $items;
    }

    /**
     * Prepare a request for execution by transaction and a populated payment form.
     *
     * @param Transaction     $transaction
     * @param BasePaymentForm $form        Optional for capture/refund requests.
     *
     * @return mixed
     */
    // TODO seems that "createRequest" would be a better name here.
    abstract protected function getRequest(Transaction $transaction, BasePaymentForm $form = null);

    /**
     * Perform a request and return the response.
     *
     * @param $request
     * @param $transaction
     *
     * @return RequestResponseInterface
     * @throws GatewayRequestCancelledException
     */
    protected function performRequest($request, $transaction)
    {
        //raising event
        $event = new GatewayRequestEvent([
            'type' => $transaction->type,
            'request' => $request,
            'transaction' => $transaction
        ]);

        // Raise 'beforeGatewayRequestSend' event
        $this->trigger(self::EVENT_BEFORE_GATEWAY_REQUEST_SEND, $event);

        if (!$event->isValid) {
            throw new GatewayRequestCancelledException(Craft::t('commerce', 'The gateway request was cancelled!'));
        }

        $response = $this->sendRequest($request);

        return $this->prepareResponse($response);
    }

    /**
     * Prep request to be used as an authorize request.
     *
     * @param mixed $request
     *
     * @return mixed
     */
    abstract protected function prepareAuthorizeRequest($request);

    /**
     * Prep request to be used as a completing authorization request.
     *
     * @param        $request
     *
     * @return mixed
     */
    abstract protected function prepareCompleteAuthorizeRequest($request);

    /**
     * Prep request to be used as a completing purchase request.
     *
     * @param        $request
     *
     * @return mixed
     */
    abstract protected function prepareCompletePurchaseRequest($request);

    /**
     * Prep request to be used as a capture request.
     *
     * @param        $request
     * @param string $reference Reference for the transaction to be captured
     *
     * @return mixed
     */
    abstract protected function prepareCaptureRequest($request, string $reference);

    /**
     * Prep request to be used as a purchase request.
     *
     * @param mixed $request
     *
     * @return mixed
     */
    abstract protected function preparePurchaseRequest($request);

    /**
     * Prep request to be used as a refund request.
     * 
     * @param        $request
     * @param string $reference Reference for the transaction to be refunded
     *
     * @return mixed
     */
    abstract protected function prepareRefundRequest($request, string $reference);

    /**
     * Prepare a gateway's response to fit the interface.
     *
     * @param mixed $response
     *
     * @return RequestResponseInterface
     */
    abstract protected function prepareResponse($response): RequestResponseInterface;

    /**
     * Send the request to gateway
     *
     * @param mixed $request
     *
     * @return mixed
     */
    abstract protected function sendRequest($request);

    // Public methods
    // =========================================================================

    /**
     * Returns the name of this payment method.
     *
     * @return string
     */
    public function __toString()
    {
        return (string)$this->name;
    }

    /**
     * @inheritdocs
     */
    public function authorize(Transaction $transaction, BasePaymentForm $form): RequestResponseInterface
    {
        if (!$this->supportsAuthorize()) {
            throw new NotSupportedException(Craft::t('commerce', 'Authorizing is not supported by this gateway'));
        }

        $request = $this->getRequest($transaction, $form);
        $authorizeRequest = $this->prepareAuthorizeRequest($request);

        return $this->performRequest($authorizeRequest, $transaction);
    }

    /**
     * @inheritdoc
     */
    public function capture(Transaction $transaction, string $reference): RequestResponseInterface
    {
        if (!$this->supportsCapture()) {
            throw new NotSupportedException(Craft::t('commerce', 'Capturing is not supported by this gateway'));
        }

        $request = $this->getRequest($transaction);
        $captureRequest = $this->prepareCaptureRequest($request, $reference);

        return $this->performRequest($captureRequest, $transaction);
    }

    /**
     * @inheritdoc
     */
    public function completeAuthorize(Transaction $transaction): RequestResponseInterface
    {
        if (!$this->supportsCompleteAuthorize()) {
            throw new NotSupportedException(Craft::t('commerce', 'Completing authorization is not supported by this gateway'));
        }

        $request = $this->getRequest($transaction);
        $completeRequest = $this->prepareCompleteAuthorizeRequest($request);

        return $this->performRequest($completeRequest, $transaction);
    }

    /**
     * @inheritdoc
     */
    public function completePurchase(Transaction $transaction): RequestResponseInterface
    {
        if (!$this->supportsCompletePurchase()) {
            throw new NotSupportedException(Craft::t('commerce', 'Completing purchase is not supported by this gateway'));
        }

        $request = $this->getRequest($transaction);
        $completeRequest = $this->prepareCompletePurchaseRequest($request);

        return $this->performRequest($completeRequest, $transaction);
    }

    /**
     * Whether this gateway allows pamyents in control panel.
     *
     * @return bool
     */
    public function cpPaymentsEnabled()
    {
        return true;
    }

    /**
     * @return string
     */
    public function getCpEditUrl()
    {
        return UrlHelper::cpUrl('commerce/settings/gateways/'.$this->id);
    }

    /**
     * Payment Form HTML
     *
     * @param array $params
     *
     * @return string|null
     */
    abstract public function getPaymentFormHtml(array $params);

    /**
     * Payment Form HTML
     *
     * @return BasePaymentForm|null
     */
    abstract public function getPaymentFormModel();

    /**
     * Return the payment type options.
     *
     * @return array
     */
    public function getPaymentTypeOptions()
    {
        return [
            'authorize' => Craft::t('commerce', 'Authorize Only (Manually Capture)'),
            'purchase' => Craft::t('commerce', 'Purchase (Authorize and Capture Immediately)'),
        ];
    }

    /**
     * @inheritdocs
     */
    public function purchase(Transaction $transaction, BasePaymentForm $form): RequestResponseInterface
    {
        if (!$this->supportsPurchase()) {
            throw new NotSupportedException(Craft::t('commerce', 'Purchasing is not supported by this gateway'));
        }
        
        $request = $this->getRequest($transaction, $form);
        $purchaseRequest = $this->preparePurchaseRequest($request);

        return $this->performRequest($purchaseRequest, $transaction);
    }

    /**
     * @inheritdoc
     */
    public function refund(Transaction $transaction, string $reference): RequestResponseInterface
    {
        if (!$this->supportsRefund()) {
            throw new NotSupportedException(Craft::t('commerce', 'Refunding is not supported by this gateway'));
        }

        $request = $this->getRequest($transaction);
        $refundRequest = $this->prepareRefundRequest($request, $reference);

        return $this->performRequest($refundRequest, $transaction);
    }
    
    /**
     * @inheritdoc
     */
    public function rule()
    {
        return [
            [['paymentType'], 'required']
        ];
    }

    /**
     * I have no idea.
     *
     * @return bool
     */
    public function useNotifyUrl() {
        return false;
    }
}
