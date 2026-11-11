<?php

declare(strict_types=1);

use CraftCms\Commerce\Payment\Gateway\Gateway;
use CraftCms\Commerce\Payment\Gateway\Gateways;
use CraftCms\Commerce\Payment\Gateway\Types\Dummy;
use CraftCms\Commerce\Payment\Gateway\Types\Manual;

/** @param array<string, array{0: class-string<Gateway>, 1: array}> $gatewaySpecs */
function mockGateways(array $gatewaySpecs): void
{
    $gateways = [];
    foreach ($gatewaySpecs as $name => [$class, $attributes]) {
        $attributes['name'] = $name;

        if (isset($attributes['isFrontendEnabled']) && is_array($attributes['isFrontendEnabled'])) {
            putenv(substr((string)$attributes['isFrontendEnabled']['var'], 1) . '=' . $attributes['isFrontendEnabled']['value']);
            $attributes['isFrontendEnabled'] = $attributes['isFrontendEnabled']['var'];
        }

        $gateways[] = app(Gateways::class)->createGateway(['type' => $class, ...$attributes]);
    }

    $mock = Mockery::mock(Gateways::class)->makePartial();
    $mock->shouldReceive('getAllGateways')->andReturn(collect($gateways));
    app()->instance(Gateways::class, $mock);
}

test('getAllCustomerEnabledGateways filters to only frontend-enabled gateways, resolving env-var references', function() {
    mockGateways([
        'dummy' => [Dummy::class, ['isFrontendEnabled' => true]],
        'dummy-enabled-string' => [Dummy::class, ['isFrontendEnabled' => '1']],
        'dummy-disabled-string' => [Dummy::class, ['isFrontendEnabled' => '0']],
        'dummy-enabled-env' => [Dummy::class, ['isFrontendEnabled' => ['var' => '$DUMMY_ENABLED', 'value' => 'true']]],
        'dummy-disabled-env' => [Dummy::class, ['isFrontendEnabled' => ['var' => '$DUMMY_DISABLED', 'value' => 'false']]],
        'manual' => [Manual::class, ['isFrontendEnabled' => false]],
    ]);

    $enabledGateways = app(Gateways::class)->getAllCustomerEnabledGateways();

    expect($enabledGateways)->toHaveCount(3);
    expect($enabledGateways->pluck('name')->values()->all())->toBe([
        'dummy', 'dummy-enabled-string', 'dummy-enabled-env',
    ]);
});

test('getAllGatewayTypes returns the built-in Dummy and Manual gateway types', function() {
    expect(app(Gateways::class)->getAllGatewayTypes())->toEqualCanonicalizing([
        Dummy::class,
        Manual::class,
    ]);
});

test('gateway settings include properties declared on abstract gateway subclasses', function() {
    $gateway = app(Gateways::class)->createGateway([
        'type' => GatewayWithSharedSettings::class,
        'name' => 'Shared',
        'handle' => 'shared',
        'settings' => ['secretKey' => 'sk_test', 'publishableKey' => 'pk_test'],
    ]);

    expect($gateway->getSettings())->toEqualCanonicalizing(['secretKey' => 'sk_test', 'publishableKey' => 'pk_test'])
        ->and($gateway->getConfig()['settings'])->toEqualCanonicalizing(['secretKey' => 'sk_test', 'publishableKey' => 'pk_test']);
});

abstract class GatewayWithSharedSettingsBase extends Dummy
{
    public ?string $secretKey = null;
}

class GatewayWithSharedSettings extends GatewayWithSharedSettingsBase
{
    public ?string $publishableKey = null;
}

test('getPaymentFormHtml renders the gateway’s payment form Ui with the payment form’s values and errors', function() {
    $gateway = app(Gateways::class)->createGateway(['type' => Dummy::class]);
    $form = $gateway->getPaymentFormModel();
    $form->setAttributes(['firstName' => 'Ada', 'number' => '4242 4242 4242 4241', 'expiry' => '13/2020']);
    $form->validate();

    $html = $gateway->getPaymentFormHtml(['paymentForm' => $form]);

    expect($html)
        ->toContain('name="firstName" value="Ada"')
        ->toContain('name="number" value="4242424242424241"')
        ->toContain('name="expiry" value="13/2020"')
        ->toContain('name="cvv"')
        ->toContain('Not a valid credit card number.')
        // `month` and `year` have no control of their own, so their errors are listed for the whole form.
        ->toContain('The month field must not be greater than 12.');
});

test('payment forms are optional', function() {
    $gateway = app(Gateways::class)->createGateway(['type' => Manual::class]);

    expect($gateway->paymentFormUi($gateway->getPaymentFormModel()))->toBeNull()
        ->and($gateway->getPaymentFormHtml([]))->toBeNull()
        ->and($gateway->getPaymentConfirmationFormHtml([]))->toBe('');
});

test('getPaymentConfirmationFormHtml renders the gateway’s confirmation form Ui', function() {
    $gateway = app(Gateways::class)->createGateway(['type' => GatewayWithConfirmationForm::class]);

    expect($gateway->getPaymentConfirmationFormHtml(['token' => 'pi_123']))->toContain('name="paymentIntentId" value="pi_123"');
});

class GatewayWithConfirmationForm extends Dummy
{
    public function paymentConfirmationFormUi(array $params = []): ?\CraftCms\Cms\Ui\Ui
    {
        return \CraftCms\Cms\Ui\Ui::make([\CraftCms\Cms\Ui\Nodes\Field::make('Payment intent', \CraftCms\Cms\Ui\Controls\Text::make('paymentIntentId')->value($params['token']))]);
    }
}
