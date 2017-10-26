<?php

namespace craft\commerce\base;

use Craft;
use craft\base\SavableComponent;
use craft\commerce\models\payments\BasePaymentForm;
use craft\helpers\StringHelper;
use craft\helpers\UrlHelper;

/**
 * Class Gateway
 *
 * @package   Craft
 *
 * @property int                  $id
 * @property string               $type
 * @property string               $name
 * @property string               $paymentType
 * @property array                $settings
 * @property bool                 $frontendEnabled
 * @property bool                 $sendCartInfo
 * @property bool                 $isArchived
 * @property array                $paymentTypeOptions
 * @property null|BasePaymentForm $paymentFormModel
 * @property string               $cpEditUrl
 * @property bool                 $dateArchived
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

    // TODO itembags are totally an Omnipay thing.
    // TODO make sure Stripe implements non-omnipay events.
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

    /**
     * @event SendPaymentRequestEvent The event that is triggered right before a payment request is being sent
     */
    const EVENT_BEFORE_SEND_PAYMENT_REQUEST = 'beforeSendPaymentRequest';

    // Traits
    // =========================================================================

    use GatewayTrait;

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
     * Returns the webhook url for this gateway.
     *
     * @param array $params Parameters for the url.
     *
     * @return string
     */
    public function getWebhookUrl(array $params = []): string
    {
        $params = array_merge(['gateway' => $this->id], $params);

        $url = UrlHelper::actionUrl('commerce/webhooks/process-webhook', $params);

        return StringHelper::replace($url, Craft::$app->getConfig()->getGeneral()->cpTrigger.'/', '');
    }

    /**
     * Whether this gateway allows payments in control panel.
     *
     * @return bool
     */
    public function cpPaymentsEnabled(): bool
    {
        return true;
    }

    /**
     * @return string
     */
    public function getCpEditUrl(): string
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
    public function getPaymentTypeOptions(): array
    {
        return [
            'authorize' => Craft::t('commerce', 'Authorize Only (Manually Capture)'),
            'purchase' => Craft::t('commerce', 'Purchase (Authorize and Capture Immediately)'),
        ];
    }

    /**
     * @inheritdoc
     */
    public function rule(): array
    {
        return [
            [['paymentType'], 'required']
        ];
    }
}
