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
    // Public Methods
    // =========================================================================


    /**
     * Adds a payment source.
     *
     * @return Response|null
     * @throws HttpException
     * @throws MissingComponentException
     * @throws InvalidConfigException
     * @throws BadRequestHttpException
     */
    public function actionAdd()
    {
        $this->requirePostRequest();

        $order = null;

        $plugin = Plugin::getInstance();
        $request = Craft::$app->getRequest();
        $session = Craft::$app->getSession();

        // Are we paying anonymously?
        $userId = Craft::$app->getUser()->getId();

        if (!$userId) {
            throw new HttpException(401, Craft::t('commerce', 'You must be logged in to create a payment source.'));
        }

        // Allow setting the payment method at time of submitting payment.
        $gatewayId = $request->getRequiredBodyParam('gatewayId');

        /** @var Gateway $gateway */
        $gateway = $plugin->getGateways()->getGatewayById($gatewayId);

        if (!$gateway || !$gateway->supportsPaymentSources()) {
            $error = Craft::t('commerce', 'There is no gateway selected that supports payment sources.');

            if ($request->getAcceptsJson()) {
                return $this->asErrorJson($error);
            }

            $session->setError($error);

            return null;
        }

        // Get the payment method' gateway adapter's expected form model
        $paymentForm = $gateway->getPaymentFormModel();
        $paymentForm->setAttributes($request->getBodyParams(), false);
        $description = (string)$request->getBodyParam('description');

        try {
            $paymentSource = $plugin->getPaymentSources()->createPaymentSource($userId, $gateway, $paymentForm, $description);
        } catch (Throwable $exception) {
            Craft::$app->getErrorHandler()->logException($exception);
            $error = Craft::t('commerce', 'Could not create the payment source.');

            if ($request->getAcceptsJson()) {
                return $this->asJson([
                    'error' => $error,
                    'paymentForm' => $paymentForm->getErrors(),
                ]);
            }

            $session->setError($error);
            Craft::$app->getUrlManager()->setRouteParams(compact('paymentForm'));

            return null;
        }

        if ($request->getAcceptsJson()) {
            return $this->asJson([
                'success' => true,
                'paymentSource' => $paymentSource
            ]);
        }

        return $this->redirectToPostedUrl();
    }

    /**
     * Deletes a payment source.
     *
     * @return Response|null
     * @throws Throwable if failed to delete the payment source on the gateway
     * @throws BadRequestHttpException if user not logged in
     */
    public function actionDelete()
    {
        $this->requirePostRequest();
        $this->requireLogin();

        $request = Craft::$app->getRequest();

        $id = Craft::$app->getRequest()->getRequiredBodyParam('id');

        $paymentSources = Commerce::getInstance()->getPaymentSources();
        $paymentSource = $paymentSources->getPaymentSourceById($id);

        if (!$paymentSource) {
            return null;
        }

        $currentUser = Craft::$app->getUser()->getIdentity();

        if ($paymentSource->userId !== $currentUser->getId() && !$currentUser->can('commerce-manageOrders')) {
            return null;
        }

        $result = $paymentSources->deletePaymentSourceById($id);

        if ($result) {
            if ($request->getAcceptsJson()) {
                return $this->asJson(['success' => true]);
            }

            Craft::$app->getSession()->setNotice(Craft::t('commerce', 'Payment source deleted.'));
        } else {
            if ($request->getAcceptsJson()) {
                return $this->asErrorJson(Craft::t('commerce', 'Couldn’t delete the payment source.'));
            }

            Craft::$app->getSession()->setError(Craft::t('commerce', 'Couldn’t delete the payment source.'));
        }

        return $this->redirectToPostedUrl();
    }
}
