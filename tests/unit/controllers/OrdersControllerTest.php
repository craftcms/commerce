<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\controllers;

use Codeception\Test\Unit;
use Craft;
use craft\commerce\base\ShippingMethodInterface;
use craft\commerce\base\ShippingRuleInterface;
use craft\commerce\controllers\OrdersController;
use craft\commerce\elements\Order;
use craft\commerce\events\RegisterAvailableShippingMethodsEvent;
use craft\commerce\Plugin;
use craft\commerce\services\ShippingMethods;
use craft\helpers\Json;
use craft\web\Request;
use craftcommercetests\fixtures\OrdersFixture;
use Illuminate\Support\Collection;
use UnitTester;
use yii\base\Event;
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
    protected UnitTester $tester;

    /**
     * @var  OrdersController
     */
    protected OrdersController $controller;

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
            'orders' => [
                'class' => OrdersFixture::class,
            ],
        ];
    }

    /**
     * @inheritDoc
     */
    protected function _before(): void
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

    public function testPurchasablesTable(): void
    {
        $this->request->getHeaders()->set('Accept', 'application/json');
        Craft::$app->getRequest()->setQueryParams(['siteId' => Craft::$app->getSites()->getPrimarySite()->id]);

        $response = $this->controller->runAction('purchasables-table');

        self::assertInstanceOf(Response::class, $response);

        self::assertArrayHasKey('pagination', $response->data);
        self::assertArrayHasKey('data', $response->data);

        self::assertSame(3, $response->data['pagination']['total']);
        self::assertCount(3, $response->data['data']);

        $purchasable = array_pop($response->data['data']);

        $keys = ['id', 'price', 'priceAsCurrency', 'description', 'sku', 'priceAsCurrency', 'isAvailable', 'detail'];
        foreach ($keys as $key) {
            self::assertArrayHasKey($key, $purchasable);
        }

        self::assertEquals('hct-blue', $purchasable['sku']);
    }

    public function testPurchasablesTableSort(): void
    {
        $this->request->getHeaders()->set('Accept', 'application/json');

        Craft::$app->getRequest()->setQueryParams([
            'sort' => 'sku|desc',
            'siteId' => Craft::$app->getSites()->getPrimarySite()->id,
        ]);

        $response = $this->controller->runAction('purchasables-table');

        self::assertInstanceOf(Response::class, $response);

        $purchasable = array_pop($response->data['data']);

        self::assertEquals('hct-blue', $purchasable['sku']);
    }

    public function testCustomerSearch(): void
    {
        $this->request->getHeaders()->set('Accept', 'application/json');

        Craft::$app->getRequest()->setQueryParams(['query' => 'customer1']);
        $response = $this->controller->runAction('customer-search');

        self::assertEquals(200, $response->statusCode);
        self::assertIsArray($response->data);
        self::assertCount(1, $response->data);
        $customer = $response->data['customers'][0] ?? [];
        $keys = [
            'cpEditUrl',
            'email',
            'id',
            'photo',
            'status',
            'totalAddresses',
        ];

        foreach ($keys as $key) {
            self::assertArrayHasKey($key, $customer);
        }

        self::assertEquals('customer1@crafttest.com', $customer['email']);
    }

    public function testGetIndexSourcesBadgeCounts(): void
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

    public function testGetShippingMethodOptionsReturnsOptions(): void
    {
        $ordersFixture = $this->tester->grabFixture('orders');
        $order = $ordersFixture->getElement('completed-new');

        $this->request->getHeaders()->set('Accept', 'application/json');
        $this->request->getHeaders()->set('X-Http-Method-Override', 'POST');
        $this->request->setRawBody(Json::encode($this->_buildOrderPayload($order)));

        $response = $this->controller->runAction('get-shipping-method-options');

        self::assertEquals(200, $response->statusCode);
        self::assertArrayHasKey('shippingMethodOptions', $response->data);
        self::assertNotEmpty($response->data['shippingMethodOptions']);

        $option = reset($response->data['shippingMethodOptions']);
        self::assertArrayHasKey('handle', $option);
        self::assertArrayHasKey('name', $option);
        self::assertArrayHasKey('matchesOrder', $option);
    }

    public function testGetShippingMethodOptionsInvalidOrderId(): void
    {
        $this->request->getHeaders()->set('Accept', 'application/json');
        $this->request->getHeaders()->set('X-Http-Method-Override', 'POST');
        $this->request->setRawBody(Json::encode(['order' => ['id' => 1]]));

        $response = $this->controller->runAction('get-shipping-method-options');

        self::assertEquals(400, $response->statusCode);
    }

    public function testGetShippingMethodOptionsIncludesCustomRuntimeMethod(): void
    {
        $ordersFixture = $this->tester->grabFixture('orders');
        $order = $ordersFixture->getElement('completed-new');

        $customMethod = new class() implements ShippingMethodInterface {
            public function getType(): string
            {
                return 'Custom';
            }
            public function getId(): ?int
            {
                return null;
            }
            public function getName(): string
            {
                return 'My Custom Carrier';
            }
            public function getHandle(): string
            {
                return 'myCustomCarrier';
            }
            public function getCpEditUrl(): string
            {
                return '';
            }
            public function getShippingRules(): Collection
            {
                return collect();
            }
            public function getIsEnabled(): bool
            {
                return true;
            }
            public function getPriceForOrder(Order $order): float
            {
                return 0.0;
            }
            public function getMatchingShippingRule(Order $order): ?ShippingRuleInterface
            {
                return null;
            }
            public function matchOrder(Order $order): bool
            {
                return true;
            }
        };

        Event::on(
            ShippingMethods::class,
            ShippingMethods::EVENT_REGISTER_AVAILABLE_SHIPPING_METHODS,
            $listener = function(RegisterAvailableShippingMethodsEvent $e) use ($customMethod) {
                $e->setShippingMethods($e->getShippingMethods()->push($customMethod));
            }
        );

        $this->request->getHeaders()->set('Accept', 'application/json');
        $this->request->getHeaders()->set('X-Http-Method-Override', 'POST');
        $this->request->setRawBody(Json::encode($this->_buildOrderPayload($order)));

        $response = $this->controller->runAction('get-shipping-method-options');

        Event::off(ShippingMethods::class, ShippingMethods::EVENT_REGISTER_AVAILABLE_SHIPPING_METHODS, $listener);

        self::assertEquals(200, $response->statusCode);

        $options = $response->data['shippingMethodOptions'];
        self::assertArrayHasKey('myCustomCarrier', $options);
        self::assertEquals('My Custom Carrier', $options['myCustomCarrier']['name']);
        self::assertEquals('myCustomCarrier', $options['myCustomCarrier']['handle']);
    }

    private function _buildOrderPayload(Order $order, array $overrides = []): array
    {
        return [
            'order' => array_merge([
                'id' => $order->id,
                'recalculationMode' => Order::RECALCULATION_MODE_ALL,
                'reference' => $order->reference,
                'customerId' => $order->getCustomerId(),
                'couponCode' => $order->couponCode,
                'isCompleted' => $order->isCompleted,
                'orderStatusId' => $order->orderStatusId,
                'orderSiteId' => $order->orderSiteId,
                'message' => $order->message,
                'shippingMethodHandle' => $order->shippingMethodHandle,
                'shippingMethodName' => $order->shippingMethodName,
                'notices' => [],
                'dateOrdered' => null,
                'lineItems' => [],
                'orderAdjustments' => [],
            ], $overrides),
        ];
    }
}
