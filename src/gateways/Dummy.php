<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\gateways;

use Craft;
use craft\commerce\base\CancelSubscriptionHtml;
use craft\commerce\base\Plan;
use craft\commerce\base\RequestResponseInterface;
use craft\commerce\base\SubscriptionGateway;
use craft\commerce\base\SubscriptionResponseInterface;
use craft\commerce\elements\Subscription;
use craft\commerce\models\payments\BasePaymentForm;
use craft\commerce\models\payments\CreditCardPaymentForm;
use craft\commerce\models\payments\DummyPaymentForm;
use craft\commerce\models\PaymentSource;
use craft\commerce\models\responses\Dummy as DummyRequestResponse;
use craft\commerce\models\responses\DummySubscriptionResponse;
use craft\commerce\models\subscriptions\CancelSubscriptionForm;
use craft\commerce\models\subscriptions\DummyPlan;
use craft\commerce\models\subscriptions\SubscriptionForm;
use craft\commerce\models\subscriptions\SwitchPlansForm;
use craft\commerce\models\Transaction;
use craft\elements\User;
use craft\helpers\StringHelper;
use craft\web\Response as WebResponse;
use craft\web\View;

/**
 * Dummy represents a dummy gateway.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class Dummy extends SubscriptionGateway
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function getPaymentFormHtml(array $params)
    {
        $defaults = [
            'paymentForm' => $this->getPaymentFormModel()
        ];

        $params = array_merge($defaults, $params);

        $view = Craft::$app->getView();
        $previousMode = $view->getTemplateMode();
        $view->setTemplateMode(View::TEMPLATE_MODE_CP);
        $html = Craft::$app->getView()->renderTemplate('commerce/_components/gateways/_creditCardFields', $params);
        $view->setTemplateMode($previousMode);

        return $html;
    }

    /**
     * @inheritdoc
     */
    public function getPaymentFormModel(): BasePaymentForm
    {
        return new DummyPaymentForm();
    }

    /**
     * @inheritdoc
     */
    public function authorize(Transaction $transaction, BasePaymentForm $form): RequestResponseInterface
    {
        return new DummyRequestResponse($form);
    }

    /**
     * @inheritdoc
     */
    public function capture(Transaction $transaction, string $reference): RequestResponseInterface
    {
        return new DummyRequestResponse();
    }

    /**
     * @inheritdoc
     */
    public function completeAuthorize(Transaction $transaction): RequestResponseInterface
    {
        return new DummyRequestResponse();
    }

    /**
     * @inheritdoc
     */
    public function completePurchase(Transaction $transaction): RequestResponseInterface
    {
        return new DummyRequestResponse();
    }

    /**
     * @inheritdoc
     */
    public function createPaymentSource(BasePaymentForm $sourceData, int $userId): PaymentSource
    {
        /** @var CreditCardPaymentForm $sourceData */

        $paymentSource = new PaymentSource();
        $paymentSource->gatewayId = $this->id;
        $paymentSource->token = StringHelper::randomString();
        $paymentSource->response = '';
        $paymentSource->description = 'Card ending with ' . StringHelper::last($sourceData->number, 4);

        return $paymentSource;
    }

    /**
     * @inheritdoc
     */
    public function deletePaymentSource($token): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function purchase(Transaction $transaction, BasePaymentForm $form): RequestResponseInterface
    {
        return new DummyRequestResponse($form);
    }

    /**
     * @inheritdoc
     */
    public function processWebHook(): WebResponse
    {
        return null;
    }

    /**
     * @inheritdoc
     */
    public function refund(Transaction $transaction): RequestResponseInterface
    {
        return new DummyRequestResponse();
    }

    /**
     * @inheritdoc
     */
    public function supportsAuthorize(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function supportsCapture(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function supportsCompleteAuthorize(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function supportsCompletePurchase(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function supportsPaymentSources(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function supportsPurchase(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function supportsRefund(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function supportsPartialRefund(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function supportsWebhooks(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function getCancelSubscriptionFormHtml(Subscription $subscription): string
    {
        return '';
    }

    /**
     * @inheritdoc
     */
    public function getCancelSubscriptionFormModel(): CancelSubscriptionForm
    {
        return new CancelSubscriptionForm();
    }

    /**
     * @inheritdoc
     */
    public function getPlanSettingsHtml(array $params = [])
    {
        return '<input type="hidden" name="reference" value="dummy.reference"/>';
    }

    /**
     * @inheritdoc
     */
    public function getPlanModel(): Plan
    {
        return new DummyPlan();
    }

    /**
     * @inheritdoc
     */
    public function getSubscriptionFormModel(): SubscriptionForm
    {
        return new SubscriptionForm();
    }

    /**
     * @inheritdoc
     */
    public function getSwitchPlansFormModel(): SwitchPlansForm
    {
        return new SwitchPlansForm();
    }

    /**
     * @inheritdoc
     */
    public function cancelSubscription(Subscription $subscription, CancelSubscriptionForm $parameters): SubscriptionResponseInterface
    {
        $response = new DummySubscriptionResponse();
        $response->setIsCanceled(true);
        return $response;
    }

    /**
     * @inheritdoc
     */
    public function getNextPaymentAmount(Subscription $subscription): string
    {
        return '-';
    }

    /**
     * @inheritdoc
     */
    public function getSubscriptionPayments(Subscription $subscription): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function getSubscriptionPlanByReference(string $reference): string
    {
        return 'dummy.plan';
    }

    /**
     * @inheritdoc
     */
    public function getSubscriptionPlans(): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function subscribe(User $user, Plan $plan, SubscriptionForm $parameters): SubscriptionResponseInterface
    {
        $subscription = new DummySubscriptionResponse();
        $subscription->setTrialDays((int)$parameters->trialDays);

        return $subscription;
    }

    /**
     * @inheritdoc
     */
    public function switchSubscriptionPlan(Subscription $subscription, Plan $plan, SwitchPlansForm $parameters): SubscriptionResponseInterface
    {
        return new DummySubscriptionResponse();
    }

    /**
     * @inheritdoc
     */
    public function supportsReactivation(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function supportsPlanSwitch(): bool
    {
        return true;
    }
}
