<?php

namespace craft\commerce\base;

use Craft;
use craft\base\SavableComponent;
use craft\commerce\elements\Order;
use craft\commerce\events\ItemBagEvent;
use craft\commerce\helpers\Currency;
use craft\commerce\models\LineItem;
use craft\commerce\models\OrderAdjustment;
use craft\commerce\models\payments\BasePaymentForm;
use craft\commerce\models\payments\CreditCardPaymentForm;
use craft\commerce\Plugin;
use craft\commerce\services\Payments;
use craft\helpers\UrlHelper;
use Omnipay\Common\AbstractGateway;
use Omnipay\Common\CreditCard;
use Omnipay\Common\Message\AbstractRequest;
use Omnipay\Common\Message\RequestInterface;
use Omnipay\Omnipay;

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
    use GatewayTrait;

    /**
     * @var AbstractGateway
     */
    private $_gateway;

    /**
     * Return the OmniPay gateway class name.
     *
     * @return string|null
     */
    abstract protected function getGatewayClassName();

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
     * @param CreditCard            $card
     * @param CreditCardPaymentForm $paymentForm
     *
     * @return void
     */
    abstract public function populateCard(CreditCard $card, CreditCardPaymentForm $paymentForm);

    /**
     * @param AbstractRequest $request
     * @param BasePaymentForm $form
     *
     * @return void
     */
    abstract public function populateRequest(AbstractRequest $request, BasePaymentForm $form);

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
     * Returns the name of this payment method.
     *
     * @return string
     */
    public function __toString()
    {
        return (string)$this->name;
    }

    /**
     * @return string
     */
    public function getCpEditUrl()
    {
        return UrlHelper::cpUrl('commerce/settings/gateways/'.$this->id);
    }

    /**
     * Whether this gateway requires credit card details.
     *
     * @return bool
     */
    public function requiresCreditCard()
    {
        return false;
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
     * @inheritdoc
     */
    public function purchase(array $parameters): RequestInterface
    {
        return $this->gateway()->purchase($parameters);
    }

    /**
     * @inheritdoc
     */
    public function authorize(array $parameters): RequestInterface
    {
        return $this->gateway()->authorize($parameters);
    }

    /**
     * @inheritdoc
     */
    public function refund(array $parameters): RequestInterface
    {
        return $this->gateway()->refund($parameters);
    }

    /**
     * @inheritdoc
     */
    public function capture(array $parameters): RequestInterface
    {
        return $this->gateway()->capture($parameters);
    }

    /**
     * @inheritdoc
     */
    public function supportsPurchase(): bool
    {
        return $this->gateway()->supportsPurchase();
    }

    /**
     * @inheritdoc
     */
    public function supportsAuthorize(): bool
    {
        return $this->gateway()->supportsAuthorize();
    }

    /**
     * @inheritdoc
     */
    public function supportsRefund(): bool
    {
        return $this->gateway()->supportsRefund();
    }

    /**
     * @inheritdoc
     */
    public function supportsCapture(): bool
    {
        return $this->gateway()->supportsCapture();
    }

    /**
     * I have no idea.
     *
     * @return bool
     */
    public function useNotifyUrl() {
        return false;
    }

    public function createItemBag(Order $order)
    {
        if (!$this->canSendCartInfo) {
            return null;
        }

        $itemBagClassName = $this->getItemBagClassName();
        /** @var ItemBag $itemBag */
        $itemBag = new $itemBagClassName;

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
                $defaultDescription = Craft::t('commerce', 'Item ID')." ".$item->id;
                $purchasableDescription = $purchasable ? $purchasable->getDescription() : $defaultDescription;
                $description = isset($item->snapshot['description']) ? $item->snapshot['description'] : $purchasableDescription;
                $description = empty($description) ? "Item ".$count : $description;
                $itemBag->add([
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
                $itemBag->add([
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

        // Raise the 'afterCreateItemBag' event
        $payments = Plugin::getInstance()->getPayments();
        if ($payments->hasEventHandlers($payments::EVENT_AFTER_CREATE_ITEM_BAG))
        {
            $payments->trigger($payments::EVENT_AFTER_CREATE_ITEM_BAG, new ItemBagEvent([
                'items' => $itemBag,
                'order' => $order
            ]));
        }

        return $itemBag;
    }


    // Protected Methods
    // =========================================================================

    /**
     * Creates and returns an Omnipay gateway instance based on the stored settings.
     *
     * @return AbstractGateway The Omnipay gateway.
     */
    abstract protected function createGateway(): AbstractGateway;

    /**
     * @return AbstractGateway
     */
    protected function gateway(): AbstractGateway
    {
        if ($this->_gateway !== null) {
            return $this->_gateway;
        }

        return $this->_gateway = $this->createGateway();
    }

    /**
     * Return the class name used for item bags by this gateway.
     * 
     * @return string
     */
    protected function getItemBagClassName(): string {
        return ItemBag::class;
    }
}
