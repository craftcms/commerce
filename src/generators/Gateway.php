<?php

namespace craft\commerce\generators;

use Craft;
use Nette\PhpGenerator\PhpNamespace;
use craft\commerce\base\Gateway as BaseGateway;
use craft\commerce\base\Plan;
use craft\commerce\base\RequestResponseInterface;
use craft\commerce\base\SubscriptionGateway;
use craft\commerce\base\SubscriptionResponseInterface;
use craft\commerce\elements\Order;
use craft\commerce\elements\Subscription;
use craft\commerce\errors\NotImplementedException;
use craft\commerce\models\payments\BasePaymentForm;
use craft\commerce\models\PaymentSource;
use craft\commerce\models\subscriptions\CancelSubscriptionForm;
use craft\commerce\models\subscriptions\SubscriptionForm;
use craft\commerce\models\subscriptions\SwitchPlansForm;
use craft\commerce\models\Transaction;
use craft\commerce\services\Gateways;
use craft\elements\User;
use craft\generator\BaseGenerator;
use craft\helpers\DateTimeHelper;
use craft\web\Response as WebResponse;
use craft\web\View;
use yii\helpers\Inflector;

/**
 * Creates a new payment gateway.
 */
class Gateway extends BaseGenerator
{
    private string $className;
    private string $displayName;
    private bool $supportsSubscriptions;
    private string $gatewayNamespace;
    private string $paymentFormNamespace;
    private string $responseNamespace;

    public function run(): bool
    {
        $this->className = $this->classNamePrompt('Gateway name:', [
            'required' => true,
        ]);

        $this->displayName = Inflector::camel2words($this->className);

        $this->supportsSubscriptions = $this->command->confirm('Will your gateway support subscriptions?', true);

        $this->gatewayNamespace = $this->namespacePrompt('Gateway namespace:', [
            'default' => "$this->baseNamespace\\gateways",
        ]);

        $this->paymentFormNamespace = $this->namespacePrompt('Payment form namespace:', [
            'default' => "$this->baseNamespace\\models\\payments",
        ]);

        $this->responseNamespace = $this->namespacePrompt('Request/response namespace:', [
            'default' => "$this->baseNamespace\\models\\responses",
        ]);

        $this->writeGatewayClass();
        $this->writePaymentFormClass();
        $this->writeResponseClass();
        $this->writePlanClass(); // Returns immediately if `supportsSubscriptions` is false!
        $this->writeTemplates();

        if (
            $this->isForModule() &&
            !$this->addRegistrationEventHandlerCode(
                Gateways::class,
                'EVENT_REGISTER_GATEWAY_TYPES',
                "$this->gatewayNamespace\\$this->className",
                $fallbackExample,
            )
        ) {
            $moduleFile = $this->moduleFile();
            $this->command->note(<<<MD
Add the following code to `$moduleFile` to register the gateway:

```
$fallbackExample
```
MD);
        }

        return true;
    }

