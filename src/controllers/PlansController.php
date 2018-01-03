<?php

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\base\SubscriptionGateway;
use craft\commerce\base\SubscriptionInterface;
use craft\commerce\base\Plan;
use craft\commerce\Plugin;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\web\HttpException;
use yii\web\Response;

/**
 * ClassPlans Controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since  2.0
 */
class PlansController extends BaseAdminController
{
    // Public Methods
    // =========================================================================

    /**
     * @return Response
     */
    public function actionPlanIndex(): Response
    {
        $plans = Plugin::getInstance()->getPlans()->getAllPlans();
        return $this->renderTemplate('commerce/settings/plans/index', ['plans' => $plans]);
    }

    /**
     * @param int|null  $planId
     * @param Plan|null $plan
     *
     * @return Response
     * @throws HttpException
     */
    public function actionEditPlan(int $planId = null, Plan $plan = null): Response
    {
        $variables = [
            'planId' => $planId,
            'plan' => $plan,
        ];

        $currentUser = Craft::$app->getUser()->getIdentity();

        if (!$currentUser->can('manageCommerce')) {
            throw new HttpException(403, Craft::t('commerce', 'This action is not allowed for the current user.'));
        }

        $variables['brandNewPlan'] = false;

        if (empty($variables['plan'])) {
            if (!empty($variables['planId'])) {
                $planId = $variables['planId'];
                try {
                    $variables['plan'] = Plugin::getInstance()->getPlans()->getPlanById($planId);
                } catch (InvalidConfigException $exception) {
                    throw new HttpException(404);
                }

                if (!$variables['plan']) {
                    throw new HttpException(404);
                }
            } else {
                $variables['brandNewPlan'] = true;
            }
        }

        if (!empty($variables['planId'])) {
            $variables['title'] = $variables['plan']->name;
        } else {
            $variables['title'] = Craft::t('commerce', 'Create a Subscription Plan');
        }

        $variables['supportedGateways'] = Plugin::getInstance()->getGateways()->getAllSubscriptionGateways();
        $variables['gatewayOptions'] = [''];

        foreach ($variables['supportedGateways'] as $gateway) {
            $variables['gatewayOptions'][] = ['value' => $gateway->id, 'label' => $gateway->name];
        }

        return $this->renderTemplate('commerce/settings/plans/_edit', $variables);
    }

    /**
     * @throws Exception
     * @throws HttpException if request does not match requirements
     * @throws InvalidConfigException if gateway does not support subscriptions
     * @throws \yii\web\BadRequestHttpException
     */
    public function actionSavePlan()
    {
        $currentUser = Craft::$app->getUser()->getIdentity();

        if (!$currentUser->can('manageCommerce')) {
            throw new HttpException(403, Craft::t('commerce', 'This action is not allowed for the current user.'));
        }

        $request = Craft::$app->getRequest();
        $this->requirePostRequest();

        $gatewayId = $request->getParam('gatewayId');
        $reference = $request->getParam('gateway.'.$gatewayId.'.reference', '');

        $gateway = Plugin::getInstance()->getGateways()->getGatewayById($gatewayId);

        if ($gateway instanceof SubscriptionGateway) {
            $response = $gateway->getSubscriptionPlanByReference($reference);
        } else {
            throw new InvalidConfigException('This gateway does not support subscription plans.');
        }

        $plan = $gateway->getPlanModel();

        // Shared attributes
        $plan->id = $request->getParam('planId');
        $plan->gatewayId = $gatewayId;
        $plan->name = $request->getParam('name');
        $plan->handle = $request->getParam('handle');
        $plan->reference = $reference;
        $plan->enabled = $request->getParam('enabled');
        $plan->response = $response;
        $plan->isArchived = false;

        // Save $plan
        if (Plugin::getInstance()->getPlans()->savePlan($plan)) {
            Craft::$app->getSession()->setNotice(Craft::t('commerce', 'Subscription plan saved.'));
            $this->redirectToPostedUrl($plan);
        } else {
            Craft::$app->getSession()->setError(Craft::t('commerce', 'Couldn’t save subscription plan.'));
        }

        // Send the productType back to the template
        Craft::$app->getUrlManager()->setRouteParams([
            'plan' => $plan
        ]);
    }

    /**
     * @return Response
     * @throws HttpException if request does not match requirements
     */
    public function actionArchivePlan(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $planId = Craft::$app->getRequest()->getRequiredParam('id');

        try {
            Plugin::getInstance()->getPlans()->archivePlanById($planId);
        } catch (Exception $exception) {
            return $this->asErrorJson($exception->getMessage());
        }

        return $this->asJson(['success' => true]);
    }
}
