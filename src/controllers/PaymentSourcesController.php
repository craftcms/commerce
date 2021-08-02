<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\base\Gateway;
use craft\commerce\Plugin;
use craft\commerce\Plugin as Commerce;
use craft\errors\MissingComponentException;
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
     * @return Response|null
     * @throws BadRequestHttpException
     * @throws HttpException
     * @throws InvalidConfigException
     */
    public function actionAdd(): ?Response
    {
        $this->requirePostRequest();

        $plugin = Plugin::getInstance();

        // Are we paying anonymously?
        $userId = Craft::$app->getUser()->getId();

        if (!$userId) {
            throw new HttpException(401, Craft::t('commerce', 'You must be logged in to create a payment source.'));
        }

        // Allow setting the payment method at time of submitting payment.
        $gatewayId = $this->request->getRequiredBodyParam('gatewayId');

        /** @var Gateway $gateway */
        $gateway = $plugin->getGateways()->getGatewayById($gatewayId);

        if (!$gateway || !$gateway->supportsPaymentSources()) {
            $error = Craft::t('commerce', 'There is no gateway selected that supports payment sources.');

            if ($this->request->getAcceptsJson()) {
                return $this->asErrorJson($error);
            }

            $this->setFailFlash($error);

            return null;
        }

        // Get the payment method' gateway adapter's expected form model
        $paymentForm = $gateway->getPaymentFormModel();
        $paymentForm->setAttributes($this->request->getBodyParams(), false);
        $description = (string)$this->request->getBodyParam('description');

        try {
            $paymentSource = $plugin->getPaymentSources()->createPaymentSource($userId, $gateway, $paymentForm, $description);
        } catch (Throwable $exception) {
            Craft::$app->getErrorHandler()->logException($exception);
            $error = Craft::t('commerce', 'Could not create the payment source.');

            if ($this->request->getAcceptsJson()) {
                return $this->asJson([
                    'error' => $error,
                    'paymentFormErrors' => $paymentForm->getErrors(),
                ]);
            }

            $this->setFailFlash($error);
            Craft::$app->getUrlManager()->setRouteParams(compact('paymentForm'));

            return null;
        }

        if ($this->request->getAcceptsJson()) {
            return $this->asJson([
                'success' => true,
                'paymentSource' => $paymentSource
            ]);
        }

        $this->setSuccessFlash(Craft::t('commerce', 'Payment source created.'));

        return $this->redirectToPostedUrl();
    }

    /**
     * Deletes a payment source.
     *
     * @return Response|null
     * @throws Throwable if failed to delete the payment source on the gateway
     * @throws BadRequestHttpException if user not logged in
     */
    public function actionDelete(): ?Response
    {
        $this->requirePostRequest();
        $this->requireLogin();

        $id = Craft::$app->getRequest()->getRequiredBodyParam('id');

        $paymentSources = Commerce::getInstance()->getPaymentSources();
        $paymentSource = $paymentSources->getPaymentSourceById($id);

        if (!$paymentSource) {
            return null;
        }

        $currentUser = Craft::$app->getUser()->getIdentity();

        if ($paymentSource->userId != $currentUser->getId() && !$currentUser->can('commerce-manageOrders')) {
            return null;
        }

        $result = $paymentSources->deletePaymentSourceById($id);

        if ($result) {
            if ($this->request->getAcceptsJson()) {
                return $this->asJson(['success' => true]);
            }

            $this->setSuccessFlash(Craft::t('commerce', 'Payment source deleted.'));
        } else {
            if ($this->request->getAcceptsJson()) {
                return $this->asErrorJson(Craft::t('commerce', 'Couldn’t delete the payment source.'));
            }

            $this->setFailFlash(Craft::t('commerce', 'Couldn’t delete the payment source.'));
        }

        return $this->redirectToPostedUrl();
    }
}