    /**
     * Generates the primary Gateway class.
     */
    private function writeGatewayClass(): void
    {
        $namespace = (new PhpNamespace($this->gatewayNamespace))
            ->addUse(Craft::class)
            ->addUse(User::class)
            ->addUse(BaseGateway::class)
            ->addUse(NotImplementedException::class)
            ->addUse(Order::class)
            ->addUse(BasePaymentForm::class)
            ->addUse(RequestResponseInterface::class)
            ->addUse(PaymentSource::class)
            ->addUse(Transaction::class)
            ->addUse(WebResponse::class, 'WebResponse')
            ->addUse(View::class)
            ->addUse("$this->paymentFormNamespace\\{$this->className}PaymentForm")
            ->addUse("$this->responseNamespace\\{$this->className}Response");

        $methods = [
            'displayName' => sprintf('return %s;', $this->messagePhp($this->displayName)),
            'getPaymentFormHtml' => <<<PHP
// Return a string or render a template (and don’t forget to register any relevant asset bundles):
\$view = Craft::\$app->getView();

\$params = array_merge([
    'gateway' => \$this,
], \$params);

// If you are implementing this in a module, you will need to register a template root:
return \$view->renderTemplate('{$this->module->id}/forms/payment', \$params, View::TEMPLATE_MODE_CP);
PHP,
            'authorize' => <<<PHP
    // Use the form data to determine whether the payment can be made:
    return new {$this->className}Response();
PHP,
            'capture' => <<<PHP
// Finalize an authorized payment with the processor, then return an appropriate response object:
return new {$this->className}Response();
PHP,
            'completeAuthorize' => <<<PHP
// The customer has returned after authorizing a payment off-site. It may need to be manually captured, later!
return new {$this->className}Response();
PHP,
            'completePurchase' => <<<PHP
// The customer has returned after completing a payment.
return new {$this->className}Response();
PHP,
            'createPaymentSource' => <<<PHP
throw new NotImplementedException({$this->messagePhp('This gateway does not support saved payment sources.')});
PHP,
            'deletePaymentSource' => <<<PHP
throw new NotImplementedException({$this->messagePhp('This gateway does not support saved payment sources.')});
PHP,
            'getPaymentFormModel' => <<<PHP
return new {$this->className}PaymentForm();
PHP,
            'purchase' => <<<PHP
// Tell the processor to complete a payment, then populate a response object:
return new {$this->className}Response();
PHP,
            'refund' => <<<PHP
// Tell the processor to refund the payment, then populate a response object:
return new {$this->className}Response();
PHP,
            'supportsAuthorize' => <<<PHP
return true;
PHP,
            'supportsCapture' => <<<PHP
return true;
PHP,
            'supportsCompleteAuthorize' => <<<PHP
return true;
PHP,
            'supportsCompletePurchase' => <<<PHP
return true;
PHP,
            'supportsPaymentSources' => <<<PHP
return true;
PHP,
            'supportsPurchase' => <<<PHP
return true;
PHP,
            'supportsRefund' => <<<PHP
return true;
PHP,
            'supportsPartialRefund' => <<<PHP
return true;
PHP,
            'supportsPartialPayment' => <<<PHP
return true;
PHP,
            'availableForUseWithOrder' => <<<PHP
// Make available to all Orders:
return true;
PHP,
            'supportsWebhooks' => <<<PHP
// Commerce will handle receiving webhooks, but if you return `true` here, you must also implement `processWebhook()`!
return true;
PHP,
            'processWebhook' => <<<PHP
// Gather + act on data from the request, then return a response (like a controller would):
\$rawData = Craft::\$app->getRequest()->getRawBody();
\$data = craft\helpers\Json::decodeIfJson(\$rawData);

\$response = Craft::\$app->getResponse();
\$response->format = WebResponse::FORMAT_RAW;

// Responses are only seen by the machine that sent the webhook:
\$response->data = 'Thanks, robot!';

// If an exception is thrown while processing a webhook, Craft will
// automatically send an HTTP response code >= 400!

return \$response;
PHP,
            'getTransactionHashFromWebhook' => <<<PHP
\$rawData = Craft::\$app->getRequest()->getRawBody();
\$data = Json::decodeIfJson(\$rawData);
PHP,
        ];

        // Additional methods must be implemented to satisfy SubscriptionGatewayInterface:
        if ($this->supportsSubscriptions) {
            $methods = array_merge($methods, [
                'cancelSubscription' => <<<PHP
// Tell the processor to cancel the subscription, then populate a response object:
return new {$this->className}SubscriptionResponse();
PHP,
                'getNextPaymentAmount' => <<<PHP
// Return a human-readable description of the next expected payment or invoice:
return 'Some formatted price or description!';
PHP,
                'getSubscriptionPayments' => <<<PHP
// Retrieve payment history for a subscription:
return [];
PHP,
                'refreshPaymentHistory' => <<<PHP
// Re-load payments for the provided subscription from the provider.
PHP,
                'getSubscriptionPlanByReference' => <<<PHP
// Load a definition for the subscription’s plan from the provider.
return 'some-plan-identifier';
PHP,
                'getSubscriptionPlans' => <<<PHP
return [];
PHP,
                'getCancelSubscriptionFormModel' => <<<PHP
// You may need to extend this class if you are exposing additional settings to your subscribers, or need to parameterize the cancellation request in any way:
return new CancelSubscriptionForm();
PHP,
                'getCancelSubscriptionFormHtml' => <<<PHP
return Craft::\$app->getView()->renderTemplate('{$this->module->id}/forms/subscription-switch');
PHP,
                'getPlanSettingsHtml' => <<<PHP
// Return HTML that exposes options available when setting up a new plan with this gateway, in the control panel. You may fetch data from the provider here, or use AJAX once the page loads in the client.
\$params = array_merge([
    'gateway' => \$this,
    'plans' => [
        // The format of this array depends on how you render the form/input(s).
        ['label' => 'Plan A', 'value' => 'plan-a'],
        ['label' => 'Plan B', 'value' => 'plan-b'],
    ],
], \$params);

return Craft::\$app->getView()->renderTemplate('{$this->module->id}/settings/plan', \$params);
PHP,
                'getPlanModel' => <<<PHP
return new {$this->className}Plan();
PHP,
                'getSubscriptionFormModel' => <<<PHP
// You may need to extend this class if the gateway provides additional settings when configuring a subscription. The plan identifier will always be available when configuring a subscription request.
return new SubscriptionForm();
PHP,
                'getSwitchPlansFormModel' => <<<PHP
// If the provider needs extra information to perform a switch (like proration periods), you may need to create a subclass with relevant properties.
return new SwitchPlansForm();
PHP,
                'subscribe' => <<<PHP
// Create or configure the subscription record with the provider, then return a response:
\$response = new {$this->className}SubscriptionResponse();

// ...set some properties on the response object...

return \$response;
PHP,
                'reactivateSubscription' => <<<PHP
// Update the subscription with the provider, then populate a response object:
return new {$this->className}SubscriptionResponse();
PHP,
                'switchSubscriptionPlan' => <<<PHP
// Tell the provider to change plans, then populate a response object:
return new {$this->className}SubscriptionResponse();
PHP,
                'supportsReactivation' => <<<PHP
return true;
PHP,
                'supportsPlanSwitch' => <<<PHP
// If you return `true`, you must also implement `getSwitchPlansFormHtml()`!
return false;
PHP,
                'getHasBillingIssues' => <<<PHP
return false;
PHP,
                'getBillingIssueDescription' => <<<PHP
return 'Human-readable description of the issue explains why `getHasBillingIssues()` returns true. This should not be static text!';
PHP,
                'getBillingIssueResolveFormHtml' => <<<PHP
\$view = Craft::\$app->getView();

return \$view->renderTemplate('{$this->module->id}/forms/resolve-billing-issue', [
    'gateway' => \$this,
    'subscription' => \$subscription,
]);
PHP,
                'getSwitchPlansFormHtml' => <<<PHP
return Craft::\$app->getView()->renderTemplate('{$this->module->id}/forms/subscription-switch');
PHP,
            ]);

            // Additional `use` statements are required:
            $namespace
                ->addUse(SubscriptionGateway::class)
                ->addUse(Subscription::class)
                ->addUse(SubscriptionForm::class)
                ->addUse(SwitchPlansForm::class)
                ->addUse(CancelSubscriptionForm::class)
                ->addUse(SubscriptionResponseInterface::class)
                ->addUse(Plan::class)
                ->addUse("{$this->responseNamespace}\\{$this->className}SubscriptionResponse")
                ->addUse("{$this->baseNamespace}\\models\\Plan", "{$this->className}Plan");
        }

        $class = $this->createClass($this->className, $this->supportsSubscriptions ? SubscriptionGateway::class : BaseGateway::class, [
            self::CLASS_METHODS => $methods,
        ]);
        $namespace->add($class);

        $class->setComment(<<<MD
$this->displayName gateway
MD);
        $this->writePhpClass($namespace);
        $this->command->success("**Gateway created!**");
    }

