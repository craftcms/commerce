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
use craft\commerce\helpers\DebugPanel;
use craft\commerce\Plugin;
use craft\elements\Entry;
use craft\errors\DeprecationException;
use craft\helpers\Cp;
use craft\helpers\Html;
use craft\helpers\Json;
use craft\i18n\Locale;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\HttpException;
use yii\web\Response;
use function is_array;

/**
 * Class Plans Controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class PlansController extends BaseCpController
{
    /**
     * @return Response
     * @throws InvalidConfigException
     */
    public function actionPlanIndex(): Response
    {
        $plans = Plugin::getInstance()->getPlans()->getAllPlans();

        return $this->asCpScreen()
            ->title(Craft::t('commerce', 'Subscription plans'))
            ->crumbs([
                ['label' => Craft::t('commerce', 'Commerce'), 'url' => 'commerce'],
            ])
            ->redirectUrl('commerce/subscription-plans')
            ->selectedSubnavItem('subscription-plans')
            ->additionalButtonsHtml(Html::a(
                Craft::t('commerce', 'New subscription plan'),
                'commerce/subscription-plans/new',
                ['class' => 'submit btn add icon']
            ))
            ->contentTemplate('commerce/subscriptions/plans/index.twig', compact('plans'));
    }

    /**
     * @param int|null $planId
     * @param Plan|null $plan
     * @return Response
     * @throws HttpException
     * @throws InvalidConfigException
     * @throws DeprecationException
     * @throws ForbiddenHttpException
     */
    public function actionEditPlan(int $planId = null, Plan $plan = null): Response
    {
        $this->requirePermission('commerce-manageSubscriptions');

        $variables = compact('planId', 'plan');

        $variables['brandNewPlan'] = false;

        if (empty($variables['plan'])) {
            if (!empty($variables['planId'])) {
                $planId = $variables['planId'];
                try {
                    $variables['plan'] = Plugin::getInstance()->getPlans()->getPlanById($planId);
                } catch (InvalidConfigException) {
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
            DebugPanel::prependOrAppendModelTab(model: $variables['plan'], prepend: true);
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

        $sidebar = Html::beginTag('div', ['class' => 'meta']) .
                Cp::lightswitchFieldHtml([
                    'label' => Craft::t('commerce', 'Enabled for customers to select?'),
                    'name' => 'enabled',
                    'on' => $variables['plan']?->enabled ?? false,
                    'errors' => $variables['plan']?->getErrors('enabled') ?? null,
                ]) .
            Html::endTag('div');

        if ($variables['plan']?->id) {
            $sidebar .= Html::beginTag('div', ['class' => 'meta readonly']) .
                Html::beginTag('div', ['class' => 'data']) .
                    Html::tag('h5', Craft::t('app', 'Created at'), ['class' => 'heading']) .
                    Html::tag('div', Craft::$app->getFormatter()->asDate($variables['plan']->dateCreated, Locale::LENGTH_SHORT), ['class' => 'value', 'id' => 'date-created-value']) .
                Html::endTag('div') .
                Html::beginTag('div', ['class' => 'data']) .
                    Html::tag('h5', Craft::t('app', 'Updated at'), ['class' => 'heading']) .
                    Html::tag('div', Craft::$app->getFormatter()->asDate($variables['plan']->dateUpdated, Locale::LENGTH_SHORT), ['class' => 'value', 'id' => 'date-updated-value']) .
                Html::endTag('div');
        }

        return $this->asCpScreen()
            ->title($variables['title'])
            ->selectedSubnavItem('subscription-plans')
            ->addCrumb(Craft::t('commerce', 'Commerce'), 'commerce')
            ->addCrumb(Craft::t('commerce', 'Plans'), 'commerce/subscription-plans')
            ->contentTemplate('commerce/subscriptions/plans/_edit.twig', $variables)
            ->action('commerce/plans/save-plan')
            ->redirectUrl('commerce/subscription-plans')
            ->metaSidebarHtml($sidebar);
    }

    /**
     * @throws Exception
     * @throws HttpException if request does not match requirements
     * @throws InvalidConfigException if gateway does not support subscriptions
     * @throws BadRequestHttpException
     */
    public function actionSavePlan(): void
    {
        $this->requirePermission('commerce-manageSubscriptions');

        $this->requirePostRequest();

        $gatewayId = $this->request->getBodyParam('gatewayId');
        $reference = $this->request->getBodyParam("gateway.$gatewayId.reference", '');

        $gateway = Plugin::getInstance()->getGateways()->getGatewayById($gatewayId);

        if ($gateway instanceof SubscriptionGateway) {
            $planData = $gateway->getSubscriptionPlanByReference($reference);
        } else {
            throw new InvalidConfigException('This gateway does not support subscription plans.');
        }

        $planInformationIds = $this->request->getBodyParam('planInformation');

        $planService = Plugin::getInstance()->getPlans();
        $planId = $this->request->getParam('planId');

        $plan = null;
        if ($planId) {
            $plan = $planService->getPlanById($planId);
        }

        if ($plan === null) {
            $plan = $gateway->getPlanModel();
        }

        // Shared attributes
        $plan->id = $planId;
        $plan->gatewayId = $gatewayId;
        $plan->name = $this->request->getParam('name');
        $plan->handle = $this->request->getParam('handle');
        $plan->planInformationId = is_array($planInformationIds) ? reset($planInformationIds) : null;
        $plan->reference = $reference;
        $plan->enabled = (bool)$this->request->getParam('enabled');
        $plan->planData = $planData;
        $plan->isArchived = false;

        // Save $plan
        if ($planService->savePlan($plan)) {
            $this->setSuccessFlash(Craft::t('commerce', 'Subscription plan saved.'));
            $this->redirectToPostedUrl($plan);
        } else {
            $this->setFailFlash(Craft::t('commerce', 'Couldn’t save subscription plan.'));
        }

        // Send the productType back to the template
        Craft::$app->getUrlManager()->setRouteParams([
            'plan' => $plan,
        ]);
    }

    /**
     * @throws HttpException if request does not match requirements
     */
    public function actionArchivePlan(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $this->requirePermission('commerce-manageSubscriptions');

        $planId = $this->request->getRequiredBodyParam('id');

        try {
            Plugin::getInstance()->getPlans()->archivePlanById($planId);
        } catch (Exception $exception) {
            return $this->asFailure($exception->getMessage());
        }

        return $this->asSuccess();
    }

    /**
     * @throws HttpException
     */
    public function actionReorder(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();
        $ids = Json::decode($this->request->getRequiredBodyParam('ids'));

        $success = Plugin::getInstance()->getPlans()->reorderPlans($ids);

        return $success ?
            $this->asSuccess() :
            $this->asFailure(Craft::t('commerce', 'Couldn’t reorder plans.'));
    }
}
