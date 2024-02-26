<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\elements\order;

use Codeception\Test\Unit;
use craft\commerce\elements\Order;
use craft\commerce\Plugin;
use craft\elements\Address;
use UnitTester;

/**
 * OrderValidationTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.1
 */
class OrderValidationTest extends Unit
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
     * @var Plugin|null
     */
    protected ?Plugin $pluginInstance;

    /**
     *
     */
    public function testAddressValidation(): void
    {
        $billingAddress = new Address();
        $this->order->setBillingAddress($billingAddress);

        $shippingAddress = new Address();
        $shippingAddress->addressLine1 = '1 Main Street';
        $this->order->setShippingAddress($shippingAddress);

        $validationResult = $this->order->validate();

        self::assertFalse($validationResult);
        self::assertNotEmpty($this->order->getErrors());
        self::assertArrayHasKey('billingAddress.administrativeArea', $this->order->getErrors());
        self::assertArrayHasKey('billingAddress.locality', $this->order->getErrors());
        self::assertArrayHasKey('billingAddress.postalCode', $this->order->getErrors());
        self::assertArrayHasKey('billingAddress.addressLine1', $this->order->getErrors());
        self::assertArrayHasKey('shippingAddress.administrativeArea', $this->order->getErrors());
        self::assertArrayHasKey('shippingAddress.locality', $this->order->getErrors());
        self::assertArrayHasKey('shippingAddress.postalCode', $this->order->getErrors());

        $billingAddress->addressLine1 = 'Downtown';
        $shippingAddress->locality = $billingAddress->locality = 'LA';
        $shippingAddress->administrativeArea = $billingAddress->administrativeArea = 'CA';
        $shippingAddress->postalCode = $billingAddress->postalCode = '90210';

        $this->order->setBillingAddress($billingAddress);
        $this->order->setShippingAddress($shippingAddress);

        $validationResult = $this->order->validate();

        self::assertTrue($validationResult);
        self::assertEmpty($this->order->getErrors());
    }

    /**
     * @inheritdoc
     */
    protected function _before(): void
    {
        parent::_before();

        $this->pluginInstance = Plugin::getInstance();

        $this->order = new Order();
    }

    /**
     * @inheritdoc
     */
    protected function _after(): void
    {
        parent::_after();
    }
}
