<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers;

use craft\commerce\helpers\PaymentForm;
use CraftCms\Cms\Http\RespondsWithFlash;
use CraftCms\Commerce\Customer\Customers;
use CraftCms\Commerce\Payment\Gateway\Gateways;
use CraftCms\Commerce\Payment\PaymentSources;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;
use function CraftCms\Cms\currentUserElement;
use function CraftCms\Cms\t;

readonly class PaymentSourcesController
{
    use RespondsWithFlash;

    public function add(Request $request): ?Response
    {

        // Are we paying anonymously?
        $customer = currentUserElement();
        abort_unless($customer !== null, 401, t('You must be signed in to create a payment source.', category: 'commerce'));

        // Allow setting the payment method at time of submitting payment.
        $gatewayId = $request->input('gatewayId');
        abort_if(!$gatewayId, 400, 'Missing gatewayId');
        $gatewayId = (int)$gatewayId;

        $isPrimaryPaymentSource = $request->input('isPrimaryPaymentSource', false);

        $gateway = app(Gateways::class)->getGatewayById($gatewayId);

        /** @phpstan-ignore-next-line method.notFound (supportsPaymentSources() is declared on GatewayInterface, which legacy craft\commerce\base\Gateway implements via the class_alias chain, which PHPStan can't trace) */
        if (!$gateway || !$gateway->supportsPaymentSources()) {
            return $this->asFailure(t('There is no gateway selected that supports payment sources.', category: 'commerce'));
        }

        // Get the payment method' gateway adapter's expected form model
        /** @phpstan-ignore-next-line method.notFound (getPaymentFormModel() is declared on GatewayInterface, which legacy craft\commerce\base\Gateway implements via the class_alias chain, which PHPStan can't trace) */
        $paymentForm = $gateway->getPaymentFormModel();
        $paymentFormParams = $request->input(PaymentForm::getPaymentFormParamName($gateway->handle), []);
        $paymentForm->setAttributes($paymentFormParams, false);
        $description = (string)$request->input('description');

        try {
            /** @phpstan-ignore-next-line argument.type (legacy craft\commerce\base\Gateway implements GatewayInterface via the class_alias chain, which PHPStan can't trace) */
            $paymentSource = app(PaymentSources::class)->createPaymentSource($customer->id, $gateway, $paymentForm, $description, $isPrimaryPaymentSource);
        } catch (Throwable $exception) {
            Log::error($exception->getMessage(), ['exception' => $exception]);
            return $this->asModelFailure(
                $paymentForm,
                t('Could not create the payment source.', category: 'commerce'),
                'paymentForm',
                ['paymentFormErrors' => $paymentForm->getErrors()]
            );
        }

        if ($isPrimaryPaymentSource) {
            app(Customers::class)->savePrimaryPaymentSourceId($customer, $paymentSource->id);
        }

        return $this->asModelSuccess(
            $paymentSource,
            t('Payment source created.', category: 'commerce'),
            'paymentSource'
        );
    }

    public function setPrimaryPaymentSource(Request $request): ?Response
    {
        $user = currentUserElement();
        abort_unless($user !== null, 401, t('You must be signed in to set a primary payment source.', category: 'commerce'));

        $paymentSourceId = $request->input('id');
        abort_if(!$paymentSourceId, 400, 'Missing id');

        // Check payment source exists and belongs to the user
        $paymentSource = app(PaymentSources::class)->getPaymentSourceByIdAndUserId((int)$paymentSourceId, $user->id);
        if (!$paymentSource) {
            return $this->asFailure(
                t('Unable to retrieve payment source.', category: 'commerce'),
                ['paymentSourceId' => $paymentSourceId],
            );
        }

        if (!app(Customers::class)->savePrimaryPaymentSourceId($user, $paymentSource->id)) {
            return $this->asFailure(
                t('Unable to set primary payment source.', category: 'commerce'),
                ['paymentSourceId' => $paymentSourceId],
            );
        }

        return $this->asSuccess(t('Primary payment source updated.', category: 'commerce'));
    }

    public function delete(Request $request): ?Response
    {
        $currentUser = currentUserElement();
        abort_unless($currentUser !== null, 401);

        $id = $request->input('id');
        abort_if(!$id, 400, 'Missing id');

        $paymentSources = app(PaymentSources::class);
        $paymentSource = $paymentSources->getPaymentSourceById((int)$id);

        if (!$paymentSource) {
            return null;
        }

        if ($paymentSource->getCustomer()?->id != $currentUser->id && !$currentUser->can('commerce-manageOrders')) {
            return null;
        }

        $result = $paymentSources->deletePaymentSourceById((int)$id);

        if ($result) {
            return $this->asModelSuccess($paymentSource, t('Payment source deleted.', category: 'commerce'));
        }

        return $this->asModelFailure($paymentSource, t('Couldn\'t delete the payment source.', category: 'commerce'));
    }
}
