<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\controllers;

use Codeception\Test\Unit;
use Craft;
use craft\commerce\controllers\OrdersController;
use craft\commerce\Plugin;
use craft\web\Request;
use craftcommercetests\fixtures\ProductFixture;
use UnitTester;
use yii\web\Response;

/**
 * OrdersControllerTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.x
 */
class OrdersControllerTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected $tester;

    /**
     * @var  OrdersController
     */
    protected $controller;

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
            'products' => [
                'class' => ProductFixture::class
            ],
        ];
    }

    /**
     * @inheritDoc
     */
    protected function _before()
    {
        parent::_before();

        // Mock admin user
        Craft::$app->getUser()->setIdentity(
            Craft::$app->getUsers()->getUserById('1')
        );
        Craft::$app->getUser()->getIdentity()->password = '$2y$13$tAtJfYFSRrnOkIbkruGGEu7TPh0Ixvxq0r.XgWqIgNWuWpxpA7SxK';

        $this->controller = new OrdersController('orders', Plugin::getInstance());
        $this->request = Craft::$app->getRequest();
        $this->request->enableCsrfValidation = false;
    }

    public function testPurchasblesTable()
    {
        $this->request->headers->set('Accept', 'application/json');

        $response = $this->controller->runAction('purchasables-table');

        $this->assertInstanceOf(Response::class, $response);

        $this->assertArrayHasKey('pagination', $response->data);
        $this->assertArrayHasKey('data', $response->data);

        $this->assertSame(10, $response->data['pagination']['total']);
        $this->assertCount(10, $response->data['data']);

        $purchasable = array_pop($response->data['data']);

        $keys = ['id', 'price', 'description', 'sku', 'priceAsCurrency', 'isAvailable', 'detail'];
        foreach ($keys as $key) {
            $this->assertArrayHasKey($key, $purchasable);
        }

        $this->assertEquals('hct-blue', $purchasable['sku']);
    }

    public function testPurchasblesTableSort()
    {
        $this->request->headers->set('Accept', 'application/json');

        Craft::$app->getRequest()->setQueryParams(['sort' => 'sku|desc']);

        $response = $this->controller->runAction('purchasables-table');

        $this->assertInstanceOf(Response::class, $response);

        $purchasable = array_pop($response->data['data']);

        $this->assertEquals('ANT-001', $purchasable['sku']);
    }
}