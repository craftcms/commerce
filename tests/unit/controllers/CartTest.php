<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\controllers;

use Codeception\Test\Unit;
use Craft;
use craft\commerce\controllers\CartController;
use craft\commerce\elements\Variant;
use craft\commerce\Plugin;
use craft\web\Request;
use craftcommercetests\fixtures\SalesFixture;
use UnitTester;
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
    protected $tester;

    /**
     * @var CartController
     */
    protected $cartController;

    /**
     * @var Request
     */
    protected $request;

    /**
     * @return array
     */
    public function _fixtures(): array
    {
        return [
            'sales' => [
                'class' => SalesFixture::class,
            ],
        ];
    }

    /**
     * @inheritDoc
     */
    protected function _before()
    {
        parent::_before();

        $this->cartController = new CartController('cart', Plugin::getInstance());
        $this->request = Craft::$app->getRequest();
        $this->request->enableCsrfValidation = false;
    }

    /**
     * @throws \yii\base\InvalidRouteException
     */
    public function testGetCart() {
        $this->request->headers->set('Accept', 'application/json');
        $return = $this->cartController->runAction('get-cart');

        self::assertInstanceOf(Response::class, $return);

        $data = $return->data;
        self::assertArrayHasKey('cart', $data);
        self::assertArrayHasKey('total', $data['cart']);
        self::assertEquals(0, $data['cart']['total']);
    }

    /**
     * @throws \Throwable
     * @throws \craft\errors\ElementNotFoundException
     * @throws \yii\base\Exception
     * @throws \yii\base\InvalidRouteException
     */
    public function testAddSinglePurchasable()
    {
        $this->request->headers->set('Accept', 'application/json');
        $this->request->headers->set('X-Http-Method-Override', 'POST');

        $variant = Variant::find()->sku('rad-hood')->one();
        $this->request->setBodyParams([
            'purchasableId' => $variant->id,
            'qty' => 2
        ]);

        $this->cartController->runAction('update-cart');
        $cart = Plugin::getInstance()->getCarts()->getCart();

        self::assertCount(1, $cart->getLineItems());
        self::assertSame(2, $cart->getTotalQty());
        self::assertSame($variant->getSalePrice() * 2, $cart->getTotal());
    }

    /**
     * @throws \Throwable
     * @throws \craft\errors\ElementNotFoundException
     * @throws \yii\base\Exception
     * @throws \yii\base\InvalidRouteException
     */
    public function testAddMultiplePurchasablesLite()
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
            'purchasables' => $purchasables
        ]);

        $lastItem = array_pop($purchasables);

        $this->cartController->runAction('update-cart');
        $cart = Plugin::getInstance()->getCarts()->getCart();

        self::assertCount(1, $cart->getLineItems(), 'Only one line item can be added');
        self::assertSame($lastItem['qty'], $cart->getTotalQty());
        $lineItem = $cart->getLineItems()[0];
        self::assertEquals($lastItem['id'], $lineItem->purchasableId, 'The last line item to be added is the one in the cart');
    }

    /**
     * @throws \Throwable
     * @throws \craft\errors\ElementNotFoundException
     * @throws \craft\errors\InvalidPluginException
     * @throws \yii\base\Exception
     * @throws \yii\base\InvalidRouteException
     */
    public function testAddMultiplePurchasables()
    {
        Craft::$app->getPlugins()->switchEdition('commerce', Plugin::EDITION_PRO);
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
            'purchasables' => $purchasables
        ]);

        $this->cartController->runAction('update-cart');
        $cart = Plugin::getInstance()->getCarts()->getCart();

        self::assertCount(2, $cart->getLineItems(), 'Has all items in the car');
    }
}