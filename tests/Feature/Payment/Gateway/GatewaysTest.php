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
