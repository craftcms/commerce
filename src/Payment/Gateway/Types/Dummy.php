<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Payment\Gateway\Types;

use CraftCms\Cms\Cms;
use CraftCms\Cms\Support\Str;
use CraftCms\Cms\Ui\Controls\Text;
use CraftCms\Cms\Ui\Enums\FieldWidth;
use CraftCms\Cms\Ui\Nodes\Field;
use CraftCms\Cms\Ui\Ui;
use CraftCms\Cms\Ui\UiContext;
use CraftCms\Cms\Ui\UiHtmlRenderer;
use CraftCms\Cms\Ui\UiResolver;
use CraftCms\Commerce\Exceptions\NotImplementedException;
use CraftCms\Commerce\Payment\Data\PaymentSource;
use CraftCms\Commerce\Payment\Data\Transaction;
use CraftCms\Commerce\Payment\Forms\BasePaymentForm;
use CraftCms\Commerce\Payment\Forms\CreditCardPaymentForm;
use CraftCms\Commerce\Payment\Forms\DummyPaymentForm;
use CraftCms\Commerce\Payment\Gateway\Contracts\RequestResponseInterface;
use CraftCms\Commerce\Payment\Gateway\Gateway;
use CraftCms\Commerce\Payment\Gateway\Responses\Dummy as DummyRequestResponse;
use Illuminate\Http\Response;
use InvalidArgumentException;
use Override;

use function CraftCms\Cms\t;

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

        /** @var CreditCardPaymentForm $paymentForm */
        $paymentForm = $params['paymentForm'] ?? $paymentFormModel;
        $errors = $paymentForm->errors();

        $ui = Ui::make()
            ->addGroup(t('Card Holder', category: 'commerce'), [
                Field::make(t('First Name', category: 'commerce'))
                    ->required()
                    ->width(FieldWidth::Half)
                    ->control(Text::make('firstName')->maxLength(70)->placeholder(t('First Name', category: 'commerce'))->value($paymentForm->firstName)),
                Field::make(t('Last Name', category: 'commerce'))
                    ->required()
                    ->width(FieldWidth::Half)
                    ->control(Text::make('lastName')->maxLength(70)->placeholder(t('Last Name', category: 'commerce'))->value($paymentForm->lastName)),
            ])
            ->addGroup(t('Card', category: 'commerce'), [
                Field::make(t('Card Number', category: 'commerce'))
                    ->control(Text::make('number')->maxLength(19)->placeholder(t('Card Number', category: 'commerce'))->inputMode('numeric')->value($paymentForm->number)),
                Field::make(t('Expiry', category: 'commerce'))
                    ->width(FieldWidth::Half)
                    ->control(Text::make('expiry')
                        ->placeholder(t('MM', category: 'commerce') . ' / ' . t('YYYY', category: 'commerce'))
                        ->value($paymentForm->expiry)),
                Field::make(t('CVV', category: 'commerce'))
                    ->width(FieldWidth::Half)
                    ->control(Text::make('cvv')->inputType('tel')->placeholder(t('CVV', category: 'commerce'))->value($paymentForm->cvv)),
            ]);

        return app(UiHtmlRenderer::class)->render(app(UiResolver::class)->resolve($ui, new UiContext(errors: [
            'firstName' => $errors->get('firstName'),
            'lastName' => $errors->get('lastName'),
            'number' => $errors->get('number'),
            'expiry' => [...$errors->get('expiry'), ...$errors->get('month'), ...$errors->get('year')],
            'cvv' => $errors->get('cvv'),
        ])));
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
