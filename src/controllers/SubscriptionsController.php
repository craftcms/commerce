<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\base\SubscriptionGateway;
use craft\commerce\elements\Subscription;
use craft\commerce\errors\SubscriptionException;
use craft\commerce\Plugin as Commerce;
use craft\commerce\web\assets\commercecp\CommerceCpAsset;
use craft\helpers\StringHelper;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\HttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Class Subscriptions Controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class SubscriptionsController extends BaseController
{
    // Public Methods
    // =========================================================================

    /**
     * @return Response
     */
    public function actionIndex(): Response
    {
        $this->requirePermission('commerce-manageSubscriptions');
        return $this->renderTemplate('commerce/subscriptions/_index');
    }

    /**
     * @param int|null $subscriptionId
     * @param Subscription|null $subscription
     * @return Response
     * @throws HttpException
     * @throws InvalidConfigException
     */
    public function actionEdit(int $subscriptionId = null, Subscription $subscription = null): Response
    {

        $this->requirePermission('commerce-manageSubscriptions');

        $this->getView()->registerAssetBundle(CommerceCpAsset::class);
        $fieldLayout = Craft::$app->getFields()->getLayoutByType(Subscription::class);

        $variables = [
            'subscriptionId' => $subscriptionId,
            'subscription' => $subscription,
            'fieldLayout' => $fieldLayout
        ];

        if (empty($variables['subscription'])) {
            $variables['subscription'] = Subscription::find()->anyStatus()->id($subscriptionId)->one();
        }

        return $this->renderTemplate('commerce/subscriptions/_edit', $variables);
    }

    /**
     * Save a subscription's custom fields.
     *
     * @return Response|null
     * @throws NotFoundHttpException if subscription not found
     * @throws ForbiddenHttpException if permissions are lacking
     * @throws HttpException if invalid data posted
     * @throws \Throwable if reasons
     */
    public function actionSave()
    {
        $this->requirePostRequest();
        $this->requirePermission('commerce-manageSubscriptions');

        $subscriptionId = Craft::$app->getRequest()->getRequiredBodyParam('subscriptionId');

        if (!$subscription = Subscription::find()->id($subscriptionId)->one()) {
            throw new NotFoundHttpException('Subscription not found');
        }

        $subscription->setFieldValuesFromRequest('fields');

        if (Craft::$app->getElements()->saveElement($subscription)) {
            return $this->redirectToPostedUrl($subscription);
        }

        Craft::$app->getSession()->setError(Craft::t('commerce', 'Couldn’t save subscription..'));
        Craft::$app->getUrlManager()->setRouteParams([
            'subscriptions' => $subscription
        ]);
    }

    /**
     * Refreshes all subscription payments
     *
     * @return Response|null
     * @throws BadRequestHttpException If not POST request
     * @throws ForbiddenHttpException If permissions are lacking
     * @throws NotFoundHttpException If subscription not found
     */
    public function actionRefreshPayments(): Response
    {
        $this->requirePostRequest();
        $this->requirePermission('commerce-manageSubscriptions');

        $subscriptionId = Craft::$app->getRequest()->getRequiredBodyParam('subscriptionId');

        if (!$subscription = Subscription::find()->id($subscriptionId)->one()) {
            throw new NotFoundHttpException('Subscription not found');
        }

        $gateway = $subscription->getGateway();
        $gateway->refreshPaymentHistory($subscription);

        // Save
        return $this->redirectToPostedUrl($subscription);
    }

    /**
     * @throws Exception
     * @throws HttpException if request does not match requirements
     * @throws InvalidConfigException if gateway does not support subscriptions
     * @throws BadRequestHttpException
     */
    public function actionSubscribe(): Response
    {
        $this->requireLogin();
        $this->requirePostRequest();

        $session = Craft::$app->getSession();
        $plugin = Commerce::getInstance();

        $request = Craft::$app->getRequest();
        $planUid = $request->getValidatedBodyParam('planUid');

        if (!$planUid || !$plan = $plugin->getPlans()->getPlanByUid($planUid)) {
            throw new InvalidConfigException('Subscription plan not found with that id.');
        }

        $error = false;

        try {
            /** @var SubscriptionGateway $gateway */
            $gateway = $plan->getGateway();
            $parameters = $gateway->getSubscriptionFormModel();

            foreach ($parameters->attributes() as $attributeName) {
                $value = $request->getValidatedBodyParam($attributeName);

                if (is_string($value) && StringHelper::countSubstrings($value, ':') > 0) {
                    list($hashedPlanUid, $parameterValue) = explode(':', $value);

                    if ($plan->uid == $hashedPlanUid) {
                        $parameters->{$attributeName} = $parameterValue;
                    }
                }
            }

            try {
                $paymentForm = $gateway->getPaymentFormModel();
                $paymentForm->setAttributes($request->getBodyParams(), false);

                if ($paymentForm->validate()) {
                    $plugin->getPaymentSources()->createPaymentSource(Craft::$app->getUser()->getId(), $gateway, $paymentForm);
                }

                $fieldsLocation = Craft::$app->getRequest()->getParam('fieldsLocation', 'fields');
                $fieldValues = $request->getBodyParam($fieldsLocation, []);

                $subscription = $plugin->getSubscriptions()->createSubscription(Craft::$app->getUser()->getIdentity(), $plan, $parameters, $fieldValues);
            } catch (\Throwable $exception) {
                Craft::error($exception->getMessage(), 'commerce');

                throw new SubscriptionException(Craft::t('commerce', 'Unable to start the subscription. Please check your payment details.'));
            }

        } catch (SubscriptionException $exception) {
            $error = $exception->getMessage();
        }

        if ($error) {
            if ($request->getAcceptsJson()) {
                return $this->asErrorJson($error);
            }

            $session->setError($error);
        }

        if ($request->getAcceptsJson()) {
            return $this->asJson([
                'success' => true,
                'subscription' => $subscription
            ]);
        }

        return $this->redirectToPostedUrl();
    }

    /**
     * @return Response
     * @throws InvalidConfigException
     * @throws BadRequestHttpException
     */
    public function actionReactivate(): Response
    {
        $this->requireLogin();
        $this->requirePostRequest();

        $session = Craft::$app->getSession();
        $plugin = Commerce::getInstance();

        $request = Craft::$app->getRequest();

        $error = false;

        try {
            $subscriptionUid = $request->getValidatedBodyParam('subscriptionUid');
            $subscription = Subscription::find()->uid($subscriptionUid)->one();
            $userSession = Craft::$app->getUser();

            $validData = $subscriptionUid && $subscription;
            $validAction = $subscription->canReactivate();
            $canModifySubscription = ($subscription->userId === $userSession->getId()) || $userSession->checkPermission('commerce-manageSubscriptions');

            if ($validData && $validAction && $canModifySubscription) {
                if (!$plugin->getSubscriptions()->reactivateSubscription($subscription)) {
                    $error = Craft::t('commerce', 'Unable to reactivate subscription at this time.');
                }
            } else {
                $error = Craft::t('commerce', 'Unable to reactivate subscription at this time.');
            }
        } catch (SubscriptionException $exception) {
            $error = $exception->getMessage();
        }

        if ($error) {
            if ($request->getAcceptsJson()) {
                return $this->asErrorJson($error);
            }

            $session->setError($error);
        }

        if ($request->getAcceptsJson()) {
            return $this->asJson([
                'success' => true,
                'subscription' => $subscription
            ]);
        }

        return $this->redirectToPostedUrl();
    }

    /**
     * @return Response
     * @throws InvalidConfigException
     * @throws BadRequestHttpException
     */
    public function actionSwitch(): Response
    {
        $this->requireLogin();
        $this->requirePostRequest();

        $session = Craft::$app->getSession();
        $plugin = Commerce::getInstance();

        $request = Craft::$app->getRequest();
        $subscriptionUid = $request->getValidatedBodyParam('subscriptionUid');
        $planUid = $request->getValidatedBodyParam('planUid');

        $error = false;

        try {
            $subscription = Subscription::find()->uid($subscriptionUid)->one();
            $plan = Commerce::getInstance()->getPlans()->getPlanByUid($planUid);
            $userSession = Craft::$app->getUser();

            $validData = $planUid && $plan && $subscriptionUid && $subscription;
            $validAction = $plan->canSwitchFrom($subscription->getPlan());
            $canModifySubscription = ($subscription->userId === $userSession->getId()) || $userSession->checkPermission('commerce-manageSubscriptions');

            if ($validData && $validAction && $canModifySubscription) {
                /** @var SubscriptionGateway $gateway */
                $gateway = $subscription->getGateway();
                $parameters = $gateway->getSwitchPlansFormModel();

                foreach ($parameters->attributes() as $attributeName) {
                    $value = $request->getValidatedBodyParam($attributeName);

                    if (is_string($value) && StringHelper::countSubstrings($value, ':') > 0) {
                        list($hashedPlanUid, $parameterValue) = explode(':', $value);

                        if ($hashedPlanUid == $planUid) {
                            $parameters->{$attributeName} = $parameterValue;
                        }
                    }
                }

                if (!$plugin->getSubscriptions()->switchSubscriptionPlan($subscription, $plan, $parameters)) {
                    $error = Craft::t('commerce', 'Unable to modify subscription at this time.');
                }
            } else {
                $error = Craft::t('commerce', 'Unable to modify subscription at this time.');
            }
        } catch (SubscriptionException $exception) {
            $error = $session->setError($exception->getMessage());
        }

        if ($error) {
            if ($request->getAcceptsJson()) {
                return $this->asErrorJson($error);
            }

            $session->setError($error);
        }

        if ($request->getAcceptsJson()) {
            return $this->asJson([
                'success' => true,
                'subscription' => $subscription
            ]);
        }

        return $this->redirectToPostedUrl();
    }

    /**
     * @return Response
     * @throws InvalidConfigException
     * @throws BadRequestHttpException
     */
    public function actionCancel(): Response
    {
        $this->requireLogin();
        $this->requirePostRequest();

        $session = Craft::$app->getSession();
        $plugin = Commerce::getInstance();
        $request = Craft::$app->getRequest();

        $error = false;

        try {
            $subscriptionUid = $request->getValidatedBodyParam('subscriptionUid');

            $subscription = Subscription::find()->uid($subscriptionUid)->one();
            $userSession = Craft::$app->getUser();

            $validData = $subscriptionUid && $subscription;
            $canModifySubscription = ($subscription->userId === $userSession->getId()) || $userSession->checkPermission('commerce-manageSubscriptions');

            if ($validData && $canModifySubscription) {
                /** @var SubscriptionGateway $gateway */
                $gateway = $subscription->getGateway();
                $parameters = $gateway->getCancelSubscriptionFormModel();

                foreach ($parameters->attributes() as $attributeName) {
                    $value = $request->getValidatedBodyParam($attributeName);

                    if (is_string($value) && StringHelper::countSubstrings($value, ':') > 0) {
                        list($hashedSubscriptionUid, $parameterValue) = explode(':', $value);

                        if ($hashedSubscriptionUid == $subscriptionUid) {
                            $parameters->{$attributeName} = $parameterValue;
                        }
                    }
                }

                if (!$plugin->getSubscriptions()->cancelSubscription($subscription, $parameters)) {
                    $error = Craft::t('commerce', 'Unable to cancel subscription at this time.');
                }
            } else {
                $error = Craft::t('commerce', 'Unable to cancel subscription at this time.');
            }
        } catch (SubscriptionException $exception) {
            $error = $exception->getMessage();
        }

        if ($error) {
            if ($request->getAcceptsJson()) {
                return $this->asErrorJson($error);
            }

            $session->setError($error);
        }

        if ($request->getAcceptsJson()) {
            return $this->asJson([
                'success' => true,
                'subscription' => $subscription
            ]);
        }

        return $this->redirectToPostedUrl();
    }
}
