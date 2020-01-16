<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\base\Element;
use craft\commerce\base\SubscriptionGateway;
use craft\commerce\elements\Subscription;
use craft\commerce\errors\SubscriptionException;
use craft\commerce\Plugin as Commerce;
use craft\commerce\Plugin;
use craft\commerce\web\assets\commercecp\CommerceCpAsset;
use craft\helpers\StringHelper;
use craft\helpers\UrlHelper;
use craft\models\FieldLayout;
use Throwable;
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
        $variables = [];
        $this->requirePermission('commerce-manageSubscriptions');
        $this->getView()->registerAssetBundle(CommerceCpAsset::class);

        if ($subscription === null && $subscriptionId) {
            $subscription = Subscription::find()->anyStatus()->id($subscriptionId)->one();
        }

        $fieldLayout = Craft::$app->getFields()->getLayoutByType(Subscription::class);

        $variables['tabs'] = [];

        $variables['tabs'][] = [
            'label' => Plugin::t('Manage'),
            'url' => '#subscriptionManageTab',
            'class' => null
        ];

        foreach ($fieldLayout->getTabs() as $index => $tab) {
            // Do any of the fields on this tab have errors?
            $hasErrors = false;

            if ($subscription->hasErrors()) {
                foreach ($tab->getFields() as $field) {
                    if ($subscription->getErrors($field->handle)) {
                        $hasErrors = true;
                        break;
                    }
                }
            }

            $variables['tabs'][] = [
                'label' => Plugin::t($tab->name),
                'url' => '#tab' . ($index + 1),
                'class' => $hasErrors ? 'error' : null
            ];
        }

        $variables['continueEditingUrl'] = $subscription->cpEditUrl;
        $variables['subscriptionId'] = $subscriptionId;
        $variables['subscription'] = $subscription;
        $variables['fieldLayout'] = $fieldLayout;

        return $this->renderTemplate('commerce/subscriptions/_edit', $variables);
    }

    /**
     * Save a subscription's custom fields.
     *
     * @return Response|null
     * @throws NotFoundHttpException if subscription not found
     * @throws ForbiddenHttpException if permissions are lacking
     * @throws HttpException if invalid data posted
     * @throws Throwable if reasons
     */
    public function actionSave()
    {
        $this->requirePostRequest();
        $this->requirePermission('commerce-manageSubscriptions');

        $subscriptionId = Craft::$app->getRequest()->getRequiredBodyParam('subscriptionId');

        if (!$subscription = Subscription::find()->anyStatus()->id($subscriptionId)->one()) {
            throw new NotFoundHttpException('Subscription not found');
        }

        $subscription->setFieldValuesFromRequest('fields');

        $subscription->setScenario(Element::SCENARIO_LIVE);

        if (!Craft::$app->getElements()->saveElement($subscription)) {
            Craft::$app->getSession()->setError(Plugin::t('Couldn’t save subscription.'));
            Craft::$app->getUrlManager()->setRouteParams([
                'subscription' => $subscription
            ]);
            return null;
        }

        return $this->redirectToPostedUrl($subscription);
    }

    /**
     * Refreshes all subscription payments
     *
     * @throws BadRequestHttpException If not POST request
     * @throws ForbiddenHttpException If permissions are lacking
     * @throws NotFoundHttpException If subscription not found
     * @throws InvalidConfigException
     */
    public function actionRefreshPayments()
    {
        $this->requirePostRequest();
        $this->requirePermission('commerce-manageSubscriptions');

        $subscriptionId = Craft::$app->getRequest()->getRequiredBodyParam('subscriptionId');

        if (!$subscription = Subscription::find()->anyStatus()->id($subscriptionId)->one()) {
            throw new NotFoundHttpException('Subscription not found');
        }

        $gateway = $subscription->getGateway();
        $gateway->refreshPaymentHistory($subscription);

        // Save
        return $this->redirectToPostedUrl($subscription);
    }

    /**
     * @return Response|null
     * @throws Exception
     * @throws HttpException if request does not match requirements
     * @throws InvalidConfigException if gateway does not support subscriptions
     * @throws BadRequestHttpException
     */
    public function actionSubscribe()
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

        $error = null;

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
            } catch (Throwable $exception) {
                Craft::$app->getErrorHandler()->logException($exception);

                throw new SubscriptionException(Plugin::t('Unable to start the subscription. Please check your payment details.'));
            }
        } catch (SubscriptionException $exception) {
            $error = $exception->getMessage();
        }

        if (!$error && $subscription->isSuspended && !$subscription->hasStarted) {
            $url = Plugin::getInstance()->getSettings()->updateBillingDetailsUrl;

            if (empty($url)) {
                $error = Plugin::t('Unable to start the subscription. Please check your payment details.');
            } else {
                return $this->redirect(UrlHelper::url($url, ['subscription' => $subscription->uid]));
            }
        }

        if ($error) {
            if ($request->getAcceptsJson()) {
                return $this->asErrorJson($error);
            }

            $session->setError($error);
            return null;
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
     * @return Response|null
     * @throws InvalidConfigException
     * @throws BadRequestHttpException
     */
    public function actionReactivate()
    {
        $this->requireLogin();
        $this->requirePostRequest();

        $session = Craft::$app->getSession();
        $plugin = Commerce::getInstance();

        $request = Craft::$app->getRequest();

        $error = false;
        $subscription = null;

        try {
            $subscriptionUid = $request->getValidatedBodyParam('subscriptionUid');
            $subscription = Subscription::find()->anyStatus()->uid($subscriptionUid)->one();
            $userSession = Craft::$app->getUser();

            $validData = $subscriptionUid && $subscription;
            $validAction = $subscription->canReactivate();
            $canModifySubscription = ($subscription->userId === $userSession->getId()) || $userSession->checkPermission('commerce-manageSubscriptions');

            if ($validData && $validAction && $canModifySubscription) {
                if (!$plugin->getSubscriptions()->reactivateSubscription($subscription)) {
                    $error = Plugin::t('Unable to reactivate subscription at this time.');
                }
            } else {
                $error = Plugin::t('Unable to reactivate subscription at this time.');
            }
        } catch (Exception $exception) {
            $error = $exception->getMessage();
        }

        if ($error) {
            if ($request->getAcceptsJson()) {
                return $this->asErrorJson($error);
            }

            $session->setError($error);

            return null;
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
     * @return Response|null
     * @throws InvalidConfigException
     * @throws BadRequestHttpException
     */
    public function actionSwitch()
    {
        $this->requireLogin();
        $this->requirePostRequest();

        $session = Craft::$app->getSession();
        $plugin = Commerce::getInstance();

        $request = Craft::$app->getRequest();
        $subscriptionUid = $request->getValidatedBodyParam('subscriptionUid');
        $planUid = $request->getValidatedBodyParam('planUid');

        $error = false;
        $subscription = null;

        try {
            $subscription = Subscription::find()->anyStatus()->uid($subscriptionUid)->one();
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
                    $error = Plugin::t('Unable to modify subscription at this time.');
                }
            } else {
                $error = Plugin::t('Unable to modify subscription at this time.');
            }
        } catch (SubscriptionException $exception) {
            $error = $session->setError($exception->getMessage());
        }

        if ($error) {
            if ($request->getAcceptsJson()) {
                return $this->asErrorJson($error);
            }

            $session->setError($error);

            return null;
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
     * @return Response|null
     * @throws InvalidConfigException
     * @throws BadRequestHttpException
     */
    public function actionCancel()
    {
        $this->requireLogin();
        $this->requirePostRequest();

        $session = Craft::$app->getSession();
        $plugin = Commerce::getInstance();
        $request = Craft::$app->getRequest();

        $error = false;
        $subscription = null;

        try {
            $subscriptionUid = $request->getValidatedBodyParam('subscriptionUid');

            $subscription = Subscription::find()->anyStatus()->uid($subscriptionUid)->one();
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
                    $error = Plugin::t('Unable to cancel subscription at this time.');
                }
            } else {
                $error = Plugin::t('Unable to cancel subscription at this time.');
            }
        } catch (SubscriptionException $exception) {
            $error = $exception->getMessage();
        }

        if ($error) {
            if ($request->getAcceptsJson()) {
                return $this->asErrorJson($error);
            }

            $session->setError($error);

            return null;
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
