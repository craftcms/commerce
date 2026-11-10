<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Payment\Gateway;

use CraftCms\Cms\Cms;
use CraftCms\Cms\Component\Component;
use CraftCms\Cms\Component\Concerns\ConfigurableComponent;
use CraftCms\Cms\Component\Concerns\SavableComponent;
use CraftCms\Cms\Component\Contracts\ConfigurableComponentInterface;
use CraftCms\Cms\Component\Events\DefineSettingsAttributes;
use CraftCms\Cms\Condition\Contracts\ConditionRuleInterface;
use CraftCms\Cms\Element\Conditions\Contracts\ElementConditionInterface;
use CraftCms\Cms\Support\Env;
use CraftCms\Cms\Support\Facades\Conditions;
use CraftCms\Cms\Support\Json;
use CraftCms\Cms\Support\Url;
use CraftCms\Cms\Support\Utils;
use CraftCms\Cms\Ui\Enums\ControlMode;
use CraftCms\Cms\Ui\Ui;
use CraftCms\Cms\Ui\UiContext;
use CraftCms\Cms\Ui\UiHtmlRenderer;
use CraftCms\Cms\Ui\UiResolver;
use CraftCms\Commerce\Address\Conditions\GatewayAddressCondition;
use CraftCms\Commerce\Order\Conditions\GatewayOrderCondition;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Payment\Data\Transaction;
use CraftCms\Commerce\Payment\Forms\BasePaymentForm;
use CraftCms\Commerce\Payment\Gateway\Contracts\GatewayInterface;
use DateTime;
use Override;
use ReflectionProperty;

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

    /**
     * Renders this gateway's settingsUi() as a standalone HTML string, for CP templates still
     * rendered via Twig rather than the Form API.
     */
    public function getSettingsHtml(): ?string
    {
        return $this->renderUiHtml($this->settingsUi(), new UiContext(mode: ControlMode::Editable));
    }

    /**
     * @see getSettingsHtml()
     */
    public function getReadOnlySettingsHtml(): ?string
    {
        return $this->renderUiHtml($this->settingsUi(), new UiContext(mode: ControlMode::ReadOnly));
    }

    /**
     * Returns the payment form customers fill in to pay with this gateway, or `null` if the gateway doesn’t need one
     * (for example, because it redirects customers to a hosted checkout).
     *
     * Each control’s value comes from the payment form model’s attribute at the same path, and the model’s
     * validation errors are shown on those controls. Markup the Ui controls can’t express, such as a provider’s
     * hosted card fields, can be added with a trusted {@see \CraftCms\Cms\Ui\Nodes\TemplateContent} node.
     *
     * @param array<string, mixed> $params The parameters passed to {@see getPaymentFormHtml()}, such as `order`.
     */
    public function paymentFormUi(BasePaymentForm $form, array $params = []): ?Ui
    {
        return null;
    }

    /**
     * Returns the form shown when paying with a stored payment source, or `null` if the gateway doesn’t need one.
     *
     * @param array<string, mixed> $params The parameters passed to {@see getPaymentConfirmationFormHtml()}.
     */
    public function paymentConfirmationFormUi(array $params = []): ?Ui
    {
        return null;
    }

    /**
     * Renders {@see paymentFormUi()} as HTML, for templates.
     *
     * @param array<string, mixed> $params Passed on to {@see paymentFormUi()}. A `paymentForm` param sets the payment
     * form model whose values and errors the form shows; it defaults to a new {@see getPaymentFormModel()}.
     */
    public function getPaymentFormHtml(array $params): ?string
    {
        $form = $params['paymentForm'] ?? $this->getPaymentFormModel();
        $ui = $this->paymentFormUi($form, $params);

        return $this->renderUiHtml($ui, new UiContext(
            values: $form->toArray(),
            errors: $form->errors()->getMessages(),
        ));
    }

    /**
     * Renders {@see paymentConfirmationFormUi()} as HTML, for templates.
     *
     * @param array<string, mixed> $params
     */
    public function getPaymentConfirmationFormHtml(array $params): string
    {
        return $this->renderUiHtml($this->paymentConfirmationFormUi($params), new UiContext()) ?? '';
    }

    private function renderUiHtml(?Ui $ui, UiContext $context): ?string
    {
        if ($ui === null) {
            return null;
        }

        return app(UiHtmlRenderer::class)->render(app(UiResolver::class)->resolve($ui, $context));
    }

    /**
     * Returns the gateway’s settings attributes.
     *
     * Craft skips public properties declared on abstract classes, so it doesn’t treat a base class’s own properties
     * (like `name` and `handle` here) as settings. Gateway types often share their settings via an abstract base
     * class though, so properties declared on abstract subclasses of this class are included.
     */
    #[Override]
    public function settingsAttributes(): array
    {
        $attributes = array_keys(Utils::getPublicProperties($this, function(ReflectionProperty $property) {
            $class = $property->getDeclaringClass();

            return !$class->isAbstract() || $class->isSubclassOf(self::class);
        }));

        event($event = new DefineSettingsAttributes(component: $this, attributes: $attributes));

        return $event->attributes;
    }

    #[Override]
    public function getRules(): array
    {
        return [
            'paymentType' => ['required'],
            'handle' => ['required'],
        ];
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
        return !empty($this->getOrderCondition()->getConditionRules()->findRules(fn(ConditionRuleInterface $rule) => true));
    }

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
        return !empty($this->getBillingAddressCondition()->getConditionRules()->findRules(fn(ConditionRuleInterface $rule) => true));
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
        return !empty($this->getShippingAddressCondition()->getConditionRules()->findRules(fn(ConditionRuleInterface $rule) => true));
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
