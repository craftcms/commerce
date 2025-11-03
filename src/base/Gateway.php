<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\base;

use Craft;
use craft\base\SavableComponent;
use craft\commerce\elements\conditions\addresses\GatewayAddressCondition;
use craft\commerce\elements\conditions\orders\DiscountOrderCondition;
use craft\commerce\elements\conditions\orders\GatewayOrderCondition;
use craft\commerce\elements\Order;
use craft\commerce\models\payments\BasePaymentForm;
use craft\commerce\models\Transaction;
use craft\elements\conditions\ElementConditionInterface;
use craft\helpers\Json;
use craft\helpers\StringHelper;
use craft\helpers\UrlHelper;

/**
 * Class Gateway
 *
 * @property string $cpEditUrl
 * @property bool|string|null $isFrontendEnabled
 * @property bool $isArchived
 * @property null|BasePaymentForm $paymentFormModel
 * @property string $paymentType
 * @property-read null|string $transactionHashFromWebhook
 * @property array $paymentTypeOptions
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
abstract class Gateway extends SavableComponent implements GatewayInterface
{
    use GatewayTrait;

    /**
     * @var ElementConditionInterface|null
     * @since 5.4.0
     */
    private ?ElementConditionInterface $_orderCondition = null;

    /**
     * @var ElementConditionInterface|null
     * @since 5.5
     */
    private ?ElementConditionInterface $_billingAddressCondition = null;

    /**
     * @var ElementConditionInterface|null
     * @since 5.5
     */
    private ?ElementConditionInterface $_shippingAddressCondition = null;

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
     * Shows the payment button on the payment form.
     *
     * @return bool
     */
    public function showPaymentFormSubmitButton(): bool
    {
        return true;
    }

    /**
     * Returns the webhook url for this gateway.
     *
     * @param array $params Parameters for the url.
     */
    public function getWebhookUrl(array $params = []): string
    {
        $params = array_merge(['gateway' => $this->id], $params);

        $url = UrlHelper::actionUrl('commerce/webhooks/process-webhook', $params);

        // Remove the cpTrigger from the url if it's there.
        if (Craft::$app->getConfig()->getGeneral()->cpTrigger) {
            $url = StringHelper::replace($url, Craft::$app->getConfig()->getGeneral()->cpTrigger . '/', '');
        }

        return $url;
    }

    /**
     * Returns whether this gateway allows payments in control panel.
     */
    public function cpPaymentsEnabled(): bool
    {
        return true;
    }

    public function getCpEditUrl(): string
    {
        return UrlHelper::cpUrl('commerce/settings/gateways/' . $this->id);
    }

    /**
     * Returns the payment type options.
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
    public function defineRules(): array
    {
        $rules = parent::defineRules();
        $rules[] = [['paymentType', 'handle'], 'required'];

        $rules[] = [['name', 'handle', 'paymentType', 'isFrontendEnabled', 'orderCondition', 'billingAddressCondition', 'shippingAddressCondition', 'sortOrder'], 'safe'];

        return $rules;
    }

    /**
     * Returns the html to use when paying with a stored payment source.
     *
     * @param array $params
     * @return string
     */
    public function getPaymentConfirmationFormHtml(array $params): string
    {
        return '';
    }

    /**
     * @inheritdoc
     */
    public function availableForUseWithOrder(Order $order): bool
    {
        if ($this->hasOrderCondition() && !$this->getOrderCondition()->matchElement($order)) {
            return false;
        }

        if ($this->hasBillingAddressCondition() && $order->billingAddress && !$this->getBillingAddressCondition()->matchElement($order->billingAddress)) {
            return false;
        }

        if ($this->hasShippingAddressCondition() && $order->shippingAddress && !$this->getShippingAddressCondition()->matchElement($order->shippingAddress)) {
            return false;
        }

        return true;
    }

    /**
     * Returns true if gateway supports partial refund requests.
     */
    public function supportsPartialPayment(): bool
    {
        return true;
    }

    /**
     * Returns true if this gateway has an order condition
     *
     * @since 5.4.0
     */
    public function hasOrderCondition(): bool
    {
        return $this->getOrderCondition()->getConditionRules() !== [];
    }

    /**
     * Returns payment Form HTML
     */
    abstract public function getPaymentFormHtml(array $params): ?string;

    /**
     * @inheritdoc
     */
    public function getTransactionHashFromWebhook(): ?string
    {
        return null;
    }

    /**
     * @param Transaction $transaction
     * @return bool
     * @since 4.8.1
     */
    public function transactionSupportsRefund(Transaction $transaction): bool
    {
        return true;
    }


    /**
     * Gets the order condition for this gateway
     *
     * @since 5.4.0
     */
    public function getOrderCondition(): ElementConditionInterface
    {
        /** @var DiscountOrderCondition $condition */
        $condition = $this->_orderCondition ?? new GatewayOrderCondition();
        $condition->mainTag = 'div';
        $condition->name = 'orderCondition';

        return $condition;
    }

    /**
     * Sets the order condition for this gateway
     *
     * @since 5.4.0
     */
    public function setOrderCondition(ElementConditionInterface|string|array $condition): void
    {
        if (empty($condition)) {
            $this->_orderCondition = null;
            return;
        }

        if (is_string($condition)) {
            $condition = Json::decodeIfJson($condition);
        }

        if (!$condition instanceof GatewayOrderCondition) {
            $condition['class'] = GatewayOrderCondition::class;
            $condition = \Craft::$app->getConditions()->createCondition($condition);
            /** @var GatewayOrderCondition $condition */
        }
        $condition->forProjectConfig = true;

        $this->_orderCondition = $condition;
    }

    /**
     * Returns true if this gateway has a billing address condition
     *
     * @since 5.5
     */
    public function hasBillingAddressCondition(): bool
    {
        return $this->getBillingAddressCondition()->getConditionRules() !== [];
    }

    /**
     * Gets the billing address condition for this gateway
     *
     * @since 5.5
     */
    public function getBillingAddressCondition(): ElementConditionInterface
    {
        /** @var GatewayAddressCondition $condition */
        $condition = $this->_billingAddressCondition ?? new GatewayAddressCondition();
        $condition->mainTag = 'div';
        $condition->name = 'billingAddressCondition';

        return $condition;
    }

    /**
     * Sets the billing address condition for this gateway
     *
     * @since 5.5
     */
    public function setBillingAddressCondition(ElementConditionInterface|string|array $condition): void
    {
        if (empty($condition)) {
            $this->_billingAddressCondition = null;
            return;
        }

        if (is_string($condition)) {
            $condition = Json::decodeIfJson($condition);
        }

        if (!$condition instanceof GatewayAddressCondition) {
            $condition['class'] = GatewayAddressCondition::class;
            $condition = \Craft::$app->getConditions()->createCondition($condition);
            /** @var GatewayAddressCondition $condition */
        }
        $condition->forProjectConfig = true;

        $this->_billingAddressCondition = $condition;
    }

    /**
     * Returns true if this gateway has a shipping address condition
     *
     * @since 5.5
     */
    public function hasShippingAddressCondition(): bool
    {
        return $this->getShippingAddressCondition()->getConditionRules() !== [];
    }

    /**
     * Gets the shipping address condition for this gateway
     *
     * @since 5.5
     */
    public function getShippingAddressCondition(): ElementConditionInterface
    {
        /** @var GatewayAddressCondition $condition */
        $condition = $this->_shippingAddressCondition ?? new GatewayAddressCondition();
        $condition->mainTag = 'div';
        $condition->name = 'shippingAddressCondition';

        return $condition;
    }

    /**
     * Sets the shipping address condition for this gateway
     *
     * @since 5.5
     */
    public function setShippingAddressCondition(ElementConditionInterface|string|array $condition): void
    {
        if (empty($condition)) {
            $this->_shippingAddressCondition = null;
            return;
        }

        if (is_string($condition)) {
            $condition = Json::decodeIfJson($condition);
        }

        if (!$condition instanceof GatewayAddressCondition) {
            $condition['class'] = GatewayAddressCondition::class;
            $condition = \Craft::$app->getConditions()->createCondition($condition);
            /** @var GatewayAddressCondition $condition */
        }
        $condition->forProjectConfig = true;

        $this->_shippingAddressCondition = $condition;
    }

    /**
     * @return array
     * @since 5.4.0
     */
    public function getConfig(): array
    {
        $configData = [
            'name' => $this->name,
            'handle' => $this->handle,
            'type' => static::class,
            'settings' => $this->getSettings(),
            'sortOrder' => ($this->sortOrder ?? 99),
            'paymentType' => $this->paymentType,
            'isFrontendEnabled' => $this->getIsFrontendEnabled(false),
            'orderCondition' => $this->getOrderCondition()->getConfig(),
            'billingAddressCondition' => $this->getBillingAddressCondition()->getConfig(),
            'shippingAddressCondition' => $this->getShippingAddressCondition()->getConfig(),
        ];

        return $configData;
    }
}
