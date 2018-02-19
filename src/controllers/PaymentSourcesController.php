<?php

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\base\Gateway;
use craft\commerce\Plugin as Commerce;
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
     * @throws \Throwable if something went wrong when adding the payment source
     */
    public function actionAdd()
    {
        $this->requirePostRequest();

        $order = null;

        $plugin = Commerce::getInstance();
        $request = Craft::$app->getRequest();
        $session = Craft::$app->getSession();

        // Are we paying anonymously?
        $userId = Craft::$app->getUser()->getId();

        if (!$userId) {
            throw new HttpException(401, Craft::t('commerce', 'Not authorized to make payments on this order.'));
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

        // Save the return and cancel URLs to the order
        $returnUrl = $request->getValidatedBodyParam('redirect');

        $error = '';

        try {
            $success = $plugin->getPaymentSources()->createPaymentSource($userId, $gateway, $paymentForm, $description);
        } catch (\Throwable $exception) {
            $error = $exception->getMessage();
            $success = false;
        }

        if ($success) {
            if ($request->getAcceptsJson()) {
                $response = ['success' => true];

                return $this->asJson($response);
            }

            if ($returnUrl) {
                $this->redirect($returnUrl);
            } else {
                $this->redirectToPostedUrl($order);
            }
        } else {
            if ($request->getAcceptsJson()) {
                return $this->asJson(['error' => $error, 'paymentForm' => $paymentForm->getErrors()]);
            }

            $session->setError($error);
            Craft::$app->getUrlManager()->setRouteParams(compact('paymentForm'));
        }

        return $this->redirectToPostedUrl();
    }

    /**
     * Deletes a payment source.
     *
     * @return Response|null
     * @throws \Throwable if failed to delete the payment source on the gateway
     * @throws \yii\web\BadRequestHttpException if user not logged in
     */
    public function actionDelete()
    {
        $this->requirePostRequest();
        $this->requireLogin();

        $id = Craft::$app->getRequest()->getParam('id');

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
            Craft::$app->getSession()->setNotice(Craft::t('commerce', 'Payment source deleted.'));
        } else {
            Craft::$app->getSession()->setError(Craft::t('commerce', 'Couldn’t delete the payment source.'));
        }

        return $this->redirectToPostedUrl();
    }
}
