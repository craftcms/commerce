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
use craft\commerce\db\Table;
use craft\commerce\elements\Subscription;
use craft\commerce\errors\SubscriptionException;
use craft\commerce\helpers\PaymentForm;
use craft\commerce\Plugin;
use craft\commerce\Plugin as Commerce;
use craft\commerce\records\Subscription as SubscriptionRecord;
use craft\commerce\stripe\gateways\PaymentIntents;
use craft\commerce\web\assets\commercecp\CommerceCpAsset;
use craft\helpers\App;
use craft\helpers\StringHelper;
use craft\helpers\UrlHelper;
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
     * @throws ForbiddenHttpException
     */
    public function actionIndex(): Response
    {
        $this->requirePermission('commerce-manageSubscriptions');
        return $this->renderTemplate('commerce/subscriptions/_index');
    }

    /**
     * @param int|null $subscriptionId
     * @param Subscription|null $subscription
     * @throws HttpException
     * @throws InvalidConfigException
     */
    public function actionEdit(int $subscriptionId = null, Subscription $subscription = null): Response
    {
        $variables = [];

        $this->getView()->registerAssetBundle(CommerceCpAsset::class);

        if ($subscription === null && $subscriptionId) {
            /** @var Subscription|null $subscription */
            $subscription = Subscription::find()->status(null)->id($subscriptionId)->one();
        }

        if (!$subscription) {
            throw new NotFoundHttpException('Subscription not found');
        }

        $this->enforceManageSubscriptionPermissions($subscription);

        $fieldLayout = Craft::$app->getFields()->getLayoutByType(Subscription::class);

        $form = $fieldLayout->createForm($subscription);
        $tabMenu = $form->getTabMenu();
        $tabMenu['tab--subscriptionManageTab'] = [
            'label' => Craft::t('commerce', 'Manage'),
            'url' => '#tab--subscriptionManageTab',
            'class' => null,
        ];
        $variables['tabs'] = $tabMenu;
        $variables['fieldsHtml'] = $form->render();

        $variables['continueEditingUrl'] = $subscription->getCpEditUrl();
        $variables['subscriptionId'] = $subscriptionId;
        $variables['subscription'] = $subscription;
        $variables['fieldLayout'] = $fieldLayout;

        return $this->renderTemplate('commerce/subscriptions/_edit', $variables);
    }

    /**
     * Save a subscription's custom fields.
     *
     * @throws NotFoundHttpException if subscription not found
     * @throws ForbiddenHttpException if permissions are lacking
     * @throws HttpException if invalid data posted
     * @throws Throwable if reasons
     */
    public function actionSave(): ?Response
    {
        $this->requirePostRequest();

        $subscriptionId = $this->request->getRequiredBodyParam('subscriptionId');
        /** @var Subscription|null $subscription */
        $subscription = Subscription::find()->status(null)->id($subscriptionId)->one();

        if (!$subscription) {
            throw new NotFoundHttpException('Subscription not found');
        }

        if (!$this->_canUpdateSubscription($subscription) === true) {
            $this->enforceManageSubscriptionPermissions($subscription);
        }

        $subscription->setFieldValuesFromRequest('fields');

        $subscription->setScenario(Element::SCENARIO_LIVE);

        if (!Craft::$app->getElements()->saveElement($subscription)) {
            $this->setFailFlash(Craft::t('commerce', 'Couldn’t save subscription.'));
            Craft::$app->getUrlManager()->setRouteParams([
                'subscription' => $subscription,
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
    public function actionRefreshPayments(): Response
    {
        $this->requirePostRequest();

        $subscriptionId = $this->request->getRequiredBodyParam('subscriptionId');

        if (!$subscription = Subscription::find()->status(null)->id($subscriptionId)->one()) {
            throw new NotFoundHttpException('Subscription not found');
        }

        /** @var Subscription $subscription */
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
    public function actionSubscribe(): ?Response
    {
        $this->requireLogin();
        $this->requirePostRequest();
        $user = Craft::$app->getUser()->getIdentity();

        $returnUrl = $this->request->getValidatedBodyParam('redirect');

        $plugin = Commerce::getInstance();

        $planUid = $this->request->getValidatedBodyParam('planUid');

        if (!$planUid || !$plan = $plugin->getPlans()->getPlanByUid($planUid)) {
            throw new InvalidConfigException('Subscription plan not found with that id.');
        }

        $error = null;
        $subscription = null;

        try {
            /** @var SubscriptionGateway $gateway */
            $gateway = $plan->getGateway();
            $parameters = $gateway->getSubscriptionFormModel();

            foreach ($parameters->attributes() as $attributeName) {
                $value = $this->request->getValidatedBodyParam($attributeName);

                if (is_string($value) && StringHelper::countSubstrings($value, ':') > 0) {
                    [$hashedPlanUid, $parameterValue] = explode(':', $value);

                    if ($plan->uid == $hashedPlanUid) {
                        $parameters->{$attributeName} = $parameterValue;
                    }
                }
            }

            try {
                $paymentFormData = $this->request->getBodyParam(PaymentForm::getPaymentFormParamName($gateway->handle)) ?? [];

                if (!empty($paymentFormData)) {
                    Craft::$app->getDeprecator()->log('SubscriptionController::create-newPaymentMethod', 'The subscription create action now requires that a customer’s default payment source is set up before subscribing, or pass the payment source information to the subscribe form.');

                    $createPaymentSource = function($gateway, $paymentFormData) use ($plugin) {
                        $paymentForm = $gateway->getPaymentFormModel();
                        $paymentForm->setAttributes($paymentFormData, false);

                        if ($paymentForm->validate()) {
                            $plugin->getPaymentSources()->createPaymentSource(Craft::$app->getUser()->getId(), $gateway, $paymentForm);
                        }
                    };

                    $exists = class_exists(PaymentIntents::class);
                    /** @phpstan-ignore-next-line */
                    if ($exists && $plan->getGateway() instanceof PaymentIntents) {
                        if (isset($paymentFormData['paymentMethodId'])) {
                            $createPaymentSource($gateway, $paymentFormData);
                        }
                    } else {
                        $createPaymentSource($gateway, $paymentFormData);
                    }
                }

                $fieldsLocation = $this->request->getParam('fieldsLocation', 'fields');
                $fieldValues = $this->request->getBodyParam($fieldsLocation, []);

                $subscription = $plugin->getSubscriptions()->createSubscription($user, $plan, $parameters, $fieldValues);
            } catch (\Exception $exception) {
                Craft::$app->getErrorHandler()->logException($exception);

                throw new SubscriptionException(Craft::t('commerce', 'Unable to start the subscription. ' . $exception->getMessage()));
            }
        } catch (SubscriptionException $exception) {
            $error = $exception->getMessage();
        }

        if ($returnUrl) {
            $returnUrl = $this->getView()->renderObjectTemplate($returnUrl, $subscription);
            $subscriptionRecord = SubscriptionRecord::findOne($subscription->id);
            $subscriptionRecord->returnUrl = $returnUrl;
            $subscriptionRecord->save();
            $subscription->returnUrl = $returnUrl;
        }

        if (!$error && $subscription && $subscription->isSuspended && !$subscription->hasStarted) {
            $url = Plugin::getInstance()->getSettings()->updateBillingDetailsUrl;

            if (empty($url)) {
                $error = Craft::t('commerce', 'Unable to start the subscription. Please check your payment details.');
            } else {
                return $this->redirect(UrlHelper::url(App::parseEnv($url), ['subscription' => $subscription->uid]));
            }
        }

        if ($error) {
            return $this->asFailure($error);
        }

        return $this->asSuccess(
            Craft::t('commerce', 'Subscription started.'),
            data: [
                'subscription' => $subscription ?? null,
            ],
            redirect: $returnUrl
        );
    }

    /**
     * @throws BadRequestHttpException
     * @throws Throwable
     */
    public function actionReactivate(): ?Response
    {
        $this->requireLogin();
        $this->requirePostRequest();

        $plugin = Commerce::getInstance();

        $error = false;
        $subscription = null;

        try {
            $subscriptionUid = $this->request->getValidatedBodyParam('subscriptionUid');
            /** @var Subscription|null $subscription */
            $subscription = Subscription::find()->status(null)->uid($subscriptionUid)->one();

            $validData = $subscriptionUid && $subscription;
            $validAction = $subscription->canReactivate();
            $canModifySubscription = Craft::$app->getElements()->canSave($subscription);

            if (($validData && $validAction && $canModifySubscription) || $this->_canUpdateSubscription($subscription)) {
                if (!$plugin->getSubscriptions()->reactivateSubscription($subscription)) {
                    $error = Craft::t('commerce', 'Unable to reactivate subscription at this time.');
                }
            } else {
                $error = Craft::t('commerce', 'Unable to reactivate subscription at this time.');
            }
        } catch (Exception $exception) {
            $error = $exception->getMessage();
        }

        if ($error) {
            return $this->asFailure($error);
        }

        return $this->asSuccess(
            Craft::t('commerce', 'Subscription reactivated.'),
            data: [
                'subscription' => $subscription,
            ]
        );
    }

    /**
     * @throws InvalidConfigException
     * @throws BadRequestHttpException
     */
    public function actionSwitch(): ?Response
    {
        $this->requireLogin();
        $this->requirePostRequest();

        $plugin = Commerce::getInstance();

        $subscriptionUid = $this->request->getValidatedBodyParam('subscriptionUid');
        $planUid = $this->request->getValidatedBodyParam('planUid');

        $error = false;

        try {
            /** @var Subscription|null $subscription */
            $subscription = Subscription::find()->status(null)->uid($subscriptionUid)->one();
            $plan = Commerce::getInstance()->getPlans()->getPlanByUid($planUid);

            $validData = $planUid && $plan && $subscriptionUid && $subscription;
            $validAction = $plan->canSwitchFrom($subscription->getPlan());
            $canModifySubscription = Craft::$app->getElements()->canSave($subscription);

            if (($validData && $validAction && $canModifySubscription) || $this->_canUpdateSubscription($subscription)) {
                /** @var SubscriptionGateway $gateway */
                $gateway = $subscription->getGateway();
                $parameters = $gateway->getSwitchPlansFormModel();

                foreach ($parameters->attributes() as $attributeName) {
                    $value = $this->request->getValidatedBodyParam($attributeName);

                    if (is_string($value) && StringHelper::countSubstrings($value, ':') > 0) {
                        [$hashedPlanUid, $parameterValue] = explode(':', $value);

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
            return $this->asFailure($exception->getMessage());
        }

        if ($error) {
            return $this->asFailure($error);
        }

        return $this->asSuccess(
            Craft::t('commerce', 'Subscription switched.'),
            data: [
                'subscription' => $subscription,
            ]
        );
    }

    /**
     * @throws InvalidConfigException
     * @throws BadRequestHttpException
     */
    public function actionCancel(): ?Response
    {
        $this->requireLogin();
        $this->requirePostRequest();

        $plugin = Commerce::getInstance();

        $error = false;
        $subscription = null;

        try {
            $subscriptionUid = $this->request->getValidatedBodyParam('subscriptionUid');
            /** @var Subscription|null $subscription */
            $subscription = Subscription::find()->status(null)->uid($subscriptionUid)->one();
            $validData = $subscriptionUid && $subscription;

            $canModifySubscription = Craft::$app->getElements()->canSave($subscription);

            if (($validData === true && $canModifySubscription === true) || $this->_canUpdateSubscription($subscription)) {
                /** @var SubscriptionGateway $gateway */
                $gateway = $subscription->getGateway();
                $parameters = $gateway->getCancelSubscriptionFormModel();

                foreach ($parameters->attributes() as $attributeName) {
                    $value = $this->request->getValidatedBodyParam($attributeName);

                    if (is_string($value) && StringHelper::countSubstrings($value, ':') > 0) {
                        [$hashedSubscriptionUid, $parameterValue] = explode(':', $value);

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
            return $this->asFailure($error);
        }

        return $this->asSuccess(
            Craft::t('commerce', 'Subscription cancelled.'),
            data: [
                'subscription' => $subscription,
            ]
        );
    }

    public function actionCompleteSubscription(): ?Response
    {
        $subscriptionUid = $this->request->getRequiredQueryParam('subscription');
        $subscription = Subscription::find()->status(null)->uid($subscriptionUid)->one();

        if (!$subscription) {
            throw new NotFoundHttpException('Subscription not found');
        }

        $gateway = $subscription->getGateway();
        $transactionHash = $gateway->getTransactionHashFromWebhook();
        $useMutex = (bool)$transactionHash;
        $transactionLockName = 'commerceTransaction:' . $transactionHash;
        $mutex = Craft::$app->getMutex();

        if ($useMutex && !$mutex->acquire($transactionLockName, 15)) {
            throw new Exception('Unable to acquire a lock for transaction: ' . $transactionHash);
        }

        $gateway->refreshPaymentHistory($subscription);

        if ($useMutex) {
            $mutex->release($transactionLockName);
        }

        return $this->asSuccess(redirect: $subscription->returnUrl);
    }


    /**
     * @param Subscription $subscription
     * @throws ForbiddenHttpException
     */
    protected function enforceManageSubscriptionPermissions(Subscription $subscription)
    {
        if (!Craft::$app->getElements()->canView($subscription)) {
            throw new ForbiddenHttpException('User not authorized to view this subscription.');
        }
    }

    /**
     * @param Subscription $subscription
     * @return bool
     * @throws Throwable
     */
    private function _canUpdateSubscription(Subscription $subscription): bool
    {
        $currentUser = Craft::$app->getUser()->getIdentity();

        $isOwner = $subscription->userId === $currentUser->id;
        $isFrontEnd = !Craft::$app->getRequest()->getIsCpRequest();

        return ($isOwner === true && $isFrontEnd === true);
    }
}

