<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\elements\order;

use Codeception\Test\Unit;
use Craft;
use craft\commerce\elements\Order;
use craft\commerce\Plugin;
use craft\elements\Address;
use UnitTester;
use yii\base\InvalidConfigException;

/**
 * OrderAddressesTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.1
 */
class OrderAddressesTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected UnitTester $tester;

    /**
     * @var Order
     */
    protected Order $order;

    /**
     * @var string
     */
    protected string $originalEdition;

    /**
     * @var Plugin|null
     */
    protected ?Plugin $pluginInstance;

    /**
     * @param array|null $billingAddress
     * @param array|null $shippingAddress
     * @param bool $expected
     * @return void
     * @throws InvalidConfigException
     * @dataProvider hasMatchingAddressesDataProvider
     */
    public function testHasMatchingAddresses(?array $billingAddress, ?array $shippingAddress, bool $expected): void
    {
        $this->order->setBillingAddress(Craft::createObject($billingAddress));
        $this->order->setShippingAddress(Craft::createObject($shippingAddress));

        self::assertSame($expected, $this->order->hasMatchingAddresses());
    }

    public function hasMatchingAddressesDataProvider(): array
    {
        return [
            'all-matching' => [
                [
                    'class' => Address::class,
                    'fullName' => 'Johnny Appleseed',
                    'addressLine1' => '1 Main Street',
                ],
                [
                    'class' => Address::class,
                    'fullName' => 'Johnny Appleseed',
                    'addressLine1' => '1 Main Street',
                ],
                true,
            ],
            'no-matching-address' => [
                [
                    'class' => Address::class,
                    'fullName' => 'Johnny Appleseed',
                    'addressLine1' => '1 Main Street',
                ],
                [
                    'class' => Address::class,
                    'fullName' => 'Johnny Appleseed',
                    'addressLine1' => '123 Main Street',
                ],
                false,
            ],
            'no-matching-name' => [
                [
                    'class' => Address::class,
                    'fullName' => 'Johnny Appleseed',
                    'addressLine1' => '1 Main Street',
                ],
                [
                    'class' => Address::class,
                    'fullName' => 'Jenny Appleseed',
                    'addressLine1' => '1 Main Street',
                ],
                false,
            ],
            'all-matching-full' => [
                [
                    'class' => Address::class,
                    'fullName' => 'Johnny Appleseed',
                    'addressLine1' => '1 Main Street',
                    'addressLine2' => 'SW',
                    'locality' => 'Bend',
                    'administrativeArea' => 'OR',
                    'countryCode' => 'US',
                    'postalCode' => '12345',
                ],
                [
                    'class' => Address::class,
                    'fullName' => 'Johnny Appleseed',
                    'addressLine1' => '1 Main Street',
                    'addressLine2' => 'SW',
                    'locality' => 'Bend',
                    'administrativeArea' => 'OR',
                    'countryCode' => 'US',
                    'postalCode' => '12345',
                ],
                true,
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    protected function _before(): void
    {
        parent::_before();

        $this->pluginInstance = Plugin::getInstance();
        $this->originalEdition = $this->pluginInstance->edition;
        $this->pluginInstance->edition = Plugin::EDITION_PRO;

        $this->order = new Order();
    }

    /**
     * @inheritdoc
     */
    protected function _after(): void
    {
        parent::_after();

        $this->pluginInstance->edition = $this->originalEdition;
    }
}
