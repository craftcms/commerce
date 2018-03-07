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
 * TODO when checking if subscription is currentUser's, allow for commerce permission instead of admin
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
            $variables['subscription'] = Subscription::find()->id($subscriptionId)->one();
        }

        return $this->renderTemplate('commerce/subscriptions/_edit', $variables);
    }

    /**
     * Save a subscription's custom fields.
     *
     * @return Response|null
     * @throws NotFoundHttpException if rsubscription not found
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
            $this->redirectToPostedUrl($subscription);
        }

        Craft::$app->getSession()->setError(Craft::t('commerce', 'Couldn’t save subscription..'));
        Craft::$app->getUrlManager()->setRouteParams([
            'subscriptions' => $subscription
        ]);
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
        $planId = $request->getValidatedBodyParam('planId');

        if (!$planId || !$plan = $plugin->getPlans()->getPlanById($planId)) {
            throw new InvalidConfigException('Subscription plan not found with that id.');
        }

        $error = false;

        try {
            /** @var SubscriptionGateway $gateway */
            $gateway = $plan->getGateway();
            $parameters = $gateway->getSubscriptionFormModel();

            foreach ($parameters->attributes() as $attributeName) {
                $parameters->{$attributeName} = $request->getValidatedBodyParam($attributeName);
            }

            try {
                $paymentForm = $gateway->getPaymentFormModel();
                $paymentForm->setAttributes($request->getBodyParams(), false);

                if ($paymentForm->validate()) {
                    $plugin->getPaymentSources()->createPaymentSource(Craft::$app->getUser()->getId(), $gateway, $paymentForm);
                }
            } catch (\Throwable $exception) {
                Craft::error($exception->getMessage(), 'commerce');

                throw new SubscriptionException(Craft::t('commerce', 'Unable to start the subscription. Please check your payment details.'));
            }

            $subscription = $plugin->getSubscriptions()->createSubscription(Craft::$app->getUser()->getIdentity(), $plan, $parameters);
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
            $subscriptionId = $request->getValidatedBodyParam('subscriptionId');
            $subscription = Subscription::find()->id($subscriptionId)->one();
            $currentUser = Craft::$app->getUser();

            $validData = $subscriptionId && $subscription;
            $validAction = $subscription->canReactivate();
            $canModifySubscription = ($subscription->userId === $currentUser->getId()) || $currentUser->getIsAdmin();

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
        $subscriptionId = $request->getValidatedBodyParam('subscriptionId');
        $planId = $request->getValidatedBodyParam('planId');

        $error = false;

        try {
            $subscription = Subscription::find()->id($subscriptionId)->one();
            $plan = Commerce::getInstance()->getPlans()->getPlanById($planId);
            $currentUser = Craft::$app->getUser();

            $validData = $planId && $plan && $subscriptionId && $subscription;
            $validAction = $plan->canSwitchFrom($subscription->getPlan());
            $canModifySubscription = ($subscription->userId === $currentUser->getId()) || $currentUser->getIsAdmin();

            if ($validData && $validAction && $canModifySubscription) {
                /** @var SubscriptionGateway $gateway */
                $gateway = $subscription->getGateway();
                $parameters = $gateway->getSwitchPlansFormModel();

                foreach ($parameters->attributes() as $attributeName) {
                    $parameters->{$attributeName} = $request->getValidatedBodyParam($attributeName);
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
            $subscriptionId = $request->getValidatedBodyParam('subscriptionId');

            $subscription = Subscription::find()->id($subscriptionId)->one();
            $currentUser = Craft::$app->getUser();

            $validData = $subscriptionId && $subscription;
            $canModifySubscription = ($subscription->userId === $currentUser->getId()) || $currentUser->getIsAdmin();

            if ($validData && $canModifySubscription) {
                /** @var SubscriptionGateway $gateway */
                $gateway = $subscription->getGateway();
                $parameters = $gateway->getCancelSubscriptionFormModel();

                foreach ($parameters->attributes() as $attributeName) {
                    $parameters->{$attributeName} = $request->getValidatedBodyParam($attributeName);
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
