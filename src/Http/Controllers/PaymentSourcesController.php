<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers;

use craft\commerce\helpers\PaymentForm;
use craft\commerce\Plugin;
use CraftCms\Cms\Http\RespondsWithFlash;
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
        $plugin = Plugin::getInstance();

        // Are we paying anonymously?
        $customer = currentUserElement();
        abort_unless($customer, 401, t('You must be signed in to create a payment source.', category: 'commerce'));

        // Allow setting the payment method at time of submitting payment.
        $gatewayId = $request->input('gatewayId');
        abort_if(!$gatewayId, 400, 'Missing gatewayId');

        $isPrimaryPaymentSource = $request->input('isPrimaryPaymentSource', false);

        $gateway = $plugin->getGateways()->getGatewayById($gatewayId);

        if (!$gateway || !$gateway->supportsPaymentSources()) {
            return $this->asFailure(t('There is no gateway selected that supports payment sources.', category: 'commerce'));
        }

        // Get the payment method' gateway adapter's expected form model
        $paymentForm = $gateway->getPaymentFormModel();
        $paymentFormParams = $request->input(PaymentForm::getPaymentFormParamName($gateway->handle), []);
        $paymentForm->setAttributes($paymentFormParams, false);
        $description = (string)$request->input('description');

        try {
            $paymentSource = $plugin->getPaymentSources()->createPaymentSource($customer->id, $gateway, $paymentForm, $description, $isPrimaryPaymentSource);
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
            $plugin->getCustomers()->savePrimaryPaymentSourceId($customer, $paymentSource->id);
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
        abort_unless($user, 401, t('You must be signed in to set a primary payment source.', category: 'commerce'));

        $paymentSourceId = $request->input('id');
        abort_if(!$paymentSourceId, 400, 'Missing id');

        // Check payment source exists and belongs to the user
        $paymentSource = Plugin::getInstance()->getPaymentSources()->getPaymentSourceByIdAndUserId((int)$paymentSourceId, $user->id);
        if (!$paymentSource) {
            return $this->asFailure(
                t('Unable to retrieve payment source.', category: 'commerce'),
                ['paymentSourceId' => $paymentSourceId],
            );
        }

        if (!Plugin::getInstance()->getCustomers()->savePrimaryPaymentSourceId($user, $paymentSource->id)) {
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
        abort_unless($currentUser, 401);

        $id = $request->input('id');
        abort_if(!$id, 400, 'Missing id');

        $paymentSources = Plugin::getInstance()->getPaymentSources();
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
