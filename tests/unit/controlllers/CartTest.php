<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit;

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
<<<<<<< HEAD
 * @since 3.x
=======
 * @since 3.2.0
>>>>>>> develop
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

    public function testGetCart() {
        $this->request->headers->set('Accept', 'application/json');
        $return = $this->cartController->runAction('get-cart');

        $this->assertInstanceOf(Response::class, $return);

        $data = $return->data;
        $this->assertArrayHasKey('cart', $data);
        $this->assertArrayHasKey('total', $data['cart']);
        $this->assertEquals(0, $data['cart']['total']);
    }

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

        $this->assertCount(1, $cart->getLineItems());
        $this->assertSame(2, $cart->getTotalQty());
        $this->assertSame($variant->getSalePrice() * 2, $cart->getTotal());
    }

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

        $this->assertCount(1, $cart->getLineItems(), 'Only one line item can be added');
        $this->assertSame($lastItem['qty'], $cart->getTotalQty());
        $lineItem = $cart->getLineItems()[0];
        $this->assertEquals($lastItem['id'], $lineItem->purchasableId, 'The last line item to be added is the one in the cart');
    }

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

        $this->assertCount(2, $cart->getLineItems(), 'Has all items in the car');
    }
}