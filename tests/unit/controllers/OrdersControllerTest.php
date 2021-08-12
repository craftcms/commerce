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
use craftcommercetests\fixtures\OrdersFixture;
use UnitTester;
use yii\web\Response;

/**
 * OrdersControllerTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.2.14
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
            'orders' => [
                'class' => OrdersFixture::class,
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

    public function testPurchasablesTable()
    {
        $this->request->getHeaders()->set('Accept', 'application/json');

        $response = $this->controller->runAction('purchasables-table');

        self::assertInstanceOf(Response::class, $response);

        self::assertArrayHasKey('pagination', $response->data);
        self::assertArrayHasKey('data', $response->data);

        self::assertSame(10, $response->data['pagination']['total']);
        self::assertCount(10, $response->data['data']);

        $purchasable = array_pop($response->data['data']);

        $keys = ['id', 'price', 'description', 'sku', 'priceAsCurrency', 'isAvailable', 'detail'];
        foreach ($keys as $key) {
            self::assertArrayHasKey($key, $purchasable);
        }

        self::assertEquals('hct-blue', $purchasable['sku']);
    }

    public function testPurchasablesTableSort()
    {
        $this->request->getHeaders()->set('Accept', 'application/json');

        Craft::$app->getRequest()->setQueryParams(['sort' => 'sku|desc']);

        $response = $this->controller->runAction('purchasables-table');

        self::assertInstanceOf(Response::class, $response);

        $purchasable = array_pop($response->data['data']);

        self::assertEquals('ANT-001', $purchasable['sku']);
    }

    public function testCustomerSearch()
    {
        $this->request->getHeaders()->set('Accept', 'application/json');

        $response = $this->controller->runAction('customer-search', ['query' => 'support']);

        self::assertEquals(200, $response->statusCode);
        self::assertIsArray($response->data);
        self::assertCount(1, $response->data);
        $customer = $response->data[0] ?? [];
        $keys = [
            'id',
            'userId',
            'email',
            'primaryBillingAddressId',
            'billingFirstName',
            'billingLastName',
            'billingFullName',
            'billingAddress',
            'shippingFirstName',
            'shippingLastName',
            'shippingFullName',
            'shippingAddress',
            'primaryShippingAddressId',
            'user',
            'photo',
            'url',
        ];

        foreach ($keys as $key) {
            self::assertArrayHasKey($key, $customer);
        }
        // self::assertArrayHasKey('');
        self::assertEquals('support@craftcms.com', $customer['email']);
    }

    public function testGetIndexSourcesBadgeCounts()
    {
        $this->request->getHeaders()->set('Accept', 'application/json');

        $response = $this->controller->runAction('get-index-sources-badge-counts');

        self::assertEquals(200, $response->statusCode);
        self::assertIsArray($response->data);
        self::assertArrayHasKey('counts', $response->data);
        self::assertArrayHasKey('total', $response->data);
        self::assertCount(4, $response->data['counts']);

        $keys = ['orderStatusId', 'handle', 'orderCount'];
        foreach ($keys as $key) {
            self::assertArrayHasKey($key, array_shift($response->data['counts']));
        }
    }
}