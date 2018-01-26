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
use yii\web\HttpException;
use yii\web\Response;

/**
 * Class Subscriptions Controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since  2.0
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
        $variables = [
            'subscriptionId' => $subscriptionId,
            'plan' => $subscription,
        ];
    }

    /**
     * @throws Exception
     * @throws HttpException if request does not match requirements
     * @throws InvalidConfigException if gateway does not support subscriptions
     * @throws \yii\web\BadRequestHttpException
     */
    public function actionSubscribe(): Response
    {
        $this->requireLogin();
        $this->requirePostRequest();

        $session = Craft::$app->getSession();
        $plugin = Commerce::getInstance();

        $request = Craft::$app->getRequest();
        $planId = $request->getRequiredBodyParam('planId');
        $plan = $plugin->getPlans()->getPlanById($planId);

        if (!$plan) {
            throw new InvalidConfigException('Subscription plan not found with that id.');
        }

        try {
            $success = $plugin->getSubscriptions()->subscribe(Craft::$app->getUser()->getIdentity(), $plan);

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
     * @throws \yii\web\BadRequestHttpException
     */
    public function actionCancelSubscription(): Response
    {
        $this->requireLogin();
        $this->requirePostRequest();

        $session = Craft::$app->getSession();
        $plugin = Commerce::getInstance();

        $request = Craft::$app->getRequest();
        $subscriptionId = $request->getRequiredBodyParam('subscriptionId');

        try {
            $subscription = Subscription::find()->id($subscriptionId)->one();

            if (!$subscription || $subscription->userId !== Craft::$app->getUser()->getId()) {
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
