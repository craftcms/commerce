<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\services;

use Codeception\Test\Unit;
use Craft;
use craft\commerce\gateways\Dummy;
use craft\commerce\gateways\Manual;
use craft\commerce\Plugin;
use craft\helpers\ArrayHelper;
use UnitTester;
use yii\base\InvalidConfigException;

/**
 * GatewaysTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.0.0
 */
class GatewaysTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected UnitTester $tester;

    /**
     * @param array $gateways
     * @param int $count
     * @return void
     * @throws InvalidConfigException
     * @dataProvider getAllCustomerEnabledGatewaysDataProvider
     */
    public function testGetAllCustomerEnabledGateways(array $gateways, int $count, array $enabledKeys): void
    {
        foreach ($gateways as $name => &$gateway) {
            [$class, $attributes] = $gateway;
            $attributes['name'] = $name;

            if (isset($attributes['isFrontendEnabled']) && is_array($attributes['isFrontendEnabled'])) {
                putenv(substr($attributes['isFrontendEnabled']['var'], 1) . '=' . $attributes['isFrontendEnabled']['value']);
                $attributes['isFrontendEnabled'] = $attributes['isFrontendEnabled']['var'];
            }
            $gateway = Craft::createObject($class, ['config' => ['attributes' => $attributes]]);
        }

        unset($gateway);

        $this->tester->mockMethods(Plugin::getInstance(), 'gateways', [
            'getAllGateways' => $gateways,
        ]);

        $enabledGateways = Plugin::getInstance()->getGateways()->getAllCustomerEnabledGateways();
        self::assertCount($count, $enabledGateways);
        self::assertEquals($enabledKeys, ArrayHelper::getColumn($enabledGateways, 'name', false));
    }

    public function getAllCustomerEnabledGatewaysDataProvider(): array
    {
        return [
            [
                [
                    'dummy' => [
                        Dummy::class,
                        [
                            'isFrontendEnabled' => true,
                        ],
                    ],
                    'dummy-enabled-string' => [
                        Dummy::class,
                        [
                            'isFrontendEnabled' => '1',
                        ],
                    ],
                    'dummy-disabled-string' => [
                        Dummy::class,
                        [
                            'isFrontendEnabled' => '0',
                        ],
                    ],
                    'dummy-enabled-env' => [
                        Dummy::class,
                        [
                            'isFrontendEnabled' => ['var' => '$DUMMY_ENABLED', 'value' => 'true'],
                        ],
                    ],
                    'dummy-disabled-env' => [
                        Dummy::class,
                        [
                            'isFrontendEnabled' => ['var' => '$DUMMY_DISABLED', 'value' => 'false'],
                        ],
                    ],
                    'manual' => [
                        Manual::class,
                        [
                            'isFrontendEnabled' => false,
                        ],
                    ],
                ],
                3,
                ['dummy', 'dummy-enabled-string', 'dummy-enabled-env'],
            ],
        ];
    }
}
