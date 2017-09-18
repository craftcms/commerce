<?php

namespace craft\commerce\base;

use Craft;
use craft\base\SavableComponent;
use craft\commerce\models\payments\BasePaymentForm;
use craft\helpers\StringHelper;
use craft\helpers\UrlHelper;

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
    public function getWebhookUrl(array $params = [])
    {
        $params = array_merge(['gateway' => $this->id], $params);

        $url = UrlHelper::actionUrl('commerce/webhooks/process-webhook', $params);

        return StringHelper::replace($url, Craft::$app->getConfig()->getGeneral()->cpTrigger.'/', '');
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
     * @inheritdoc
     */
    public function rule()
    {
        return [
            [['paymentType'], 'required']
        ];
    }
}
