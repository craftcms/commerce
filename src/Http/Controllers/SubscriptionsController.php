<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers;

use craft\commerce\base\SubscriptionGateway;
use craft\commerce\elements\Subscription;
use craft\commerce\errors\SubscriptionException;
use craft\commerce\helpers\PaymentForm;
use craft\commerce\Plugin;
use craft\commerce\records\Subscription as SubscriptionRecord;
use craft\commerce\stripe\gateways\PaymentIntents;
use craft\commerce\web\assets\commercecp\CommerceCpAsset;
use CraftCms\Cms\Element\Validation\ElementRules;
use CraftCms\Cms\FieldLayout\FieldLayoutCompiler;
use CraftCms\Cms\Form\Enums\ControlMode;
use CraftCms\Cms\Form\FormContext;
use CraftCms\Cms\Form\FormHtmlRenderer;
use CraftCms\Cms\Http\RespondsWithFlash;
use CraftCms\Cms\Http\Responses\CpModalResponse;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\View\TemplateMode;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

use function CraftCms\Cms\currentUser;
use function CraftCms\Cms\currentUserElement;
use function CraftCms\Cms\pageTemplate;
use function CraftCms\Cms\renderSandboxedObjectTemplate;
use function CraftCms\Cms\t;

readonly class SubscriptionsController
{
    use RespondsWithFlash;

    public function index(): string
    {
        abort_unless(currentUser()?->can('commerce-manageSubscriptions'), 403);

        return pageTemplate('commerce/subscriptions/_index', [], TemplateMode::Cp);
    }

    public function edit(?int $subscriptionId = null): string
    {
        \Craft::$app->getView()->registerAssetBundle(CommerceCpAsset::class);

        abort_if(!$subscriptionId, 404, 'Subscription not found');

        /** @var Subscription|null $subscription */
        $subscription = Subscription::find()->status(null)->id($subscriptionId)->one();
        abort_if(!$subscription, 404, 'Subscription not found');

        $this->enforceManageSubscriptionPermissions($subscription);

        $fieldLayout = $subscription->getFieldLayout();
        $payload = app(FieldLayoutCompiler::class)->compile(
            $fieldLayout,
            $subscription,
            new FormContext(
                errors: $subscription->errors()->getMessages(),
                mode: ControlMode::Editable,
                refreshable: true,
            ),
        );
        $renderer = app(FormHtmlRenderer::class);

        $tabMenu = $renderer->tabMenu($payload);
        $tabMenu['tab--subscriptionManageTab'] = [
            'label' => t('Manage', category: 'commerce'),
            'url' => '#tab--subscriptionManageTab',
            'class' => null,
        ];

        return pageTemplate('commerce/subscriptions/_edit', [
            'tabs' => $tabMenu,
            'fieldsHtml' => $renderer->render($payload),
            'continueEditingUrl' => $subscription->getCpEditUrl(),
            'subscriptionId' => $subscriptionId,
            'subscription' => $subscription,
            'fieldLayout' => $fieldLayout,
        ], TemplateMode::Cp);
    }

    public function save(Request $request): ?Response
    {
        $subscriptionId = $request->input('subscriptionId');
        abort_if(!$subscriptionId, 400, 'Missing subscriptionId');

        /** @var Subscription|null $subscription */
        $subscription = Subscription::find()->status(null)->id($subscriptionId)->one();
        abort_if(!$subscription, 404, 'Subscription not found');

        if (!$this->canUpdateSubscription($subscription)) {
            $this->enforceManageSubscriptionPermissions($subscription);
        }

        $subscription->setFieldValuesFromRequest('fields');

        $subscription->ruleset->useScenario(ElementRules::SCENARIO_LIVE);

        if (!Elements::saveElement($subscription)) {
            return $this->asFailure(t('Couldn\'t save subscription.', category: 'commerce'));
        }

        return $this->redirectToPostedUrl($subscription);
    }

    public function refreshPayments(Request $request): Response
    {
        abort_unless(currentUser()?->can('commerce-manageSubscriptions'), 403);

        $subscriptionId = $request->input('subscriptionId');
        abort_if(!$subscriptionId, 400, 'Missing subscriptionId');

        $subscription = Subscription::find()->status(null)->id($subscriptionId)->one();
        abort_if(!$subscription, 404, 'Subscription not found');

        $gateway = $subscription->getGateway();
        $gateway->refreshPaymentHistory($subscription);

        return $this->redirectToPostedUrl($subscription);
    }

    public function subscribe(Request $request): ?Response
    {
        $user = currentUserElement();
        abort_unless($user, 401);

        $returnUrl = $request->input('redirect');

        $plugin = Plugin::getInstance();

        $planUid = $request->input('planUid');
        abort_unless($planUid && $plan = $plugin->getPlans()->getPlanByUid($planUid), 400, 'Subscription plan not found with that id.');

        $error = null;
        $subscription = null;

        try {
            /** @var SubscriptionGateway $gateway */
            $gateway = $plan->getGateway();
            $parameters = $gateway->getSubscriptionFormModel();

            foreach ($parameters->attributes() as $attributeName) {
                $value = $request->input($attributeName);

                if (is_string($value) && \craft\helpers\StringHelper::countSubstrings($value, ':') > 0) {
                    [$hashedPlanUid, $parameterValue] = explode(':', $value);

                    if ($plan->uid == $hashedPlanUid) {
                        $parameters->{$attributeName} = $parameterValue;
                    }
                }
            }

            try {
                $paymentFormData = $request->input(PaymentForm::getPaymentFormParamName($gateway->handle)) ?? [];

                if (!empty($paymentFormData)) {
                    \Craft::$app->getDeprecator()->log('SubscriptionController::create-newPaymentMethod', 'The subscription create action now requires that a customer\'s default payment source is set up before subscribing, or pass the payment source information to the subscribe form.');

                    $createPaymentSource = function($gateway, $paymentFormData) use ($plugin, $user) {
                        $paymentForm = $gateway->getPaymentFormModel();
                        $paymentForm->setAttributes($paymentFormData, false);

                        if ($paymentForm->validate()) {
                            $plugin->getPaymentSources()->createPaymentSource($user->id, $gateway, $paymentForm);
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

                $fieldsLocation = $request->input('fieldsLocation', 'fields');
                $fieldValues = $request->input($fieldsLocation, []);

                $subscription = $plugin->getSubscriptions()->createSubscription($user, $plan, $parameters, $fieldValues);
            } catch (\Exception $exception) {
                \Craft::$app->getErrorHandler()->logException($exception);

                throw new SubscriptionException(t('Unable to start the subscription. ', category: 'commerce') . $exception->getMessage());
            }
        } catch (SubscriptionException $exception) {
            $error = $exception->getMessage();
        }

        if ($subscription && $returnUrl) {
            $returnUrl = renderSandboxedObjectTemplate($returnUrl, $subscription);
            $subscriptionRecord = SubscriptionRecord::findOne($subscription->id);
            $subscriptionRecord->returnUrl = $returnUrl;
            $subscriptionRecord->save();
            $subscription->returnUrl = $returnUrl;
        }

        if (!$error && $subscription && $subscription->isSuspended && !$subscription->hasStarted) {
            $url = Plugin::getInstance()->getSettings()->updateBillingDetailsUrl;

            if (empty($url)) {
                $error = t('Unable to start the subscription. Please check your payment details.', category: 'commerce');
            } else {
                return redirect(\craft\helpers\UrlHelper::url(\CraftCms\Cms\Support\Env::parse($url), ['subscription' => $subscription->uid]));
            }
        }

        if ($error) {
            return $this->asFailure($error);
        }

        return $this->asSuccess(
            t('Subscription started.', category: 'commerce'),
            data: [
                'subscription' => $subscription ?? null,
            ],
            redirect: $returnUrl
        );
    }

    public function reactivate(Request $request): ?Response
    {
        $user = currentUserElement();
        abort_unless($user, 401);

        $plugin = Plugin::getInstance();

        $error = false;
        $subscription = null;

        try {
            $subscriptionUid = $request->input('subscriptionUid');
            /** @var Subscription|null $subscription */
            $subscription = Subscription::find()->status(null)->uid($subscriptionUid)->one();

            $validData = $subscriptionUid && $subscription;
            $validAction = $subscription->canReactivate();
            $canModifySubscription = $subscription->canSave($user);

            if (($validData && $validAction && $canModifySubscription) || $this->canUpdateSubscription($subscription)) {
                if (!$plugin->getSubscriptions()->reactivateSubscription($subscription)) {
                    $error = t('Unable to reactivate subscription at this time.', category: 'commerce');
                }
            } else {
                $error = t('Unable to reactivate subscription at this time.', category: 'commerce');
            }
        } catch (\Exception $exception) {
            $error = $exception->getMessage();
        }

        if ($error) {
            return $this->asFailure($error);
        }

        return $this->asSuccess(
            t('Subscription reactivated.', category: 'commerce'),
            data: [
                'subscription' => $subscription,
            ]
        );
    }

    public function switchPlan(Request $request): ?Response
    {
        $user = currentUserElement();
        abort_unless($user, 401);

        $plugin = Plugin::getInstance();

        $subscriptionUid = $request->input('subscriptionUid');
        $planUid = $request->input('planUid');

        $error = false;
        $subscription = null;

        try {
            /** @var Subscription|null $subscription */
            $subscription = Subscription::find()->status(null)->uid($subscriptionUid)->one();
            $plan = $plugin->getPlans()->getPlanByUid($planUid);

            $validData = $planUid && $plan && $subscriptionUid && $subscription;
            $validAction = $plan->canSwitchFrom($subscription->getPlan());
            $canModifySubscription = $subscription->canSave($user);

            if (($validData && $validAction && $canModifySubscription) || $this->canUpdateSubscription($subscription)) {
                /** @var SubscriptionGateway $gateway */
                $gateway = $subscription->getGateway();
                $parameters = $gateway->getSwitchPlansFormModel();

                foreach ($parameters->attributes() as $attributeName) {
                    $value = $request->input($attributeName);

                    if (is_string($value) && \craft\helpers\StringHelper::countSubstrings($value, ':') > 0) {
                        [$hashedPlanUid, $parameterValue] = explode(':', $value);

                        if ($hashedPlanUid == $planUid) {
                            $parameters->{$attributeName} = $parameterValue;
                        }
                    }
                }

                if (!$plugin->getSubscriptions()->switchSubscriptionPlan($subscription, $plan, $parameters)) {
                    $error = t('Unable to modify subscription at this time.', category: 'commerce');
                }
            } else {
                $error = t('Unable to modify subscription at this time.', category: 'commerce');
            }
        } catch (SubscriptionException $exception) {
            return $this->asFailure($exception->getMessage());
        }

        if ($error) {
            return $this->asFailure($error);
        }

        return $this->asSuccess(
            t('Subscription switched.', category: 'commerce'),
            data: [
                'subscription' => $subscription,
            ]
        );
    }

    public function cancel(Request $request): ?Response
    {
        $user = currentUserElement();
        abort_unless($user, 401);

        $plugin = Plugin::getInstance();

        $error = false;
        $subscription = null;

        try {
            $subscriptionUid = $request->input('subscriptionUid');
            /** @var Subscription|null $subscription */
            $subscription = Subscription::find()->status(null)->uid($subscriptionUid)->one();
            $validData = $subscriptionUid && $subscription;

            $canModifySubscription = $subscription?->canSave($user);

            if (($validData === true && $canModifySubscription === true) || $this->canUpdateSubscription($subscription)) {
                /** @var SubscriptionGateway $gateway */
                $gateway = $subscription->getGateway();
                $parameters = $gateway->getCancelSubscriptionFormModel();

                foreach ($parameters->attributes() as $attributeName) {
                    $value = $request->input($attributeName);

                    if (is_string($value) && \craft\helpers\StringHelper::countSubstrings($value, ':') > 0) {
                        [$hashedSubscriptionUid, $parameterValue] = explode(':', $value);

                        if ($hashedSubscriptionUid == $subscriptionUid) {
                            $parameters->{$attributeName} = $parameterValue;
                        }
                    }
                }

                if (!$plugin->getSubscriptions()->cancelSubscription($subscription, $parameters)) {
                    $error = t('Unable to cancel subscription at this time.', category: 'commerce');
                }
            } else {
                $error = t('Unable to cancel subscription at this time.', category: 'commerce');
            }
        } catch (SubscriptionException $exception) {
            $error = $exception->getMessage();
        }

        if ($error) {
            return $this->asFailure($error);
        }

        return $this->asSuccess(
            t('Subscription cancelled.', category: 'commerce'),
            data: [
                'subscription' => $subscription,
            ]
        );
    }

    public function completeSubscription(Request $request): ?Response
    {
        $subscriptionUid = $request->input('subscription');
        abort_if(!$subscriptionUid, 400, 'Missing subscription');

        $subscription = Subscription::find()->status(null)->uid($subscriptionUid)->one();
        abort_if(!$subscription, 404, 'Subscription not found');

        $gateway = $subscription->getGateway();
        $transactionHash = $gateway->getTransactionHashFromWebhook();

        if ($transactionHash) {
            $lock = Cache::lock('commerceTransaction:' . $transactionHash, 15);

            try {
                $lock->block(15);
            } catch (LockTimeoutException) {
                abort(500, "Unable to acquire a lock for transaction: $transactionHash");
            }

            $gateway->refreshPaymentHistory($subscription);
            $lock->release();
        } else {
            $gateway->refreshPaymentHistory($subscription);
        }

        return $this->asSuccess(redirect: $subscription->returnUrl);
    }

    public function deleteSubscriptionsModal(Request $request): CpModalResponse
    {
        abort_unless($request->isCpRequest() && $request->expectsJson() && currentUser()?->can('deleteUsers'), 403);

        $subscriptionIds = $request->input('subscriptionIds');
        abort_if(!$subscriptionIds, 400, 'Missing subscriptionIds');

        $numSubscriptions = count($subscriptionIds);

        return $this->renderGatewayCancelModal($request, 'commerce/subscriptions/delete-subscriptions')
            ->submitButtonLabel(t('Delete {type}', [
                'type' => $numSubscriptions === 1 ? Subscription::lowerDisplayName() : Subscription::pluralLowerDisplayName(),
            ]));
    }

    public function deleteSubscriptions(Request $request): Response
    {
        abort_unless($request->isCpRequest() && $request->expectsJson() && currentUser()?->can('deleteUsers'), 403);

        $subscriptions = $this->subscriptionsFromRequest($request);
        $this->cancelSubscriptionsAtGateway($request, $subscriptions);

        foreach ($subscriptions as $subscription) {
            if (!Elements::deleteElement($subscription)) {
                \Craft::warning('Failed to delete subscription ' . $subscription->id . ' (' . $subscription->reference . ')', __METHOD__);
            }
        }

        $numSubscriptions = count($subscriptions);

        return $this->asSuccess(t('{type} deleted.', [
            'type' => $numSubscriptions === 1 ? Subscription::displayName() : Subscription::pluralDisplayName(),
        ]));
    }

    private function renderGatewayCancelModal(Request $request, string $actionUrl): CpModalResponse
    {
        $subscriptionIds = collect($request->input('subscriptionIds'))->filter()->map(fn($id) => (int)$id)->all();
        $gatewayId = (int)$request->input('gatewayId');

        $gateway = Plugin::getInstance()->getGateways()->getGatewayById($gatewayId);
        $subscription = Subscription::find()->id($subscriptionIds)->status(null)->one();

        $cancelFormHtml = '';
        if ($gateway instanceof SubscriptionGateway && $subscription) {
            $cancelFormHtml = $gateway->getCancelSubscriptionFormHtml($subscription);
        }

        return new CpModalResponse()
            ->action($actionUrl)
            ->contentHtml(function() use ($cancelFormHtml, $subscriptionIds, $gatewayId) {
                $view = \Craft::$app->getView();

                if ($cancelFormHtml) {
                    $view->registerJsWithVars(
                        fn($formId, $inputName) => <<<JS
                            (function() {
                                var form = document.getElementById(\$formId);
                                var radios = document.querySelectorAll(`input[name=\$inputName]`);
                                function toggle() {
                                    var checked = document.querySelector(`input[name=\$inputName]:checked`);
                                    form.style.display = (checked && checked.value === '1') ? '' : 'none';
                                }
                                radios.forEach(function(r) { r.addEventListener('change', toggle); });
                            })();
                            JS,
                        [
                            $view->namespaceInputId('cancel-form'),
                            $view->namespaceInputName('cancelWithGateway'),
                        ]
                    );
                }

                return \craft\helpers\Cp::fieldHtml('template:_includes/forms/radioGroup.twig', [
                    'label' => t('Gateway', category: 'commerce'),
                    'name' => 'cancelWithGateway',
                    'value' => '1',
                    'options' => [
                        ['label' => t('Cancel with gateway now', category: 'commerce'), 'value' => '1'],
                        ['label' => t('Leave gateway subscription as-is', category: 'commerce'), 'value' => '0'],
                    ],
                ]) .
                    ($cancelFormHtml ? \craft\helpers\Html::tag('div', $cancelFormHtml, ['id' => 'cancel-form']) : '') .
                    implode('', array_map(fn($id) => \craft\helpers\Html::hiddenInput('subscriptionIds[]', (string)$id), $subscriptionIds)) .
                    \craft\helpers\Html::hiddenInput('gatewayId', (string)$gatewayId);
            });
    }

    /** @return Subscription[] */
    private function subscriptionsFromRequest(Request $request): array
    {
        $subscriptionIds = collect($request->input('subscriptionIds'))->filter()->map(fn($id) => (int)$id)->all();

        return Subscription::find()
            ->id($subscriptionIds)
            ->status(null)
            ->all();
    }

    /** @param Subscription[] $subscriptions */
    private function cancelSubscriptionsAtGateway(Request $request, array $subscriptions): bool
    {
        $cancelWithGateway = (bool)$request->input('cancelWithGateway', false);
        if (!$cancelWithGateway) {
            return false;
        }

        $gatewayId = (int)$request->input('gatewayId');
        $gateway = Plugin::getInstance()->getGateways()->getGatewayById($gatewayId);
        if (!$gateway instanceof SubscriptionGateway) {
            return false;
        }

        $parameters = $gateway->getCancelSubscriptionFormModel();
        foreach ($parameters->attributes() as $attribute) {
            $value = $request->input($attribute);
            if ($value !== null) {
                $parameters->$attribute = $value;
            }
        }

        $subscriptionsService = Plugin::getInstance()->getSubscriptions();
        $cancelled = false;

        foreach ($subscriptions as $subscription) {
            if (!$subscription->isExpired) {
                try {
                    $subscriptionsService->cancelSubscription($subscription, $parameters);
                    $cancelled = true;
                } catch (Throwable $e) {
                    \Craft::warning('Failed to cancel subscription ' . $subscription->reference . ' with gateway: ' . $e->getMessage(), __METHOD__);
                }
            }
        }

        return $cancelled;
    }

    private function enforceManageSubscriptionPermissions(Subscription $subscription): void
    {
        abort_unless(($user = currentUserElement()) && $subscription->canView($user), 403, 'User not authorized to view this subscription.');
    }

    private function canUpdateSubscription(Subscription $subscription): bool
    {
        $currentUser = currentUserElement();

        $isOwner = $currentUser && $subscription->userId === $currentUser->id;
        $isFrontEnd = !request()->isCpRequest();

        return $isOwner && $isFrontEnd;
    }
}
