<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\controllers;

use Codeception\Test\Unit;
use Craft;
use craft\commerce\behaviors\CustomerBehavior;
use craft\commerce\controllers\CartController;
use craft\commerce\elements\Product;
use craft\commerce\elements\Variant;
use craft\commerce\Plugin;
use craft\elements\User;
use craft\errors\ElementNotFoundException;
use craft\errors\InvalidPluginException;
use craft\web\Request;
use craftcommercetests\fixtures\CustomerAddressFixture;
use craftcommercetests\fixtures\CustomerFixture;
use craftcommercetests\fixtures\ProductFixture;
use craftcommercetests\fixtures\SalesFixture;
use Throwable;
use UnitTester;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\base\InvalidRouteException;
use yii\web\Response;

/**
 * CartTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.2.0
 */
class CartTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected UnitTester $tester;

    /**
     * @var CartController
     */
    protected CartController $cartController;

    /**
     * @var Request
     */
    protected Request $request;

    /**
     * @return array
     */
    public function _fixtures(): array
    {
        return [
            'products' => [
                'class' => ProductFixture::class,
            ],
            'sales' => [
                'class' => SalesFixture::class,
            ],
            'customer' => [
                'class' => CustomerFixture::class,
            ],
            'addresses' => [
                'class' => CustomerAddressFixture::class,
            ],
        ];
    }

    /**
     * @inheritDoc
     */
    protected function _before(): void
    {
        parent::_before();

        $this->cartController = new CartController('cart', Plugin::getInstance());
        $this->request = Craft::$app->getRequest();
        $this->request->enableCsrfValidation = false;
    }

    /**
     * @throws InvalidRouteException
     */
    public function testGetCart(): void
    {
        $this->request->headers->set('Accept', 'application/json');
        $return = $this->cartController->runAction('get-cart');

        self::assertInstanceOf(Response::class, $return);

        $data = $return->data;
        self::assertArrayHasKey('cart', $data);
        self::assertArrayHasKey('total', $data['cart']);
        self::assertEquals(0, $data['cart']['total']);

        // Assert types
        self::assertIsString($data['cart']['number']);
        self::assertNull($data['cart']['reference']);
        self::assertNull($data['cart']['couponCode']);
        self::assertIsBool($data['cart']['isCompleted']);
        self::assertNull($data['cart']['dateOrdered']);
        self::assertNull($data['cart']['datePaid']);
        self::assertNull($data['cart']['dateAuthorized']);
        self::assertIsString($data['cart']['currency']);
        self::assertNull($data['cart']['gatewayId']);
        self::assertIsString($data['cart']['lastIp']);
        self::assertNull($data['cart']['message']);
        self::assertNull($data['cart']['returnUrl']);
        self::assertNull($data['cart']['cancelUrl']);
        self::assertNull($data['cart']['orderStatusId']);
        self::assertIsString($data['cart']['orderLanguage']);
        self::assertIsInt($data['cart']['orderSiteId']);
        self::assertIsString($data['cart']['origin']);
        self::assertNull($data['cart']['billingAddressId']);
        self::assertNull($data['cart']['shippingAddressId']);
        self::assertIsBool($data['cart']['makePrimaryShippingAddress']);
        self::assertIsBool($data['cart']['makePrimaryBillingAddress']);
        self::assertIsBool($data['cart']['shippingSameAsBilling']);
        self::assertIsBool($data['cart']['billingSameAsShipping']);
        self::assertNull($data['cart']['estimatedBillingAddressId']);
        self::assertNull($data['cart']['estimatedShippingAddressId']);
        self::assertIsBool($data['cart']['estimatedBillingSameAsShipping']);
        self::assertIsString($data['cart']['shippingMethodHandle']);
        self::assertNull($data['cart']['shippingMethodName']);
        self::assertNull($data['cart']['customerId']);
        self::assertIsBool($data['cart']['registerUserOnOrderComplete']);
        self::assertNull($data['cart']['paymentSourceId']);
        self::assertNull($data['cart']['storedTotalPrice']);
        self::assertNull($data['cart']['storedTotalPaid']);
        self::assertNull($data['cart']['storedItemTotal']);
        self::assertNull($data['cart']['storedItemSubtotal']);
        self::assertNull($data['cart']['storedTotalShippingCost']);
        self::assertNull($data['cart']['storedTotalDiscount']);
        self::assertNull($data['cart']['storedTotalTax']);
        self::assertNull($data['cart']['storedTotalTaxIncluded']);
        self::assertNull($data['cart']['id']);
        self::assertIsBool($data['cart']['enabled']);
        self::assertIsInt($data['cart']['siteId']);
        self::assertIsString($data['cart']['status']);
        self::assertIsFloat($data['cart']['adjustmentSubtotal']);
        self::assertIsFloat($data['cart']['adjustmentsTotal']);
        self::assertIsString($data['cart']['paymentCurrency']);
        self::assertIsFloat($data['cart']['paymentAmount']);
        self::assertNull($data['cart']['email']);
        self::assertIsBool($data['cart']['isPaid']);
        self::assertIsFloat($data['cart']['itemSubtotal']);
        self::assertIsFloat($data['cart']['itemTotal']);
        self::assertIsArray($data['cart']['lineItems']);
        self::assertIsArray($data['cart']['orderAdjustments']);
        self::assertIsFloat($data['cart']['outstandingBalance']);
        self::assertIsString($data['cart']['paidStatus']);
        self::assertIsString($data['cart']['recalculationMode']);
        self::assertIsString($data['cart']['shortNumber']);
        self::assertIsFloat($data['cart']['totalPaid']);
        self::assertIsFloat($data['cart']['total']);
        self::assertIsFloat($data['cart']['totalPrice']);
        self::assertIsInt($data['cart']['totalQty']);
        self::assertIsFloat($data['cart']['totalSaleAmount']);
        self::assertIsFloat($data['cart']['totalWeight']);
        self::assertIsString($data['cart']['adjustmentSubtotalAsCurrency']);
        self::assertIsString($data['cart']['adjustmentsTotalAsCurrency']);
        self::assertIsString($data['cart']['itemSubtotalAsCurrency']);
        self::assertIsString($data['cart']['itemTotalAsCurrency']);
        self::assertIsString($data['cart']['outstandingBalanceAsCurrency']);
        self::assertIsString($data['cart']['paymentAmountAsCurrency']);
        self::assertIsString($data['cart']['totalPaidAsCurrency']);
        self::assertIsString($data['cart']['totalAsCurrency']);
        self::assertIsString($data['cart']['totalPriceAsCurrency']);
        self::assertIsString($data['cart']['totalSaleAmountAsCurrency']);
        self::assertIsString($data['cart']['totalTaxAsCurrency']);
        self::assertIsString($data['cart']['totalTaxIncludedAsCurrency']);
        self::assertIsString($data['cart']['totalShippingCostAsCurrency']);
        self::assertIsString($data['cart']['totalDiscountAsCurrency']);
        self::assertIsString($data['cart']['storedTotalPriceAsCurrency']);
        self::assertIsString($data['cart']['storedTotalPaidAsCurrency']);
        self::assertIsString($data['cart']['storedItemTotalAsCurrency']);
        self::assertIsString($data['cart']['storedItemSubtotalAsCurrency']);
        self::assertIsString($data['cart']['storedTotalShippingCostAsCurrency']);
        self::assertIsString($data['cart']['storedTotalDiscountAsCurrency']);
        self::assertIsString($data['cart']['storedTotalTaxAsCurrency']);
        self::assertIsString($data['cart']['storedTotalTaxIncludedAsCurrency']);
        self::assertIsString($data['cart']['paidStatusHtml']);
        self::assertIsString($data['cart']['customerLinkHtml']);
        self::assertIsString($data['cart']['orderStatusHtml']);
        self::assertIsFloat($data['cart']['totalTax']);
        self::assertIsFloat($data['cart']['totalTaxIncluded']);
        self::assertIsFloat($data['cart']['totalShippingCost']);
        self::assertIsFloat($data['cart']['totalDiscount']);
        self::assertIsArray($data['cart']['availableShippingMethodOptions']);
        self::assertIsArray($data['cart']['notices']);
        self::assertNull($data['cart']['billingAddress']);
        self::assertNull($data['cart']['shippingAddress']);
    }

    /**
     * @throws Throwable
     * @throws ElementNotFoundException
     * @throws Exception
     * @throws InvalidRouteException
     */
    public function testAddSinglePurchasable(): void
    {
        $this->request->headers->set('Accept', 'application/json');
        $this->request->headers->set('X-Http-Method-Override', 'POST');

        $variant = Variant::find()->sku('rad-hood')->one();
        $this->request->setBodyParams([
            'purchasableId' => $variant->id,
            'qty' => 2,
        ]);

        $this->cartController->runAction('update-cart');
        $cart = Plugin::getInstance()->getCarts()->getCart();

        self::assertCount(1, $cart->getLineItems());
        self::assertSame(2, $cart->getTotalQty());
        self::assertSame($variant->getSalePrice() * 2, $cart->getTotal());

        if ($cart->id) {
            Craft::$app->getElements()->deleteElement($cart, true);
        }
    }

    /**
     * @throws Throwable
     * @throws ElementNotFoundException
     * @throws InvalidPluginException
     * @throws Exception
     * @throws InvalidRouteException
     */
    public function testAddMultiplePurchasables(): void
    {
        $this->request->headers->set('X-Http-Method-Override', 'POST');

        $variants = Variant::find()->sku(['rad-hood', 'hct-white'])->all();
        $purchasables = [];
        foreach ($variants as $key => $variant) {
            $purchasables[] = [
                'id' => $variant->id,
                'qty' => $key + 1,
            ];
        }
        $this->request->setBodyParams([
            'purchasables' => $purchasables,
        ]);

        $this->cartController->runAction('update-cart');
        $cart = Plugin::getInstance()->getCarts()->getCart();

        self::assertCount(2, $cart->getLineItems(), 'Has all items in the car');

        if ($cart->id) {
            Craft::$app->getElements()->deleteElement($cart, true);
        }
    }

    /**
     * @throws ElementNotFoundException
     * @throws Exception
     * @throws InvalidPluginException
     * @throws InvalidRouteException
     * @throws Throwable
     * @throws \craft\errors\InvalidFieldException
     * @throws InvalidConfigException
     */
    public function testAddAddressCustomFieldsOnUpdateCart(): void
    {
        $this->request->headers->set('X-Http-Method-Override', 'POST');

        $shippingAddress = [
            'addressLine1' => '1 Main Street',
            'fields' => ['testPhone' => '12345'],
        ];
        $billingAddress = [
            'addressLine1' => '100 Main Street',
            'fields' => ['testPhone' => '67890'],
        ];

        $this->request->setBodyParams([
            'shippingAddress' => $shippingAddress,
            'billingAddress' => $billingAddress,
        ]);

        $this->cartController->runAction('update-cart');

        $cart = Plugin::getInstance()->getCarts()->getCart();

        $cartShippingAddress = $cart->getShippingAddress();
        $cartBillingAddress = $cart->getBillingAddress();

        self::assertEquals($shippingAddress['addressLine1'], $cartShippingAddress->addressLine1);
        self::assertEquals($shippingAddress['fields']['testPhone'], $cartShippingAddress->testPhone);
        self::assertEquals($billingAddress['addressLine1'], $cartBillingAddress->addressLine1);
        self::assertEquals($billingAddress['fields']['testPhone'], $cartBillingAddress->testPhone);

        if ($cart->id) {
            Craft::$app->getElements()->deleteElement($cart, true);
        }
    }

    /**
     * @param string $customerHandle
     * @param bool $autoSet
     * @return void
     * @throws ElementNotFoundException
     * @throws Exception
     * @throws InvalidConfigException
     * @throws InvalidPluginException
     * @throws InvalidRouteException
     * @throws Throwable
     * @dataProvider autoSetNewCartAddressesDataProvider
     * @since 4.0.4
     */
    public function testAutoSetNewCartAddresses(string $customerHandle, bool $autoSet): void
    {
        $this->request->headers->set('X-Http-Method-Override', 'POST');

        $customerFixture = $this->tester->grabFixture('customer');
        /** @var User|CustomerBehavior $customer */
        $customer = $customerFixture->getElement($customerHandle);
        Craft::$app->getUser()->setIdentity(
            Craft::$app->getUsers()->getUserById($customer->id)
        );
        $customerShippingAddress = $customer->getPrimaryShippingAddress();

        $productsFixture = $this->tester->grabFixture('products');
        /** @var Product $product */
        $product = $productsFixture->getElement('rad-hoodie');
        $bodyParams = [
            'purchasableId' => $product->getDefaultVariant()->id,
            'qty' => 2,
        ];

        $this->request->setBodyParams($bodyParams);
        $originalSettingValue = Plugin::getInstance()->getSettings()->autoSetNewCartAddresses;
        Plugin::getInstance()->getSettings()->autoSetNewCartAddresses = $autoSet;

        $this->cartController->runAction('update-cart');

        $cart = Plugin::getInstance()->getCarts()->getCart();

        $shippingAddress = $cart->getShippingAddress();

        if ($autoSet === true) {
            self::assertEquals($customerShippingAddress->addressLine1, $shippingAddress->addressLine1);
        } else {
            self::assertNull($shippingAddress);
        }

        Plugin::getInstance()->getCarts()->forgetCart();

        if ($autoSet === true) {
            Craft::$app->getElements()->deleteElement($cart->getShippingAddress(), true);
        }

        Craft::$app->getElements()->deleteElement($cart, true);

        Plugin::getInstance()->getSettings()->autoSetNewCartAddresses = $originalSettingValue;
    }

    /**
     * @return array[]
     * @since 4.0.4
     */
    public function autoSetNewCartAddressesDataProvider(): array
    {
        return [
            'auto-set' => [
                'customer3', // customer
                true, // auto set
            ],
            'dont-auto-set' => [
                'customer3', // customer
                true, // auto set
            ],
        ];
    }

    /**
     * @param bool|null $saveBillingAddress
     * @param bool|null $saveShippingAddress
     * @param bool|null $saveBoth
     * @return void
     * @throws ElementNotFoundException
     * @throws Exception
     * @throws InvalidConfigException
     * @throws InvalidPluginException
     * @throws InvalidRouteException
     * @throws Throwable
     * @since 4.3.0
     * @dataProvider setSaveAddressesDataProvider
     */
    public function testSetSaveAddresses(?bool $saveBillingAddress, ?bool $saveShippingAddress, ?bool $saveBoth): void
    {
        $this->request->headers->set('X-Http-Method-Override', 'POST');

        $bodyParams = [];
        if ($saveBoth) {
            $bodyParams['saveAddressesOnOrderComplete'] = true;
        } else {
            $bodyParams['saveBillingAddressOnOrderComplete'] = $saveBillingAddress;
            $bodyParams['saveShippingAddressOnOrderComplete'] = $saveShippingAddress;
        }

        $this->request->setBodyParams($bodyParams);
        $this->cartController->runAction('update-cart');

        $cart = Plugin::getInstance()->getCarts()->getCart();

        if ($saveBoth) {
            self::assertTrue($cart->saveBillingAddressOnOrderComplete);
            self::assertTrue($cart->saveShippingAddressOnOrderComplete);
        } else {
            self::assertEquals($saveBillingAddress, $cart->saveBillingAddressOnOrderComplete);
            self::assertEquals($saveShippingAddress, $cart->saveShippingAddressOnOrderComplete);
        }

        Plugin::getInstance()->getCarts()->forgetCart();

        Craft::$app->getElements()->deleteElement($cart, true);
    }

    /**
     * @return array[]
     * @since 4.3.0
     */
    public function setSaveAddressesDataProvider(): array
    {
        return [
            'save-billing' => [
                true, // save billing
                false, // save shipping
                false, // save both
            ],
            'save-shipping' => [
                false, // save billing
                true, // save shipping
                false, // save both
            ],
            'save-both' => [
                false, // save billing
                false, // save shipping
                true, // save both
            ],
            'save-both-individually' => [
                true, // save billing
                true, // save shipping
                false, // save both
            ],
        ];
    }
}
