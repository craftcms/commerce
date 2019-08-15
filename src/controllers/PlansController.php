<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\base\Plan;
use craft\commerce\base\SubscriptionGateway;
use craft\commerce\Plugin;
use craft\elements\Entry;
use craft\helpers\Json;
use function is_array;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\web\BadRequestHttpException;
use yii\web\HttpException;
use yii\web\Response;

/**
 * Class Plans Controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
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
        return $this->renderTemplate('commerce/settings/subscriptions/plans', ['plans' => $plans]);
    }

    /**
     * @param int|null $planId
     * @param Plan|null $plan
     * @return Response
     * @throws HttpException
     */
    public function actionEditPlan(int $planId = null, Plan $plan = null): Response
    {
        $variables = compact('planId', 'plan');

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

        $variables['entryElementType'] = Entry::class;

        $gateways = Plugin::getInstance()->getGateways()->getAllSubscriptionGateways();
        $variables['supportedGateways'] = $gateways;
        $variables['gatewayOptions'] = [['value' => '', 'label' => '-']];

        foreach ($gateways as $gateway) {
            $variables['gatewayOptions'][] = ['value' => $gateway->id, 'label' => $gateway->name];
        }

        return $this->renderTemplate('commerce/settings/subscriptions/_editPlan', $variables);
    }

    /**
     * @throws Exception
     * @throws HttpException if request does not match requirements
     * @throws InvalidConfigException if gateway does not support subscriptions
     * @throws BadRequestHttpException
     */
    public function actionSavePlan()
    {
        $request = Craft::$app->getRequest();
        $this->requirePostRequest();

        $gatewayId = $request->getBodyParam('gatewayId');
        $reference = $request->getBodyParam("gateway.{$gatewayId}.reference", '');

        $gateway = Plugin::getInstance()->getGateways()->getGatewayById($gatewayId);

        if ($gateway instanceof SubscriptionGateway) {
            $planData = $gateway->getSubscriptionPlanByReference($reference);
        } else {
            throw new InvalidConfigException('This gateway does not support subscription plans.');
        }

        $planInformationIds = $request->getBodyParam('planInformation');

        $planService = Plugin::getInstance()->getPlans();
        $planId = $request->getParam('planId');

        $plan = null;
        if ($planId) {
            $plan = $planService->getPlanById($planId);
        }

        if (null === $plan) {
            $plan = $gateway->getPlanModel();
        }

        // Shared attributes
        $plan->id = $planId;
        $plan->gatewayId = $gatewayId;
        $plan->name = $request->getParam('name');
        $plan->handle = $request->getParam('handle');
        $plan->planInformationId = is_array($planInformationIds) ? reset($planInformationIds) : null;
        $plan->reference = $reference;
        $plan->enabled = (bool)$request->getParam('enabled');
        $plan->planData = $planData;
        $plan->isArchived = false;

        // Save $plan
        if ($planService->savePlan($plan)) {
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

        $planId = Craft::$app->getRequest()->getRequiredBodyParam('id');

        try {
            Plugin::getInstance()->getPlans()->archivePlanById($planId);
        } catch (Exception $exception) {
            return $this->asErrorJson($exception->getMessage());
        }

        return $this->asJson(['success' => true]);
    }

    /**
     * @throws HttpException
     */
    public function actionReorder(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();
        $ids = Json::decode(Craft::$app->getRequest()->getRequiredBodyParam('ids'));

        if ($success = Plugin::getInstance()->getPlans()->reorderPlans($ids)) {
            return $this->asJson(['success' => $success]);
        }

        return $this->asJson(['error' => Craft::t('commerce', 'Couldn’t reorder plans.')]);
    }

}