    /**
     * Generates a PaymentForm class.
     */
    private function writePaymentFormClass(): void
    {
        $namespace = (new PhpNamespace($this->paymentFormNamespace))
        ->addUse(Craft::class)
        ->addUse(BasePaymentForm::class);

        $class = $this->createClass("{$this->className}PaymentForm", BasePaymentForm::class, [
            self::CLASS_METHODS => [
                'populateFromPaymentSource' => <<<PHP
// Copy properties from the Commerce \$paymentSource model to populate it with a saved payment method.
// \$this->myGatewayPaymentMethodId = \$paymentSource->token;
// \$customer = Commerce::getInstance()->getCustomers()->getCustomer(\$paymentSource->gatewayId, \$paymentSource->getCustomer());
// \$this->myGatewayCustomerId = \$customer->reference;
PHP,
                'defineRules' => <<<PHP
// Validate critical attributes before sending to the gateway to prevent common errors and omissions:
return [];
PHP,
            ],
        ]);

        $namespace->add($class);

        $class->setComment(<<<COMMENT
$this->displayName payment form

You may instead extend {@see craft\commerce\models\payments\CreditCardPaymentForm}, if your gateway uses a standard tokenized payment flow!
COMMENT);

        $this->writePhpClass($namespace);
        $this->command->success("**Payment form created!**");
    }

