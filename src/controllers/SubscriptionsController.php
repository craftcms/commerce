<?php

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\base\SubscriptionGateway;
use craft\commerce\base\SubscriptionGatewayInterface;
use craft\commerce\elements\Subscription;
use craft\commerce\errors\SubscriptionException;
use craft\commerce\Plugin as Commerce;
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
 * @since  2.0
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
     * @param int|null  $subscriptionId
     * @param Subscription|null $subscription
     *
     * @return Response
     * @throws HttpException
     */
    public function actionEdit(int $subscriptionId = null, Subscription $subscription = null): Response
    {
        $this->requirePermission('commerce-manageSubscriptions');

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

        try {
            /** @var SubscriptionGateway $gateway */
            $gateway = $plan->getGateway();
            $parameters = $gateway->getSubscriptionFormModel();

            foreach ($parameters->attributes() as $attributeName) {
                $parameters->{$attributeName} = $request->getValidatedBodyParam($attributeName);
            }

            $success = $plugin->getSubscriptions()->subscribe(Craft::$app->getUser()->getIdentity(), $plan, $parameters);

            if (!$success) {
                $session->setError(Craft::t('commerce', 'Unable to subscribe at this time.'));
            }

        } catch (SubscriptionException $exception) {
            $session->setError($exception->getMessage());
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

        try {
            $subscriptionId = $request->getValidatedBodyParam('subscriptionId');
            $subscription = Subscription::find()->id($subscriptionId)->one();
            $currentUser = Craft::$app->getUser();

            $validData = $subscriptionId && $subscription;
            $validAction = $subscription->canReactivate();
            $canModifySubscription = ($subscription->userId === $currentUser->getId()) || $currentUser->getIsAdmin();

            if (!($validData && $validAction && $canModifySubscription)) {
                throw new SubscriptionException(Craft::t('commerce', 'Unable to reactivate subscription at this time.'));
            }

            $success = $plugin->getSubscriptions()->reactivateSubscription($subscription);

            if (!$success) {
                $session->setError(Craft::t('commerce', 'Unable to reactivate subscription at this time.'));
            }

        } catch (SubscriptionException $exception) {
            $session->setError($exception->getMessage());
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

        try {
            $subscription = Subscription::find()->id($subscriptionId)->one();
            $plan = Commerce::getInstance()->getPlans()->getPlanById($planId);
            $currentUser = Craft::$app->getUser();

            $validData = $planId && $plan && $subscriptionId && $subscription;
            $validAction = $plan->canSwitchFrom($subscription->getPlan());
            $canModifySubscription = ($subscription->userId === $currentUser->getId()) || $currentUser->getIsAdmin();

            if (!($validData && $validAction && $canModifySubscription)) {
                throw new SubscriptionException(Craft::t('commerce', 'Unable to modify subscription at this time.'));
            }

            /** @var SubscriptionGateway $gateway */
            $gateway = $subscription->getGateway();
            $parameters = $gateway->getSwitchPlansFormModel();

            foreach ($parameters->attributes() as $attributeName) {
                $parameters->{$attributeName} = $request->getValidatedBodyParam($attributeName);
            }

            $success = $plugin->getSubscriptions()->switchSubscriptionPlan($subscription, $plan, $parameters);

            if (!$success) {
                $session->setError(Craft::t('commerce', 'Unable to modify subscription at this time.'));
            }

        } catch (SubscriptionException $exception) {
            $session->setError($exception->getMessage());
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

        try {
            $subscriptionId = $request->getValidatedBodyParam('subscriptionId');

            $subscription = Subscription::find()->id($subscriptionId)->one();
            $currentUser = Craft::$app->getUser();

            $validData = $subscriptionId && $subscription;
            $canModifySubscription = ($subscription->userId === $currentUser->getId()) || $currentUser->getIsAdmin();

            if (!($validData && $canModifySubscription)) {
                throw new SubscriptionException(Craft::t('commerce', 'Unable to cancel subscription at this time.'));
            }

            /** @var SubscriptionGateway $gateway */
            $gateway = $subscription->getGateway();
            $parameters = $gateway->getCancelSubscriptionFormModel();

            foreach ($parameters->attributes() as $attributeName) {
                $parameters->{$attributeName} = $request->getValidatedBodyParam($attributeName);
            }

            $success = $plugin->getSubscriptions()->cancelSubscription($subscription, $parameters);
            
            if (!$success) {
                $session->setError(Craft::t('commerce', 'Unable to cancel subscription at this time.'));
            }

        } catch (SubscriptionException $exception) {
            $session->setError($exception->getMessage());
        }

        return $this->redirectToPostedUrl();
    }
}
