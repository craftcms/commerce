<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers\Settings;

use craft\commerce\base\SubscriptionGateway;
use craft\commerce\Plugin;
use CraftCms\Cms\Entry\Elements\Entry;
use CraftCms\Cms\Http\RespondsWithFlash;
use CraftCms\Cms\Http\Responses\CpScreenResponse;
use CraftCms\Cms\Translation\Locale;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

use function CraftCms\Cms\t;

readonly class PlansController
{
    use RespondsWithFlash;

    public function planIndex(): CpScreenResponse
    {
        $plans = Plugin::getInstance()->getPlans()->getAllPlans();

        return new CpScreenResponse()
            ->title(t('Subscription plans', category: 'commerce'))
            ->crumbs([
                ['label' => t('Commerce', category: 'commerce'), 'url' => 'commerce'],
            ])
            ->redirectUrl('commerce/subscription-plans')
            ->selectedSubnavItem('subscription-plans')
            ->additionalButtonsHtml(\craft\helpers\Html::a(
                t('New subscription plan', category: 'commerce'),
                'commerce/subscription-plans/new',
                ['class' => 'submit btn add icon']
            ))
            ->contentTemplate('commerce/subscriptions/plans/index.twig', ['plans' => $plans]);
    }

    public function editPlan(?int $planId = null): CpScreenResponse
    {
        if ($planId !== null) {
            $plan = Plugin::getInstance()->getPlans()->getPlanById($planId);
            abort_if(!$plan, 404, 'Plan not found');
            $brandNewPlan = false;
            $title = $plan->name;
        } else {
            $plan = null;
            $brandNewPlan = true;
            $title = t('Create a Subscription Plan', category: 'commerce');
        }

        $gateways = Plugin::getInstance()->getGateways()->getAllSubscriptionGateways();
        $gatewayOptions = [['value' => '', 'label' => '-']];

        foreach ($gateways as $gateway) {
            $gatewayOptions[] = ['value' => $gateway->id, 'label' => $gateway->name];
        }

        $sidebar = \craft\helpers\Html::beginTag('div', ['class' => 'meta']) .
            \craft\helpers\Cp::lightswitchFieldHtml([
                'label' => t('Enabled for customers to select?', category: 'commerce'),
                'name' => 'enabled',
                'on' => $plan?->enabled ?? false,
                'errors' => $plan?->getErrors('enabled') ?? null,
            ]) .
            \craft\helpers\Html::endTag('div');

        if ($plan?->id) {
            $dateCreated = $plan->dateCreated;
            $dateUpdated = $plan->dateUpdated;
            $sidebar .= \craft\helpers\Html::beginTag('div', ['class' => 'meta read-only']);
            if ($dateCreated) {
                $sidebar .=
                    \craft\helpers\Html::beginTag('div', ['class' => 'data', 'attribute' => 'dateCreated']) .
                    \craft\helpers\Html::tag('h5', t('Created at'), ['class' => 'heading']) .
                    \craft\helpers\Html::tag('div', \Craft::$app->getFormatter()->asDate($dateCreated, Locale::LENGTH_SHORT), ['class' => 'value', 'id' => 'date-created-value']) .
                    \craft\helpers\Html::endTag('div');
            }

            if ($dateUpdated) {
                $sidebar .=
                    \craft\helpers\Html::beginTag('div', ['class' => 'data', 'attribute' => 'dateUpdated']) .
                    \craft\helpers\Html::tag('h5', t('Updated at'), ['class' => 'heading']) .
                    \craft\helpers\Html::tag('div', \Craft::$app->getFormatter()->asDate($dateUpdated, Locale::LENGTH_SHORT), ['class' => 'value', 'id' => 'date-updated-value']) .
                    \craft\helpers\Html::endTag('div');
            }
            $sidebar .= \craft\helpers\Html::endTag('div');
        }

        return new CpScreenResponse()
            ->title($title)
            ->selectedSubnavItem('subscription-plans')
            ->addCrumb(t('Commerce', category: 'commerce'), 'commerce')
            ->addCrumb(t('Subscription Plans', category: 'commerce'), 'commerce/subscription-plans')
            ->contentTemplate('commerce/subscriptions/plans/_edit.twig', [
                'planId' => $planId,
                'plan' => $plan,
                'brandNewPlan' => $brandNewPlan,
                'title' => $title,
                'entryElementType' => Entry::class,
                'supportedGateways' => $gateways,
                'gatewayOptions' => $gatewayOptions,
            ])
            ->action('commerce/plans/save-plan')
            ->redirectUrl('commerce/subscription-plans')
            ->metaSidebarHtml($sidebar);
    }

    public function savePlan(Request $request): Response
    {
        $gatewayId = $request->input('gatewayId');
        $reference = $request->input("gateway.$gatewayId.reference", '');

        $gateway = Plugin::getInstance()->getGateways()->getGatewayById($gatewayId);

        abort_unless($gateway instanceof SubscriptionGateway, 400, 'This gateway does not support subscription plans.');

        $planData = $gateway->getSubscriptionPlanByReference($reference);

        $planInformationIds = $request->input('planInformation');

        $planService = Plugin::getInstance()->getPlans();
        $planId = $request->input('planId');

        $plan = null;
        if ($planId) {
            $plan = $planService->getPlanById($planId);
        }

        $plan ??= $gateway->getPlanModel();

        // Shared attributes
        $plan->id = $planId;
        $plan->gatewayId = $gatewayId;
        $plan->name = $request->input('name');
        $plan->handle = $request->input('handle');
        $plan->planInformationId = is_array($planInformationIds) ? reset($planInformationIds) : null;
        $plan->reference = $reference;
        $plan->enabled = (bool)$request->input('enabled');
        $plan->planData = $planData;
        $plan->isArchived = false;

        if ($planService->savePlan($plan)) {
            return $this->asModelSuccess($plan, t('Subscription plan saved.', category: 'commerce'), 'plan');
        }

        return $this->asModelFailure($plan, t('Couldn\'t save subscription plan.', category: 'commerce'), 'plan');
    }

    public function archivePlan(Request $request): Response
    {
        abort_unless($request->expectsJson(), 400);

        $planId = $request->input('id');
        abort_if(!$planId, 400, 'Missing plan id');

        try {
            Plugin::getInstance()->getPlans()->archivePlanById($planId);
        } catch (\Exception $exception) {
            return $this->asFailure($exception->getMessage());
        }

        return $this->asSuccess();
    }

    public function reorder(Request $request): Response
    {
        abort_unless($request->expectsJson(), 400);
        abort_unless($request->input('ids'), 400, 'Missing ids');

        $ids = \craft\helpers\Json::decode($request->input('ids'));

        $success = Plugin::getInstance()->getPlans()->reorderPlans($ids);

        return $success
            ? $this->asSuccess()
            : $this->asFailure(t('Couldn\'t reorder plans.', category: 'commerce'));
    }
}
