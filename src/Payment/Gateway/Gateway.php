<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Payment\Gateway;

use CraftCms\Cms\Cms;
use CraftCms\Cms\Component\Component;
use CraftCms\Cms\Component\Concerns\ConfigurableComponent;
use CraftCms\Cms\Component\Concerns\SavableComponent;
use CraftCms\Cms\Component\Contracts\ConfigurableComponentInterface;
use CraftCms\Cms\Element\Conditions\Contracts\ElementConditionInterface;
use CraftCms\Cms\Support\Env;
use CraftCms\Cms\Support\Facades\Conditions;
use CraftCms\Cms\Support\Json;
use CraftCms\Cms\Support\Url;
use CraftCms\Commerce\Address\Conditions\GatewayAddressCondition;
use CraftCms\Commerce\Order\Conditions\GatewayOrderCondition;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Payment\Data\Transaction;
use CraftCms\Commerce\Payment\Gateway\Contracts\GatewayInterface;
use DateTime;
use Override;

use function CraftCms\Cms\t;

abstract class Gateway extends Component implements GatewayInterface, ConfigurableComponentInterface
{
    use ConfigurableComponent;
    use SavableComponent;

    public ?string $name = null;

    public ?string $handle = null;

    public string $paymentType = 'purchase';

    public bool|string|null $_isFrontendEnabled = true;

    public bool $isArchived = false;

    public ?DateTime $dateArchived = null;

    public ?int $sortOrder = null;

    public ?string $uid = null;

    private ?ElementConditionInterface $_orderCondition = null;

    private ?ElementConditionInterface $_billingAddressCondition = null;

    private ?ElementConditionInterface $_shippingAddressCondition = null;

    public function __toString(): string
    {
        return (string)$this->name;
    }

    public function setIsFrontendEnabled(bool|string|null $isFrontendEnabled): void
    {
        $this->_isFrontendEnabled = $isFrontendEnabled;
    }

    public function getIsFrontendEnabled(bool $parse = true): bool|string|null
    {
        return $parse ? Env::parseBoolean($this->_isFrontendEnabled) : $this->_isFrontendEnabled;
    }

    /**
     * Shows the payment button on the payment form.
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

        $url = Url::actionUrl('commerce/webhooks/process-webhook', $params);

        // Remove the cpTrigger from the url if it's there.
        if ($cpTrigger = Cms::config()->cpTrigger) {
            $url = str_replace($cpTrigger . '/', '', $url);
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
        return Url::cpUrl('commerce/settings/gateways/' . $this->id);
    }

    /**
     * Returns the payment type options.
     */
    public function getPaymentTypeOptions(): array
    {
        return [
            'authorize' => t('Authorize Only (Manually Capture)', category: 'commerce'),
            'purchase' => t('Purchase (Authorize and Capture Immediately)', category: 'commerce'),
        ];
    }

    #[Override]
    public function getRules(): array
    {
        return [
            'paymentType' => ['required'],
            'handle' => ['required'],
        ];
    }

    /**
     * Returns the html to use when paying with a stored payment source.
     */
    public function getPaymentConfirmationFormHtml(array $params): string
    {
        return '';
    }

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
     */
    public function hasOrderCondition(): bool
    {
        return $this->getOrderCondition()->getConditionRules() !== [];
    }

    /**
     * Returns payment Form HTML
     */
    abstract public function getPaymentFormHtml(array $params): ?string;

    public function getTransactionHashFromWebhook(): ?string
    {
        return null;
    }

    public function transactionSupportsRefund(Transaction $transaction): bool
    {
        return true;
    }

    /**
     * Gets the order condition for this gateway
     */
    public function getOrderCondition(): ElementConditionInterface
    {
        /** @var GatewayOrderCondition $condition */
        $condition = $this->_orderCondition ?? new GatewayOrderCondition();
        $condition->mainTag = 'div';
        $condition->name = 'orderCondition';

        return $condition;
    }

    /**
     * Sets the order condition for this gateway
     */
    public function setOrderCondition(ElementConditionInterface|string|array|null $condition): void
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
            $condition = Conditions::createCondition($condition);
        }
        $condition->forProjectConfig = true;

        /** @phpstan-ignore-next-line assign.propertyType */
        $this->_orderCondition = $condition;
    }

    /**
     * Returns true if this gateway has a billing address condition
     */
    public function hasBillingAddressCondition(): bool
    {
        return $this->getBillingAddressCondition()->getConditionRules() !== [];
    }

    /**
     * Gets the billing address condition for this gateway
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
     */
    public function setBillingAddressCondition(ElementConditionInterface|string|array|null $condition): void
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
            $condition = Conditions::createCondition($condition);
        }
        $condition->forProjectConfig = true;

        /** @phpstan-ignore-next-line assign.propertyType */
        $this->_billingAddressCondition = $condition;
    }

    /**
     * Returns true if this gateway has a shipping address condition
     */
    public function hasShippingAddressCondition(): bool
    {
        return $this->getShippingAddressCondition()->getConditionRules() !== [];
    }

    /**
     * Gets the shipping address condition for this gateway
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
     */
    public function setShippingAddressCondition(ElementConditionInterface|string|array|null $condition): void
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
            $condition = Conditions::createCondition($condition);
        }
        $condition->forProjectConfig = true;

        /** @phpstan-ignore-next-line assign.propertyType */
        $this->_shippingAddressCondition = $condition;
    }

    public function getConfig(): array
    {
        return [
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
    }
}