    private function writeResponseClass(): void
    {
        $responseNamespace = (new PhpNamespace($this->responseNamespace))
            ->addUse(Craft::class)
            ->addUse(RequestResponseInterface::class);

        $paymentResponseClass = $this->createClass("{$this->className}Response", null, [
            self::CLASS_IMPLEMENTS => [
                RequestResponseInterface::class,
            ],
            self::CLASS_METHODS => [
                'getData' => <<<PHP
// Return any data that should be stored along with the transaction (or subscription):
return [];
PHP,
                'isSuccessful' => <<<PHP
// Does the response indicate the payment was successful?
return true;
PHP,
                'isProcessing' => <<<PHP
// Does the response indicate the payment is still processing?
return true;
PHP,
                'isRedirect' => <<<PHP
// Does the response indicate that the customer needs to be directed offsite to complete a payment?
return false;
PHP,
                'getRedirectMethod' => <<<PHP
// Should the redirection happen naturally via a 300-level GET request, or through an HTML form?
return 'GET';
PHP,
                'getRedirectData' => <<<PHP
// Key-value pairs that are sent along with offsite redirects:
return [];
PHP,
                'getRedirectUrl' => <<<PHP
// If `isRedirect()` is `true`, where should the customer be sent?
return \$this->getData()['complete_payment_url'];
PHP,
                'getTransactionReference' => <<<PHP
// A unique identifier for the transaction, from the gateway:
return \$this->getData()['...'];
PHP,
                'getCode' => <<<PHP
// Gateway-specific success or error code for the response:
return 'OK';
PHP,
                'getMessage' => <<<PHP
// An explanation of the state of the request or payment. Your gateway may require more than two “modes,” and those situations might depend on more than just the response’s success state.
return \$this->isSuccessful() ? {$this->messagePhp('Payment complete.')} : {$this->messagePhp('Payment failed.')};
PHP,
                'redirect' => <<<PHP
// When using `GET` for the redirect method, you have an opportunity to take control of the redirection. Otherwise, Commerce will naturally redirect to the URL returned by `getRedirectUrl()`.
PHP,
            ],
        ]);

        $paymentResponseClass->setComment("$this->displayName payment request response container");
        $responseNamespace->add($paymentResponseClass);

        $this->writePhpClass($responseNamespace);
        $this->command->success("**Request/response class for payments created!**");

        if ($this->supportsSubscriptions) {
            $subscriptionResponseNamespace = (new PhpNamespace($this->responseNamespace))
                ->addUse(Craft::class)
                ->addUse(SubscriptionResponseInterface::class)
                ->addUse(DateTimeHelper::class);

            $subscriptionResponseClass = $this->createClass("{$this->className}SubscriptionResponse", null, [
                self::CLASS_IMPLEMENTS => [
                    SubscriptionResponseInterface::class,
                ],
                self::CLASS_METHODS => [
                    'getReference' => <<<PHP
// Return an identifier for the subscription in the gateway—typically an ID or UUID generated by the processor.
return \$this->getData()['...'] ?? 'plan-identifier';
PHP,
                    'getTrialDays' => <<<PHP
// The time in days that the subscription will be in a "trial" state for.
return 0;
PHP,
                    'getNextPaymentDate' => <<<PHP
\$data = \$this->getData();
return DateTimeHelper::toDateTime(\$this->getData()['next_payment_date']);
PHP,
                    'isCanceled' => <<<PHP
// Based on latest information from the gateway, is the subscription in a canceled state?
return false;
PHP,
                    'isScheduledForCancellation' => <<<PHP
// Based on latest information from the gateway, is the subscription scheduled to be canceled?
return false;
PHP,
                    'isInactive' => <<<PHP
// Based on latest information from the gateway, is the subscription in an inactive state?
return false;
PHP,
                    'getData' => <<<PHP
    // Gather data that should be stored on the subscription. This can come from properties you add to this class (and assign when instantiating it from the main gateway class), an additional API request, the database, or any other source of truth about the subscription’s state.
    return [];
PHP,
                ],
            ]);

            $subscriptionResponseClass->setComment(<<<COMMENT
$this->className subscription response container

You will instantiate and populate this class with data retrieved from your gateway (whatever its source of truth may be). Add a public property and/or a constructor to memoize that data!
COMMENT);

            $subscriptionResponseNamespace->add($subscriptionResponseClass);
            $this->writePhpClass($subscriptionResponseNamespace);
            $this->command->success("**Request/response class for subscriptions created!**");
        }
    }

