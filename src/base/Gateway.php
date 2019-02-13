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
use craft\commerce\records\Gateway as GatewayRecord;
use craft\helpers\StringHelper;
use craft\helpers\UrlHelper;
use craft\validators\UniqueValidator;

/**
 * Class Gateway
 *
 * @property string $cpEditUrl
 * @property bool $dateArchived
 * @property bool $isFrontendEnabled
 * @property bool $isArchived
 * @property string $name
 * @property null|BasePaymentForm $paymentFormModel
 * @property string $paymentType
 * @property array $paymentTypeOptions
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
abstract class Gateway extends SavableComponent implements GatewayInterface
{
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
     * @return string
     */
    public function getWebhookUrl(array $params = []): string
    {
        $params = array_merge(['gateway' => $this->id], $params);

        $url = UrlHelper::actionUrl('commerce/webhooks/process-webhook', $params);

        return StringHelper::replace($url, Craft::$app->getConfig()->getGeneral()->cpTrigger . '/', '');
    }

    /**
     * Returns whether this gateway allows payments in control panel.
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
        return UrlHelper::cpUrl('commerce/settings/gateways/' . $this->id);
    }

    /**
     * Returns the payment type options.
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
    public function rules()
    {
        return [
            [['paymentType', 'handle'], 'required'],
        ];
    }

    /**
     * Returns the html to use when paying with a stored payment source.
     *
     * @param array $params
     * @return mixed
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
     * Returns payment Form HTML
     *
     * @param array $params
     * @return string|null
     */
    abstract public function getPaymentFormHtml(array $params);
}
