<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\services;

use Codeception\Test\Unit;
use Craft;
use craft\commerce\elements\Order;
use craft\commerce\Plugin;
use craft\commerce\services\Carts;
use craftcommercetests\fixtures\CustomerAddressFixture;
use craftcommercetests\fixtures\CustomerFixture;
use UnitTester;

/**
 * CartsTest.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.2.2
 */
class CartsTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected UnitTester $tester;

    public function _fixtures(): array
    {
        return [
            'customerAddresses' => [
                'class' => CustomerAddressFixture::class,
            ],
        ];
    }

    /**
     * @param string $email
     * @param bool $autoSet
     * @param bool $hasBillingAddress
     * @param bool $hasShippingAddress
     * @return void
     * @throws \Throwable
     * @throws \craft\errors\ElementNotFoundException
     * @throws \yii\base\Exception
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\base\UnknownPropertyException
     * @dataProvider getCartDataProvider
     */
    public function testGetCartAutoSetAddresses(string $email, bool $autoSet, bool $hasBillingAddress, bool $hasShippingAddress, bool $loggedIn): void
    {
        $cartNumber = Plugin::getInstance()->getCarts()->generateCartNumber();
        Plugin::getInstance()->getSettings()->autoSetNewCartAddresses = $autoSet;
        Plugin::getInstance()->set('carts', $this->make(Carts::class, [
            'getSessionCartNumber' => function() use ($cartNumber) {
                return $cartNumber;
            },
        ]));

        if ($loggedIn) {
            $user = Craft::$app->getUsers()->getUserByUsernameOrEmail($email);
            Craft::$app->getUser()->setIdentity($user);
            Craft::$app->getUser()->getIdentity()->password = $user->password;
        }

        $newCart = new Order();
        $newCart->setEmail($email);
        $newCart->number = $cartNumber;
        Craft::$app->getElements()->saveElement($newCart, false);
        // Plugin::getInstance()->getCarts()->__set('cart', $newCart);

        $cart = Plugin::getInstance()->getCarts()->getCart();

        if ($hasBillingAddress) {
            self::assertNotNull($cart->getBillingAddress());
        } else {
            self::assertNull($cart->getBillingAddress());
        }
        if ($hasShippingAddress) {
            self::assertNotNull($cart->getShippingAddress());
        } else {
            self::assertNull($cart->getShippingAddress());
        }

        Craft::$app->getElements()->deleteElement($newCart, true);
    }

    public function getCartDataProvider(): array
    {
        return [
            'inactive-user-no-auto-set-addresses' => ['inactive.user@crafttest.com', false, false, false, false],
            'inactive-user-auto-set-addresses' => ['inactive.user@crafttest.com', true, false, false, false],
            'logged-in-user-no-auto-set-addresses' => ['cred.user@crafttest.com', false, false, false, true],
            'logged-in-user-auto-set-addresses' => ['cred.user@crafttest.com', true, true, true, true],
        ];
    }
}