    private function writePlanClass(): void
    {
        if (!$this->supportsSubscriptions) {
            // Nothing to do!
            return;
        }

        $modelsNamespace = (new PhpNamespace("{$this->baseNamespace}\\models"))
            ->addUse(Plan::class, 'BasePlan');

        // This uses a string for the parent class because they would end up being named the same thing. We’ve defined the alias, above!
        $planClass = $this->createClass('Plan', Plan::class, [
            self::CLASS_METHODS => [
                'canSwitchFrom' => <<<PHP
// Compare this plan to the “current” plan (the one a subscription is based on) and return whether or not they are compatible (i.e. they share a billing cycle):
return true;
PHP,
            ],
        ]);

        $planClass->setComment('Subscription Plan class');

        $modelsNamespace->add($planClass);
        $this->writePhpClass($modelsNamespace);
        $this->command->success("**Plan classes created!**");
    }

    private function writeTemplates(): void
    {
        // Plan settings:
        $planSettingsTemplate = <<<TWIG
{# You must include at least a `reference` input. It will be automatically namespaced by Commerce. #}
{% from "_includes/forms" import selectField %}

{# Plans can be pre-loaded in `getPlanSettingsHtml()` and passed to the template, or fetched via ajax and injected in the client. #}
{{ selectField({
    label: {$this->messageTwig('Reference')},
    name: 'reference',
    value: plan ? plan.reference : null,
    options: plans,
    errors: plan ? plan.getErrors('reference') : null,
}) }}
TWIG;

        $this->command->writeToFile("{$this->basePath}/templates/settings/plan.twig", $planSettingsTemplate);

        $this->command->success("**Created plan settings template!**");

        // Payment form templates:
        $paymentFormTemplate = <<<TWIG
{# Replace this with the HTML your form requires. Keep in mind that any `input` elements’ `name` attributes will be namespaced *after* it is returned from your gateway’s `getPaymentFormHtml()` method! #}
<input type="hidden" name="customGatewayProperty">
TWIG;

        $this->command->writeToFile("{$this->basePath}/templates/forms/payment.twig", $paymentFormTemplate);

        $this->command->success("**Created payment template!**");

        if ($this->supportsSubscriptions) {
            $cancelSubscriptionForm = <<<TWIG
{# Any settings you want to give the customer control of when sending a cancellation request should be added here. #}
TWIG;
            $this->command->writeToFile("{$this->basePath}/templates/forms/subscription-cancel.twig", $cancelSubscriptionForm);

            $switchSubscriptionsForm = <<<TWIG
{# Any settings you need to expose to the subscriber when they switch plans should be included here. #}
TWIG;
            $this->command->writeToFile("{$this->basePath}/templates/forms/subscription-switch.twig", $switchSubscriptionsForm);

            $this->command->success("**Created subscription template!**");
        }
    }
}
