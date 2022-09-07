<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\base\Gateway;
use craft\commerce\helpers\PaymentForm;
use craft\commerce\Plugin;
use craft\commerce\Plugin as Commerce;
use Throwable;
use yii\base\InvalidConfigException;
use yii\web\BadRequestHttpException;
use yii\web\HttpException;
use yii\web\Response;

/**
 * Class Payments Controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class PaymentSourcesController extends BaseFrontEndController
{
    /**
     * Adds a payment source.
     *
     * @throws BadRequestHttpException
     * @throws HttpException
     * @throws InvalidConfigException
     */
    public function actionAdd(): ?Response
    {
        $this->requirePostRequest();

        $plugin = Plugin::getInstance();

        // Are we paying anonymously?
        $customer = Craft::$app->getUser()->getIdentity();

        if (!$customer) {
            throw new HttpException(401, Craft::t('commerce', 'You must be logged in to create a payment source.'));
        }

        // Allow setting the payment method at time of submitting payment.
        $gatewayId = $this->request->getRequiredBodyParam('gatewayId');

        $isPrimaryPaymentSource = $this->request->getBodyParam('isPrimaryPaymentSource', false);

        $gateway = $plugin->getGateways()->getGatewayById($gatewayId);

        if (!$gateway || !$gateway->supportsPaymentSources()) {
            return $this->asFailure(Craft::t('commerce', 'There is no gateway selected that supports payment sources.'));
        }

        // Get the payment method' gateway adapter's expected form model
        $paymentForm = $gateway->getPaymentFormModel();
        $paymentFormParams = $this->request->getBodyParam(PaymentForm::getPaymentFormParamName($gateway->handle), []);
        $paymentForm->setAttributes($paymentFormParams, false);
        $description = (string)$this->request->getBodyParam('description');

        try {
            $paymentSource = $plugin->getPaymentSources()->createPaymentSource($customer->id, $gateway, $paymentForm, $description);
        } catch (Throwable $exception) {
            Craft::$app->getErrorHandler()->logException($exception);
            return $this->asModelFailure(
                $paymentForm,
                Craft::t('commerce', 'Could not create the payment source.'),
                'paymentForm',
                ['paymentFormErrors' => $paymentForm->getErrors()]
            );
        }

        if ($isPrimaryPaymentSource) {
            $plugin->getCustomers()->savePrimaryPaymentSourceId($customer, $paymentSource->id);
        }

        return $this->asModelSuccess(
            $paymentSource,
            Craft::t('commerce', 'Payment source created.'),
            'paymentSource'
        );
    }

    /**
     * @return Response|null
     * @throws BadRequestHttpException
     * @throws HttpException
     * @throws InvalidConfigException
     * @since 4.2
     */
    public function actionSetPrimaryPaymentSource(): ?Response
    {
        $this->requirePostRequest();

        $user = Craft::$app->getUser()->getIdentity();
        if (!$user) {
            throw new HttpException(401, Craft::t('commerce', 'You must be logged in to set a primary payment source.'));
        }

        $paymentSourceId = $this->request->getRequiredBodyParam('id');

        // Check payment source exists and belongs to the user
        $paymentSource = Plugin::getInstance()->getPaymentSources()->getPaymentSourceByIdAndUserId($paymentSourceId, $user->id);
        if (!$paymentSource) {
            return $this->asFailure(
                Craft::t('commerce', 'Unable to retrieve payment source.'),
                ['paymentSourceId' => $paymentSourceId],
                ['paymentSourceId' => $paymentSourceId]
            );
        }

        if (!Plugin::getInstance()->getCustomers()->savePrimaryPaymentSourceId($user, $paymentSource->id)) {
            return $this->asFailure(
                Craft::t('commerce', 'Unable to set primary payment source.'),
                ['paymentSourceId' => $paymentSourceId],
                ['paymentSourceId' => $paymentSourceId]
            );
        }

        return $this->asSuccess(
            Craft::t('commerce', 'Primary payment source updated.')
        );
    }

    /**
     * Deletes a payment source.
     *
     * @throws Throwable if failed to delete the payment source on the gateway
     * @throws BadRequestHttpException if user not logged in
     */
    public function actionDelete(): ?Response
    {
        $this->requirePostRequest();
        $this->requireLogin();

        $id = $this->request->getRequiredBodyParam('id');

        $paymentSources = Commerce::getInstance()->getPaymentSources();
        $paymentSource = $paymentSources->getPaymentSourceById($id);

        if (!$paymentSource) {
            return null;
        }

        $currentUser = Craft::$app->getUser()->getIdentity();

        if ($paymentSource->getCustomer()?->id != $currentUser->getId() && !$currentUser->can('commerce-manageOrders')) {
            return null;
        }

        $result = $paymentSources->deletePaymentSourceById($id);

        if ($result) {
            return $this->asModelSuccess($paymentSource, Craft::t('commerce', 'Payment source deleted.'));
        }

        return $this->asModelFailure($paymentSource, Craft::t('commerce', 'Couldn’t delete the payment source.'));
    }
}
