<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Payment\Gateway\Types;

use CraftCms\Cms\Cms;
use CraftCms\Cms\Support\Str;
use CraftCms\Cms\View\TemplateMode;
use CraftCms\Commerce\Exceptions\NotImplementedException;
use CraftCms\Commerce\Payment\Forms\BasePaymentForm;
use CraftCms\Commerce\Payment\Forms\CreditCardPaymentForm;
use CraftCms\Commerce\Payment\Forms\DummyPaymentForm;
use CraftCms\Commerce\Payment\Gateway\Contracts\RequestResponseInterface;
use CraftCms\Commerce\Payment\Gateway\Gateway;
use CraftCms\Commerce\Payment\Gateway\Responses\Dummy as DummyRequestResponse;
use CraftCms\Commerce\Payment\Models\PaymentSource;
use CraftCms\Commerce\Payment\Models\Transaction;
use Illuminate\Http\Response;
use InvalidArgumentException;
use Override;

use function CraftCms\Cms\template;

/**
 * Dummy represents a dummy gateway.
 */
class Dummy extends Gateway
{
    public function getPaymentFormHtml(array $params): ?string
    {
        $paymentFormModel = $this->getPaymentFormModel();

        if (Cms::config()->devMode) {
            $paymentFormModel->firstName = 'Jenny';
            $paymentFormModel->lastName = 'Andrews';
            $paymentFormModel->number = '4242424242424242';
            $paymentFormModel->expiry = '01/' . date('Y', strtotime('+1 year'));
            $paymentFormModel->cvv = '123';
        }

        $defaults = [
            'paymentForm' => $paymentFormModel,
        ];

        $params = array_merge($defaults, $params);

        return template('commerce/_components/gateways/_creditCardFields', $params, TemplateMode::Cp);
    }

    public function getPaymentFormModel(): DummyPaymentForm
    {
        return new DummyPaymentForm();
    }

    #[Override]
    public function authorize(Transaction $transaction, BasePaymentForm $form): RequestResponseInterface
    {
        if (!$form instanceof CreditCardPaymentForm) {
            throw new InvalidArgumentException(sprintf('%s only accepts %s objects passed to $form.', __METHOD__, CreditCardPaymentForm::class));
        }

        return new DummyRequestResponse($form);
    }

    #[Override]
    public function capture(Transaction $transaction, string $reference): RequestResponseInterface
    {
        return new DummyRequestResponse();
    }

    #[Override]
    public function completeAuthorize(Transaction $transaction): RequestResponseInterface
    {
        return new DummyRequestResponse();
    }

    #[Override]
    public function completePurchase(Transaction $transaction): RequestResponseInterface
    {
        return new DummyRequestResponse();
    }

    #[Override]
    public function createPaymentSource(BasePaymentForm $sourceData, int $customerId): PaymentSource
    {
        /** @var CreditCardPaymentForm $sourceData */
        $paymentSource = new PaymentSource();
        $paymentSource->customerId = $customerId;
        $paymentSource->gatewayId = $this->id;
        $paymentSource->token = Str::random();
        $paymentSource->response = '';
        $paymentSource->description = 'Card ending with ' . substr((string)$sourceData->number, -4);

        return $paymentSource;
    }

    #[Override]
    public function deletePaymentSource(string $token): bool
    {
        return true;
    }

    #[Override]
    public function purchase(Transaction $transaction, BasePaymentForm $form): RequestResponseInterface
    {
        if (!$form instanceof CreditCardPaymentForm) {
            throw new InvalidArgumentException(sprintf('%s only accepts %s objects passed to $form.', __METHOD__, CreditCardPaymentForm::class));
        }

        return new DummyRequestResponse($form);
    }

    #[Override]
    public function processWebHook(): Response
    {
        throw new NotImplementedException(self::class . ' does not support processWebhook()');
    }

    #[Override]
    public function refund(Transaction $transaction): RequestResponseInterface
    {
        $form = new DummyPaymentForm();

        if ($transaction->note != 'fail') {
            $form->number = '4242424242424242';
        } else {
            $form->number = '378282246310005';
        }

        return new DummyRequestResponse($form);
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
        return true;
    }

    #[Override]
    public function supportsCompletePurchase(): bool
    {
        return true;
    }

    #[Override]
    public function supportsPaymentSources(): bool
    {
        return true;
    }

    #[Override]
    public function supportsPurchase(): bool
    {
        return true;
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
}
