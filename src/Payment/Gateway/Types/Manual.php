<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Payment\Gateway\Types;

use CraftCms\Cms\Form\Controls\Lightswitch;
use CraftCms\Cms\Form\Form;
use CraftCms\Cms\Form\FormContext;
use CraftCms\Cms\Form\Nodes\Field;
use CraftCms\Cms\Support\Env;
use CraftCms\Commerce\Exceptions\NotImplementedException;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Payment\Data\PaymentSource;
use CraftCms\Commerce\Payment\Data\Transaction;
use CraftCms\Commerce\Payment\Forms\BasePaymentForm;
use CraftCms\Commerce\Payment\Forms\OffsitePaymentForm;
use CraftCms\Commerce\Payment\Gateway\Contracts\RequestResponseInterface;
use CraftCms\Commerce\Payment\Gateway\Gateway;
use CraftCms\Commerce\Payment\Gateway\Responses\Manual as ManualRequestResponse;
use Illuminate\Http\Response;
use Override;

use function CraftCms\Cms\t;

/**
 * Manual represents a manual gateway.
 */
class Manual extends Gateway
{
    private string|bool $_onlyAllowForZeroPriceOrders = false;

    #[Override]
    public function getSettings(): array
    {
        $settings = parent::getSettings();
        $settings['onlyAllowForZeroPriceOrders'] = $this->getOnlyAllowForZeroPriceOrders(false);

        return $settings;
    }

    public function getPaymentFormHtml(array $params): ?string
    {
        return '';
    }

    #[Override]
    public function getPaymentFormModel(): BasePaymentForm
    {
        return new OffsitePaymentForm();
    }

    #[Override]
    public function settingsForm(FormContext $context = new FormContext()): ?Form
    {
        return Form::make()
            ->add(Field::make(t('Only allow for orders with a zero balance', category: 'commerce'))
                ->control(Lightswitch::make('onlyAllowForZeroPriceOrders')->value($this->getOnlyAllowForZeroPriceOrders(false))));
    }

    #[Override]
    public function authorize(Transaction $transaction, BasePaymentForm $form): RequestResponseInterface
    {
        return new ManualRequestResponse();
    }

    #[Override]
    public function capture(Transaction $transaction, string $reference): RequestResponseInterface
    {
        return new ManualRequestResponse();
    }

    #[Override]
    public function completeAuthorize(Transaction $transaction): RequestResponseInterface
    {
        throw new NotImplementedException(t('This gateway does not support that functionality.', category: 'commerce'));
    }

    #[Override]
    public function completePurchase(Transaction $transaction): RequestResponseInterface
    {
        throw new NotImplementedException(t('This gateway does not support that functionality.', category: 'commerce'));
    }

    #[Override]
    public function createPaymentSource(BasePaymentForm $sourceData, int $customerId): PaymentSource
    {
        throw new NotImplementedException(t('This gateway does not support that functionality.', category: 'commerce'));
    }

    #[Override]
    public function deletePaymentSource(string $token): bool
    {
        throw new NotImplementedException(t('This gateway does not support that functionality.', category: 'commerce'));
    }

    #[Override]
    public function getPaymentTypeOptions(): array
    {
        return [
            'authorize' => t('Authorize Only (Manually Capture)', category: 'commerce'),
        ];
    }

    #[Override]
    public function purchase(Transaction $transaction, BasePaymentForm $form): RequestResponseInterface
    {
        throw new NotImplementedException(t('This gateway does not support that functionality.', category: 'commerce'));
    }

    #[Override]
    public function processWebHook(): Response
    {
        throw new NotImplementedException(t('This gateway does not support that functionality.', category: 'commerce'));
    }

    #[Override]
    public function refund(Transaction $transaction): RequestResponseInterface
    {
        return new ManualRequestResponse();
    }

    #[Override]
    public function supportsAuthorize(): bool
    {
        return true;
    }

    #[Override]
    public function supportsCapture(): bool
    {
        return true;
    }

    #[Override]
    public function supportsCompleteAuthorize(): bool
    {
        return false;
    }

    #[Override]
    public function supportsCompletePurchase(): bool
    {
        return false;
    }

    #[Override]
    public function supportsPaymentSources(): bool
    {
        return false;
    }

    #[Override]
    public function supportsPurchase(): bool
    {
        return false;
    }

    #[Override]
    public function supportsRefund(): bool
    {
        return true;
    }

    #[Override]
    public function supportsPartialRefund(): bool
    {
        return true;
    }

    #[Override]
    public function supportsWebhooks(): bool
    {
        return false;
    }

    #[Override]
    public function availableForUseWithOrder(Order $order): bool
    {
        if ($this->getOnlyAllowForZeroPriceOrders() && $order->getTotalPrice() != 0) {
            return false;
        }

        return parent::availableForUseWithOrder($order);
    }

    public function getOnlyAllowForZeroPriceOrders(bool $parse = true): bool|string
    {
        return $parse ? (Env::parseBoolean($this->_onlyAllowForZeroPriceOrders) ?? false) : $this->_onlyAllowForZeroPriceOrders;
    }

    public function setOnlyAllowForZeroPriceOrders(bool|string $onlyAllowForZeroPriceOrders): void
    {
        $this->_onlyAllowForZeroPriceOrders = $onlyAllowForZeroPriceOrders;
    }
}
