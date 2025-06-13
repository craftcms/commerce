<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\base;

use Craft;
use craft\base\SavableComponent;
use craft\commerce\elements\Order;
use craft\commerce\models\payments\BasePaymentForm;
use craft\commerce\models\Transaction;
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

        $rules[] = [['name', 'handle', 'paymentType', 'isFrontendEnabled', 'sortOrder'], 'safe'];

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
}
