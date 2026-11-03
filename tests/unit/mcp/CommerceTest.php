<?php

namespace craftcommercetests\unit\mcp;

use Craft;
use craft\commerce\db\Table;
use craft\commerce\elements\Order;
use craft\commerce\elements\Product;
use craft\commerce\elements\Transfer;
use craft\commerce\elements\Variant;
use craft\commerce\management\Access;
use craft\commerce\management\Reports;
use craft\commerce\management\Resources;
use craft\commerce\management\ResourceType;
use craft\commerce\models\InventoryLocation;
use craft\commerce\Plugin;
use craft\db\Query;
use craft\elements\User;
use craft\enums\CmsEdition;
use craft\mcp\HttpTransportMiddleware;
use craft\mcp\serializers\ElementSerializer;
use craft\mcp\ServerFactory;
use craft\services\Mcp;
use craft\test\TestCase;
use craftcommercetests\fixtures\CustomerFixture;
use craftcommercetests\fixtures\OrdersFixture;
use DateInterval;
use GuzzleHttp\Psr7\ServerRequest;
use League\OAuth2\Server\Exception\OAuthServerException;
use Mcp\Server\Transport\StreamableHttpTransport;
use yii\web\ForbiddenHttpException;

/** Exercises Commerce tools through Craft's real MCP server and HTTP transport, with database fixtures. */
class CommerceTest extends TestCase
{
    private User $actor;
    private bool $allowAdminChanges;

    public function _fixtures(): array
    {
        // Promotion scheduling needs no orders or extra stores; legacy sales require a single store.
        if ($this->name() === 'testPromotionSchedulingDates') {
            return ['customers' => ['class' => CustomerFixture::class]];
        }
        return ['orders' => ['class' => OrdersFixture::class]];
    }

    protected function _before(): void
    {
        Craft::$app->edition = CmsEdition::Pro;
        $this->allowAdminChanges = Craft::$app->getConfig()->getGeneral()->allowAdminChanges;
        Craft::$app->getConfig()->getGeneral()->allowAdminChanges = true;
        $this->actor = Craft::$app->getUsers()->getUserByUsernameOrEmail('customer2@crafttest.com');
        $this->actor->admin = true;
        self::assertTrue(Craft::$app->getElements()->saveElement($this->actor, false));
        Craft::$app->getUser()->setIdentity($this->actor);
        Craft::$app->get('mcp')->tokenScopes = [Mcp::SCOPE_ADMIN];
    }

    protected function _after(): void
    {
        Craft::$app->getConfig()->getGeneral()->allowAdminChanges = $this->allowAdminChanges;
        Craft::$app->get('mcp')->tokenScopes = null;
        Craft::$app->getUser()->setIdentity(null);
    }

    public function testDiscoversAndManagesResourcesWithoutSecrets(): void
    {
        foreach (['products', 'variants', 'orders', 'transfers'] as $type) {
            self::assertSame($type, $this->ok('elements.schema', ['type' => $type])['type']);
        }
        $storeId = Plugin::getInstance()->getStores()->getPrimaryStore()->id;
        foreach (['resources', 'configuration'] as $category) {
            foreach ($this->ok("commerce.$category.schema")['types'] as $type) {
                $items = $this->ok("commerce.$category.list", ['type' => $type['type'], 'storeId' => $storeId])['items'];
                if ($type['type'] === 'gateways') {
                    foreach ($items as $gateway) {
                        self::assertArrayNotHasKey('settings', $gateway);
                    }
                }
            }
        }
        $item = $this->ok('commerce.resources.create', ['type' => 'shippingMethods', 'attributes' => [
            'storeId' => $storeId, 'name' => 'MCP express', 'handle' => 'mcpExpress',
        ]])['item'];
        $updated = $this->ok('commerce.resources.update', ['type' => 'shippingMethods', 'storeId' => $storeId,
            'identifier' => ['id' => $item['id']], 'attributes' => ['name' => 'MCP next day'],
        ])['item'];
        self::assertSame('MCP next day', $updated['name']);
        self::assertSame('mcpExpress', $updated['handle']);
        $this->denied('commerce.resources.update', ['type' => 'shippingMethods', 'storeId' => $storeId,
            'identifier' => ['id' => $item['id']], 'attributes' => ['storeId' => $storeId + 1],
        ]);
        $this->denied('commerce.resources.get', ['type' => 'shippingMethods', 'storeId' => $storeId,
            'identifier' => ['id' => $item['id'], 'handle' => 'mcpExpress'],
        ]);
        self::assertTrue($this->ok('commerce.resources.delete', ['type' => 'shippingMethods', 'storeId' => $storeId, 'identifier' => ['id' => $item['id']]])['deleted']);

        $order = Order::find()->status(null)->one();
        $data = $this->ok('elements.get', ['type' => 'orders', 'id' => $order->id])['element'];
        self::assertSame($order->id, $data['id']);
        foreach (['number', 'paymentSourceId', 'returnUrl', 'cancelUrl', 'transactions'] as $key) {
            self::assertArrayNotHasKey($key, $data);
        }
        // Generic serialization is used by search and lifecycle tools, not just our adapters.
        $serializer = Craft::$app->get('mcp')->getCapabilityContainer()->get(ElementSerializer::class);
        self::assertArrayNotHasKey('number', $serializer->serialize($order));
        self::assertSame([], $serializer->serialize($order, public: true));
    }

    public function testScopesPermissionsAndAdminChangesAreEnforced(): void
    {
        Craft::$app->get('mcp')->tokenScopes = [Mcp::SCOPE_READ];
        $this->ok('commerce.resources.schema');
        $this->denied('commerce.resources.create', ['type' => 'taxCategories', 'attributes' => ['name' => 'Forbidden', 'handle' => 'forbidden']]);
        Craft::$app->get('mcp')->tokenScopes = [Mcp::SCOPE_WRITE];
        $this->denied('commerce.configuration.schema');
        Craft::$app->get('mcp')->tokenScopes = [Mcp::SCOPE_ADMIN];
        Craft::$app->getConfig()->getGeneral()->allowAdminChanges = false;
        $this->denied('commerce.configuration.create', ['type' => 'stores', 'attributes' => ['name' => 'Forbidden', 'handle' => 'forbidden']]);
        try {
            (new Resources(new Access()))->save($this->actor, ResourceType::Stores, true, ['name' => 'Forbidden', 'handle' => 'forbidden']);
            self::fail('The Commerce service must enforce allowAdminChanges without MCP.');
        } catch (ForbiddenHttpException) {
            self::assertTrue(true);
        }

        $store = Plugin::getInstance()->getStores()->getPrimaryStore();
        $order = Order::find()->status(null)->one();
        $otherStore = Plugin::getInstance()->getStores()->getStoreByHandle('ukStore');
        $otherOrder = $this->ok('elements.create', ['type' => 'orders', 'attributes' => ['storeId' => $otherStore->id]])['element'];
        $site = Craft::$app->getSites()->getPrimarySite();
        $this->asUser('customer3@crafttest.com', ['accessCp', 'accessPlugin-commerce', "editSite:$site->uid", 'commerce-manageOrders']);
        $this->ok('commerce.orders.related', ['orderId' => $order->id, 'relation' => 'lineItems']);
        $this->denied('commerce.orders.recalculate', ['orderId' => $order->id]);
        $this->denied('elements.update', ['type' => 'orders', 'id' => $order->id, 'attributes' => ['message' => 'Forbidden']]);
        $this->denied('commerce.reports.get', ['storeId' => $store->id, 'type' => 'topCustomers']);
        $this->ok('commerce.reports.get', ['storeId' => $store->id, 'type' => 'totalOrders']);
        $this->denied('elements.get', ['type' => 'orders', 'id' => $otherOrder['id']]);
        $this->denied('commerce.reports.get', ['storeId' => $otherStore->id, 'type' => 'totalOrders']);
        $this->asUser('customer3@crafttest.com', ['accessCp', "editSite:$site->uid", 'commerce-manageOrders']);
        $this->denied('elements.get', ['type' => 'orders', 'id' => $order->id]);
    }

    public function testSignedReadTokenCannotMutateCommerce(): void
    {
        $config = Craft::$app->getConfig()->getOAuth();
        $privateKey = $config->privateKey;
        $publicKey = $config->publicKey;
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $privatePem);
        $config->privateKey = $privatePem;
        $config->publicKey = openssl_pkey_get_details($key)['key'];
        try {
            $oauth = Craft::$app->getOAuth();
            $token = $oauth->createPersonalAccessToken($this->actor, 'Commerce MCP test', [Mcp::SCOPE_READ], new DateInterval('PT5M'));
            Craft::$app->getUser()->setIdentity(null);
            Craft::$app->get('mcp')->tokenScopes = null;
            $request = new ServerRequest('POST', 'http://localhost/mcp/auth', ['Authorization' => 'Bearer ' . $token]);
            $user = $oauth->authenticate($request, 'mcp', scopes: $scopes);
            self::assertSame($this->actor->id, $user->id);
            self::assertSame([Mcp::SCOPE_READ], $scopes);
            Craft::$app->getUser()->setIdentity($user);
            Craft::$app->get('mcp')->tokenScopes = $scopes;
            $this->ok('commerce.resources.schema');
            $this->denied('commerce.resources.create', ['type' => 'taxCategories', 'attributes' => ['name' => 'Forbidden', 'handle' => 'forbidden']]);
            try {
                $oauth->authenticate($request->withHeader('Authorization', 'Bearer invalid-token'), 'mcp');
                self::fail('Invalid bearer tokens must not authenticate.');
            } catch (OAuthServerException) {
                self::assertTrue(true);
            }
        } finally {
            $config->privateKey = $privateKey;
            $config->publicKey = $publicKey;
        }
    }

    public function testCreatesProductsVariantsAndCarts(): void
    {
        $type = Plugin::getInstance()->getProductTypes()->getProductTypeByHandle('tShirts');
        $product = $this->ok('elements.create', ['type' => 'products', 'attributes' => [
            'typeId' => $type->id, 'title' => 'MCP shirt', 'defaultVariant' => ['sku' => 'mcp-shirt', 'basePrice' => 17.35],
            'postDate' => '2020-02-03T04:05:06+02:00', 'expiryDate' => '2035-07-08T09:10:11-04:00',
        ]])['element'];
        $scheduled = Product::find()->id($product['id'])->status(null)->one();
        self::assertSame('2020-02-03T02:05:06+00:00', $scheduled->postDate->setTimezone(new \DateTimeZone('UTC'))->format(DATE_ATOM));
        self::assertSame('2035-07-08T13:10:11+00:00', $scheduled->expiryDate->setTimezone(new \DateTimeZone('UTC'))->format(DATE_ATOM));
        $this->ok('elements.update', ['type' => 'products', 'id' => $product['id'], 'attributes' => ['expiryDate' => null]]);
        $scheduled = Product::find()->id($product['id'])->status(null)->one();
        self::assertNull($scheduled->expiryDate);
        self::assertSame('2020-02-03T02:05:06+00:00', $scheduled->postDate->setTimezone(new \DateTimeZone('UTC'))->format(DATE_ATOM));
        $variants = $this->ok('elements.list', ['type' => 'variants', 'criteria' => ['productId' => $product['id']]])['elements'];
        self::assertCount(1, $variants);
        self::assertSame('mcp-shirt', $variants[0]['sku']);
        self::assertEqualsWithDelta(17.35, $variants[0]['basePrice'], 0.000001);
        $variant = $this->ok('elements.create', ['type' => 'variants', 'attributes' => ['productId' => $product['id'], 'sku' => 'mcp-second', 'basePrice' => 23.75]])['element'];
        self::assertSame($product['id'], $variant['productId']);
        $cart = $this->ok('elements.create', ['type' => 'orders', 'attributes' => ['storeId' => Plugin::getInstance()->getStores()->getPrimaryStore()->id]])['element'];
        self::assertFalse($cart['isCompleted']);
        $this->ok('commerce.orders.set-line-item', ['orderId' => $cart['id'], 'purchasableId' => $variant['id'], 'quantity' => 3]);
        $items = $this->ok('commerce.orders.related', ['orderId' => $cart['id'], 'relation' => 'lineItems'])['items'];
        self::assertCount(1, $items);
        self::assertSame(3, $items[0]['qty']);
        self::assertSame('mcp-second', $items[0]['sku']);
        self::assertEqualsWithDelta(71.25, $items[0]['total'], 0.000001);
        $this->ok('commerce.orders.set-line-item', ['orderId' => $cart['id'], 'lineItemId' => $items[0]['id'], 'quantity' => 0]);
        self::assertSame([], $this->ok('commerce.orders.related', ['orderId' => $cart['id'], 'relation' => 'lineItems'])['items']);
        $this->denied('elements.update', ['type' => 'orders', 'id' => $cart['id'], 'attributes' => ['totalPaid' => 999]]);
    }

    public function testInventoryUsesLedgerAndRejectsSystemBuckets(): void
    {
        $location = Plugin::getInstance()->getInventoryLocations()->getAllInventoryLocations()->first();
        $item = Variant::find()->sku('hct-blue')->one()->inventoryItemId;
        $arguments = ['locationId' => $location->id, 'itemId' => $item];
        $this->ok('commerce.inventory.update', [...$arguments, 'type' => 'available', 'action' => 'set', 'quantity' => 20]);
        $this->ok('commerce.inventory.move', [...$arguments, 'from' => 'available', 'to' => 'reserved', 'quantity' => 2, 'note' => 'MCP hold']);
        self::assertSame(18, (int)(new Query())->from(Table::INVENTORYTRANSACTIONS)->where(['inventoryLocationId' => $location->id, 'inventoryItemId' => $item, 'type' => 'available'])->sum('quantity'));
        self::assertSame(2, (int)(new Query())->from(Table::INVENTORYTRANSACTIONS)->where(['inventoryLocationId' => $location->id, 'inventoryItemId' => $item, 'type' => 'reserved'])->sum('quantity'));
        $history = $this->ok('commerce.inventory.history', [...$arguments, 'limit' => 1]);
        self::assertSame(1, $history['count']);
        self::assertSame(1, $history['nextOffset']);
        self::assertSame('MCP hold', $history['transactions'][0]['note']);
        self::assertSame(1, $this->ok('commerce.inventory.list', $arguments)['count']);
        $this->denied('commerce.inventory.update', [...$arguments, 'type' => 'committed', 'action' => 'set', 'quantity' => 99]);
        $this->denied('commerce.inventory.move', [...$arguments, 'from' => 'available', 'to' => 'incoming', 'quantity' => 1]);
        $this->denied('commerce.inventory.history', [...$arguments, 'limit' => 0]);
    }

    public function testStoreSettingsTopologyAndReports(): void
    {
        $store = Plugin::getInstance()->getStores()->getPrimaryStore();
        $this->ok('commerce.stores.update-settings', ['storeId' => $store->id, 'attributes' => ['countries' => ['AU', 'US']]]);
        self::assertSame(['AU', 'US'], $this->ok('commerce.stores.settings', ['storeId' => $store->id])['countries']);
        $this->denied('commerce.stores.update-settings', ['storeId' => $store->id, 'attributes' => ['countries' => ['usa']]]);
        $siteId = Craft::$app->getSites()->getPrimarySite()->id;
        self::assertEqualsCanonicalizing([$siteId, 1000], $this->ok('commerce.stores.topology', ['storeId' => $store->id])['siteIds']);
        self::assertEqualsCanonicalizing([$siteId, 1000], $this->ok('commerce.stores.assign-site', ['storeId' => $store->id, 'siteId' => $siteId])['siteIds']);
        $locationId = Plugin::getInstance()->getInventoryLocations()->getAllInventoryLocations()->first()->id;
        self::assertSame([$locationId], $this->ok('commerce.stores.set-inventory-locations', ['storeId' => $store->id, 'ids' => [$locationId]])['inventoryLocationIds']);
        $completed = Order::find()->isCompleted()->storeId($store->id)->all();
        self::assertNotEmpty($completed);
        foreach (array_keys(Reports::TYPES) as $type) {
            $report = $this->ok('commerce.reports.get', ['storeId' => $store->id, 'type' => $type, 'dateRange' => 'all']);
            self::assertSame($type, $report['type']);
            if ($type === 'totalOrders') {
                self::assertSame(count($completed), (int)$report['data']['total']);
            }
            if ($type === 'totalRevenue') {
                self::assertEqualsWithDelta(array_sum(array_map(fn(Order $order) => $order->getTotalPrice(), $completed)), array_sum(array_column($report['data'], 'revenue')), 0.000001);
            }
        }
    }

    public function testDiscountCouponsAndCartAddresses(): void
    {
        $storeId = Plugin::getInstance()->getStores()->getPrimaryStore()->id;
        $discount = $this->ok('commerce.resources.create', ['type' => 'discounts', 'attributes' => [
            'storeId' => $storeId, 'name' => 'MCP five off', 'enabled' => true, 'allPurchasables' => true, 'allCategories' => true,
            'requireCouponCode' => true, 'baseDiscount' => -5, 'coupons' => [['code' => 'MCPFIVE'], ['code' => 'MCPOLD']],
        ]])['item'];
        self::assertCount(2, $discount['coupons']);
        $coupon = collect($discount['coupons'])->firstWhere('code', 'MCPFIVE');
        $updated = $this->ok('commerce.resources.update', ['type' => 'discounts', 'storeId' => $storeId, 'identifier' => ['id' => $discount['id']],
            'attributes' => ['coupons' => [['id' => $coupon['id'], 'code' => 'MCPFIVE', 'maxUses' => 10]]],
        ])['item'];
        self::assertCount(1, $updated['coupons']);
        self::assertSame($coupon['id'], $updated['coupons'][0]['id']);
        self::assertSame(10, $updated['coupons'][0]['maxUses']);
        $variant = Variant::find()->sku('hct-blue')->one();
        $cart = $this->ok('elements.create', ['type' => 'orders', 'attributes' => ['storeId' => $storeId]])['element'];
        $this->ok('commerce.orders.set-line-item', ['orderId' => $cart['id'], 'purchasableId' => $variant->id, 'quantity' => 2]);
        $this->ok('elements.update', ['type' => 'orders', 'id' => $cart['id'], 'attributes' => [
            'email' => 'mcp-buyer@crafttest.com', 'couponCode' => 'MCPFIVE',
            'shippingAddress' => ['firstName' => 'Ada', 'lastName' => 'Lovelace', 'countryCode' => 'US', 'administrativeArea' => 'NY', 'locality' => 'New York', 'postalCode' => '10001', 'addressLine1' => '1 Main St'],
        ]]);
        $saved = Order::find()->id($cart['id'])->status(null)->one();
        self::assertSame('mcp-buyer@crafttest.com', $saved->getEmail());
        self::assertSame('MCPFIVE', $saved->couponCode);
        self::assertSame('1 Main St', $saved->getShippingAddress()->addressLine1);
        self::assertSame('US', $saved->getShippingAddress()->countryCode);
        self::assertEqualsWithDelta(-5, $saved->getTotalDiscount(), 0.000001);
        $this->denied('elements.update', ['type' => 'orders', 'id' => $cart['id'], 'attributes' => ['shippingAddress' => null]]);
    }

    public function testPaymentDataIsFilteredAndInvalidWritesAreRejected(): void
    {
        $order = Order::find()->isCompleted()->one();
        $transactions = Plugin::getInstance()->getTransactions();
        $payment = $transactions->createTransaction($order, typeOverride: 'purchase');
        $payment->status = 'success';
        $payment->reference = 'private-reference';
        $payment->response = ['private-token' => 'must-not-return'];
        self::assertTrue($transactions->saveTransaction($payment));
        foreach (['transactions', 'history', 'adjustments', 'fulfillment'] as $relation) {
            $data = $this->ok('commerce.orders.related', ['orderId' => $order->id, 'relation' => $relation]);
            self::assertArrayHasKey('items', $data);
            if ($relation === 'transactions') {
                self::assertNotEmpty($data['items']);
                foreach ($data['items'] as $transaction) {
                    foreach (['response', 'reference', 'hash', 'code', 'message'] as $secret) {
                        self::assertArrayNotHasKey($secret, $transaction);
                    }
                }
                $id = $data['items'][0]['id'];
                $this->denied('commerce.payments.refund', ['transactionId' => $id, 'amount' => -1]);
                $this->denied('commerce.payments.refund', ['transactionId' => $id, 'amount' => 99999999]);
            }
        }
        $this->denied('commerce.payments.capture', ['transactionId' => 999999]);
        $this->denied('commerce.payments.refund', ['transactionId' => $payment->id, 'amount' => 0.001]);
        self::assertEqualsWithDelta($payment->paymentAmount, $transactions->getTransactionById($payment->id)->getRefundableAmount(), 0.000001);
        $status = Plugin::getInstance()->getOrderStatuses()->getAllOrderStatuses($order->storeId)->last();
        self::assertSame($status->id, $this->ok('commerce.orders.set-status', ['orderId' => $order->id, 'statusId' => $status->id])['order']['orderStatusId']);
        $this->ok('commerce.orders.recalculate', ['orderId' => $order->id]);
        // The fixture uses Commerce's local Dummy gateway. No external payment service is called.
        $refunded = $this->ok('commerce.payments.refund', ['transactionId' => $payment->id, 'amount' => 1.25])['transaction'];
        self::assertSame('success', $refunded['status']);
        self::assertSame('refund', $refunded['type']);
        self::assertEqualsWithDelta(1.25, $refunded['paymentAmount'], 0.000001);
        self::assertArrayNotHasKey('reference', $refunded);
        $remaining = $this->ok('commerce.payments.refund', ['transactionId' => $payment->id])['transaction'];
        self::assertSame('success', $remaining['status']);
        self::assertEqualsWithDelta($payment->paymentAmount - 1.25, $remaining['paymentAmount'], 0.000001);
        $this->denied('commerce.payments.refund', ['transactionId' => $payment->id, 'amount' => 1]);
        $authorization = $transactions->createTransaction($order, typeOverride: 'authorize');
        $authorization->status = 'success';
        self::assertTrue($transactions->saveTransaction($authorization));
        $captured = $this->ok('commerce.payments.capture', ['transactionId' => $authorization->id])['transaction'];
        // Dummy's capture returns a failed response; MCP must preserve that status, not claim success.
        self::assertSame('failed', $captured['status']);
        self::assertSame('capture', $captured['type']);
        self::assertSame($authorization->id, $captured['parentId']);
        self::assertArrayNotHasKey('response', $captured);
        $this->denied('commerce.payments.capture', ['transactionId' => $payment->id]);
    }

    /** @dataProvider paymentOperations */
    public function testPaymentCannotOverwriteAnInterleavedOrderEdit(string $operation, string $type, string $event): void
    {
        $order = Order::find()->isCompleted()->one();
        $order->message = 'Original message';
        self::assertTrue(Craft::$app->getElements()->saveElement($order));
        $transactions = Plugin::getInstance()->getTransactions();
        $payment = $transactions->createTransaction($order, typeOverride: $type);
        $payment->status = 'success';
        self::assertTrue($transactions->saveTransaction($payment));
        $competing = null;
        $listener = function() use ($order, &$competing): void {
            // Run another real MCP operation between the gateway response and payment bookkeeping.
            $competing = $this->call('elements.update', ['type' => 'orders', 'id' => $order->id, 'attributes' => ['message' => 'Interleaved edit']]);
        };
        $payments = Plugin::getInstance()->getPayments();
        $payments->on($event, $listener);
        try {
            $result = $this->ok("commerce.payments.$operation", ['transactionId' => $payment->id]);
            self::assertSame($order->id, $result['transaction']['orderId']);
        } finally {
            $payments->off($event, $listener);
        }
        self::assertNotNull($competing);
        $rejected = isset($competing['error']) || ($competing['result']['isError'] ?? false);
        // Either serialize/reject the competing write or preserve it. Never lose a successful edit.
        self::assertSame($rejected ? 'Original message' : 'Interleaved edit', Order::find()->id($order->id)->status(null)->one()->message);
    }

    public static function paymentOperations(): array
    {
        return [
            ['capture', 'authorize', \craft\commerce\services\Payments::EVENT_AFTER_CAPTURE_TRANSACTION],
            ['refund', 'purchase', \craft\commerce\services\Payments::EVENT_AFTER_REFUND_TRANSACTION],
        ];
    }

    public function testTransfersValidateBeforeDispatchAndMoveInventoryExactlyOnce(): void
    {
        $locations = Plugin::getInstance()->getInventoryLocations();
        $origin = $locations->getAllInventoryLocations()->first();
        $destination = new InventoryLocation(['name' => 'MCP destination', 'handle' => 'mcpDestination']);
        self::assertTrue($locations->saveInventoryLocation($destination));
        $item = Variant::find()->sku('hct-blue')->one()->inventoryItemId;
        $base = ['originLocationId' => $origin->id, 'destinationLocationId' => $destination->id];
        foreach ([0, -1, 100000] as $quantity) {
            $this->denied('commerce.transfers.save', [...$base, 'items' => [['inventoryItemId' => $item, 'quantity' => $quantity]]]);
        }
        foreach ([1, 99999] as $quantity) {
            $boundary = $this->ok('commerce.transfers.save', [...$base, 'items' => [['inventoryItemId' => $item, 'quantity' => $quantity]]])['transfer'];
            self::assertSame($quantity, $this->ok('commerce.transfers.details', ['id' => $boundary['id']])['items'][0]['quantity']);
            self::assertSame(0, $this->ledgerCount($boundary['id']));
        }
        $transfer = $this->ok('commerce.transfers.save', [...$base, 'items' => [['inventoryItemId' => $item, 'quantity' => 3]]])['transfer'];
        self::assertSame('draft', $transfer['transferStatus']);
        self::assertSame(0, $this->ledgerCount($transfer['id']));
        $detail = Transfer::find()->id($transfer['id'])->status(null)->one()->getDetails()[0];
        foreach ([0, 100000] as $quantity) {
            Craft::$app->getDb()->createCommand()->update(Table::TRANSFERDETAILS, ['quantity' => $quantity], ['id' => $detail->id])->execute();
            $this->denied('commerce.transfers.dispatch', ['id' => $transfer['id']]);
            self::assertSame(0, $this->ledgerCount($transfer['id']));
            self::assertTrue(Transfer::find()->id($transfer['id'])->status(null)->one()->isTransferDraft());
        }
        Craft::$app->getDb()->createCommand()->update(Table::TRANSFERDETAILS, ['quantity' => 3], ['id' => $detail->id])->execute();
        $this->denied('commerce.transfers.save', [...$base, 'id' => $transfer['id'], 'items' => [['inventoryItemId' => $item, 'quantity' => -1]]]);
        self::assertSame('pending', $this->ok('commerce.transfers.dispatch', ['id' => $transfer['id']])['transfer']['transferStatus']);
        $count = $this->ledgerCount($transfer['id']);
        self::assertGreaterThan(0, $count);
        self::assertSame(3, $this->ledgerSum($transfer['id'], $destination->id, 'incoming'));
        self::assertSame(-3, $this->ledgerSum($transfer['id'], $origin->id, 'available'));
        $this->denied('commerce.transfers.dispatch', ['id' => $transfer['id']]);
        self::assertSame($count, $this->ledgerCount($transfer['id']));
        $uid = $this->ok('commerce.transfers.details', ['id' => $transfer['id']])['items'][0]['uid'];
        $this->denied('commerce.transfers.receive', ['id' => $transfer['id'], 'quantities' => [$uid => ['accept' => 4]]]);
        self::assertSame($count, $this->ledgerCount($transfer['id']));
        $this->ok('commerce.transfers.receive', ['id' => $transfer['id'], 'quantities' => [$uid => ['accept' => 1, 'reject' => 1]]]);
        self::assertSame(1, $this->ledgerSum($transfer['id'], $destination->id, 'incoming'));
        self::assertSame(1, $this->ledgerSum($transfer['id'], $destination->id, 'available'));
        $this->denied('commerce.transfers.receive', ['id' => $transfer['id'], 'quantities' => [$uid => ['accept' => 2]]]);
        $this->ok('commerce.transfers.receive', ['id' => $transfer['id'], 'quantities' => [$uid => ['accept' => 1]]]);
        self::assertSame(0, $this->ledgerSum($transfer['id'], $destination->id, 'incoming'));
        self::assertSame(2, $this->ledgerSum($transfer['id'], $destination->id, 'available'));
    }

    public function testPeerProvisionalDraftsCannotBeReadEditedOrPublished(): void
    {
        $product = Product::find()->type('tShirts')->one();
        $owner = Craft::$app->getUsers()->getUserByUsernameOrEmail('customer1@crafttest.com');
        $draft = Craft::$app->getDrafts()->createDraft($product, $owner->id, 'Private draft', provisional: true);
        $saved = Craft::$app->getDrafts()->createDraft($product, $owner->id, 'Shared saved draft');
        $site = Craft::$app->getSites()->getPrimarySite();
        $type = $product->getType();
        $permissions = ['accessCp', 'accessPlugin-commerce', "editSite:$site->uid", "commerce-viewProductType:$type->uid", "commerce-saveProductType:$type->uid"];
        $this->asUser('customer3@crafttest.com', $permissions);
        $listed = $this->ok('elements.list', ['type' => 'products', 'criteria' => ['drafts' => true, 'provisionalDrafts' => null, 'status' => null]])['elements'];
        self::assertNotContains($draft->id, array_column($listed, 'id'));
        self::assertContains($saved->id, array_column($listed, 'id'));
        $genericDrafts = $this->ok('drafts.list', ['type' => Product::class, 'id' => $product->id])['drafts'];
        self::assertNotContains($draft->id, array_column($genericDrafts, 'id'));
        self::assertContains($saved->id, array_column($genericDrafts, 'id'));
        $draftVariant = $draft->getVariants()->one();
        $serializer = Craft::$app->get('mcp')->getCapabilityContainer()->get(ElementSerializer::class);
        self::assertSame([], $serializer->serialize($draftVariant));
        self::assertSame([], $this->ok('elements.list', ['type' => 'variants', 'criteria' => ['productId' => $draft->id]])['elements']);
        // Unmodified variants are shared with the canonical product and remain readable there.
        self::assertSame($draftVariant->id, $this->ok('elements.get', ['type' => 'variants', 'id' => $draftVariant->id])['element']['id']);
        $privateVariant = Craft::$app->getDrafts()->createDraft($product->getDefaultVariant(), $owner->id, 'Private variant',
            newAttributes: ['primaryOwnerId' => $draft->id, 'ownerId' => $draft->id, 'basePrice' => 987.65], provisional: true);
        $this->denied('elements.get', ['type' => 'variants', 'id' => $privateVariant->id]);
        $this->denied('elements.update', ['type' => 'variants', 'id' => $privateVariant->id, 'attributes' => ['basePrice' => 999]]);
        $this->denied('elements.get', ['type' => 'products', 'id' => $draft->id]);
        $this->denied('elements.update', ['type' => 'products', 'id' => $draft->id, 'attributes' => ['title' => 'Peer changed']]);
        $this->denied('drafts.apply', ['type' => Product::class, 'id' => $draft->id]);
        self::assertSame($product->title, Product::find()->id($product->id)->status(null)->one()->title);
        self::assertSame('Shared saved draft', $saved->draftName);
        self::assertSame('Shared edit', $this->ok('elements.update', ['type' => 'products', 'id' => $saved->id, 'attributes' => ['title' => 'Shared edit']])['element']['title']);
        $this->asUser('customer1@crafttest.com', $permissions);
        self::assertSame($draft->id, $this->ok('elements.get', ['type' => 'products', 'id' => $draft->id])['element']['id']);
        $this->asUser('customer3@crafttest.com', array_values(array_diff($permissions, ["commerce-saveProductType:$type->uid"])));
        $this->denied('elements.update', ['type' => 'products', 'id' => $saved->id, 'attributes' => ['title' => 'Read-only peer']]);
    }

    public function testReadOnlyPeerCannotDeleteSharedDraftVariants(): void
    {
        $product = Product::find()->type('tShirts')->one();
        $owner = Craft::$app->getUsers()->getUserByUsernameOrEmail('customer1@crafttest.com');
        $draft = Craft::$app->getDrafts()->createDraft($product, $owner->id, 'Shared draft');
        $variant = Craft::$app->getDrafts()->createDraft($product->getDefaultVariant(), $owner->id, 'Shared variant',
            newAttributes: ['primaryOwnerId' => $draft->id, 'ownerId' => $draft->id, 'basePrice' => 23.45]);
        $site = Craft::$app->getSites()->getPrimarySite();
        $type = $product->getType();
        $permissions = ['accessCp', 'accessPlugin-commerce', "editSite:$site->uid", "commerce-viewProductType:$type->uid"];
        $this->asUser('customer3@crafttest.com', $permissions);
        self::assertSame($variant->id, $this->ok('elements.get', ['type' => 'variants', 'id' => $variant->id])['element']['id']);
        $this->denied('drafts.delete', ['type' => Variant::class, 'id' => $variant->id]);
        self::assertNotNull(Variant::find()->id($variant->id)->drafts(null)->status(null)->one());
        $this->asUser('customer3@crafttest.com', [...$permissions, "commerce-saveProductType:$type->uid"]);
        self::assertTrue($this->ok('drafts.delete', ['type' => Variant::class, 'id' => $variant->id])['deleted']);
    }

    public function testTaxRatesRequireAZoneInTheirStore(): void
    {
        $store = Plugin::getInstance()->getStores()->getPrimaryStore();
        $other = Plugin::getInstance()->getStores()->getStoreByHandle('ukStore');
        $zone = $this->ok('commerce.resources.create', ['type' => 'taxZones', 'attributes' => [
            'storeId' => $other->id, 'name' => 'Foreign zone', 'condition' => ['class' => \craft\commerce\elements\conditions\addresses\ZoneAddressCondition::class],
        ]])['item'];
        $category = collect(Plugin::getInstance()->getTaxCategories()->getAllTaxCategories())->first();
        $attributes = ['storeId' => $store->id, 'name' => 'Scoped rate', 'rate' => 0.13, 'taxCategoryId' => $category->id];
        $this->denied('commerce.resources.create', ['type' => 'taxRates', 'attributes' => [...$attributes, 'taxZoneId' => $zone['id']]]);
        $rate = $this->ok('commerce.resources.create', ['type' => 'taxRates', 'attributes' => [...$attributes, 'taxZoneId' => null]])['item'];
        $this->denied('commerce.resources.update', ['type' => 'taxRates', 'storeId' => $store->id,
            'identifier' => ['id' => $rate['id']], 'attributes' => ['taxZoneId' => $zone['id']],
        ]);
        self::assertNull(Plugin::getInstance()->getTaxRates()->getTaxRateById($rate['id'], $store->id)->taxZoneId);
        $local = $this->ok('commerce.resources.create', ['type' => 'taxZones', 'attributes' => [
            'storeId' => $store->id, 'name' => 'Local zone', 'condition' => ['class' => \craft\commerce\elements\conditions\addresses\ZoneAddressCondition::class],
        ]])['item'];
        self::assertSame($local['id'], $this->ok('commerce.resources.update', ['type' => 'taxRates', 'storeId' => $store->id,
            'identifier' => ['id' => $rate['id']], 'attributes' => ['taxZoneId' => $local['id']],
        ])['item']['taxZoneId']);
    }

    public function testVariantsRejectForeignStoreShippingCategories(): void
    {
        $variant = Variant::find()->sku('hct-blue')->one();
        $other = Plugin::getInstance()->getStores()->getStoreByHandle('ukStore');
        $category = $this->ok('commerce.resources.create', ['type' => 'shippingCategories', 'attributes' => [
            'storeId' => $other->id, 'name' => 'Foreign shipping', 'handle' => 'foreignShipping',
        ]])['item'];
        $this->denied('elements.update', ['type' => 'variants', 'id' => $variant->id, 'attributes' => ['shippingCategoryId' => $category['id']]]);
        $this->denied('elements.create', ['type' => 'variants', 'attributes' => [
            'productId' => $variant->primaryOwnerId, 'sku' => 'invalid-shipping', 'shippingCategoryId' => $category['id'],
        ]]);
        self::assertSame($variant->shippingCategoryId, Variant::find()->id($variant->id)->one()->getShippingCategory()->id);
        self::assertSame($variant->shippingCategoryId, $this->ok('elements.update', ['type' => 'variants', 'id' => $variant->id,
            'attributes' => ['shippingCategoryId' => $variant->shippingCategoryId],
        ])['element']['shippingCategoryId']);
    }

    public function testProductTypeFieldLayoutsCanBeCreatedAndUpdated(): void
    {
        $site = Craft::$app->getSites()->getPrimarySite();
        $layout = ['tabs' => [['name' => 'MCP fields', 'elements' => []]]];
        $type = $this->ok('commerce.configuration.create', ['type' => 'productTypes', 'attributes' => [
            'name' => 'MCP layouts', 'handle' => 'mcpLayouts', 'siteSettings' => [['siteId' => $site->id]],
            'productFieldLayout' => $layout, 'variantFieldLayout' => $layout,
        ]])['item'];
        foreach (['productFieldLayout', 'variantFieldLayout'] as $name) {
            self::assertSame('MCP fields', $type[$name]['tabs'][0]['name']);
        }
        $updated = $this->ok('commerce.configuration.update', ['type' => 'productTypes', 'identifier' => ['id' => $type['id']],
            'attributes' => ['variantFieldLayout' => ['tabs' => [['name' => 'Updated variant fields', 'elements' => []]]]],
        ])['item'];
        self::assertSame('MCP fields', $updated['productFieldLayout']['tabs'][0]['name']);
        self::assertSame('Updated variant fields', $updated['variantFieldLayout']['tabs'][0]['name']);
    }

    public function testStandaloneProvisionalVariantIsPrivateWithCanonicalOwner(): void
    {
        $product = Product::find()->type('tShirts')->one();
        $owner = Craft::$app->getUsers()->getUserByUsernameOrEmail('customer1@crafttest.com');
        $private = Craft::$app->getDrafts()->createDraft($product->getDefaultVariant(), $owner->id, 'Standalone private variant',
            newAttributes: ['basePrice' => 987.65], provisional: true);
        $site = Craft::$app->getSites()->getPrimarySite();
        $type = $product->getType();
        $permissions = ['accessCp', 'accessPlugin-commerce', "editSite:$site->uid", "commerce-viewProductType:$type->uid", "commerce-saveProductType:$type->uid"];
        $this->asUser('customer3@crafttest.com', $permissions);
        $this->denied('elements.get', ['type' => 'variants', 'id' => $private->id]);
        $this->denied('elements.update', ['type' => 'variants', 'id' => $private->id, 'attributes' => ['basePrice' => 1]]);
        $serializer = Craft::$app->get('mcp')->getCapabilityContainer()->get(ElementSerializer::class);
        self::assertSame([], $serializer->serialize($private));
        $this->asUser('customer1@crafttest.com', $permissions);
        self::assertEqualsWithDelta(987.65, $this->ok('elements.get', ['type' => 'variants', 'id' => $private->id])['element']['basePrice'], 0.000001);
        self::assertEqualsWithDelta(19.99, Variant::find()->id($product->defaultVariantId)->status(null)->one()->basePrice, 0.000001);
    }

    public function testOrderValidationDoesNotCreateCustomers(): void
    {
        $order = Order::find()->status(null)->one();
        $email = 'mcp-validation-only@crafttest.com';
        self::assertNull(Craft::$app->getUsers()->getUserByUsernameOrEmail($email));
        Craft::$app->get('mcp')->tokenScopes = [Mcp::SCOPE_READ];
        self::assertTrue($this->ok('elements.validate', ['type' => 'orders', 'id' => $order->id, 'attributes' => ['email' => $email]])['valid']);
        self::assertNull(Craft::$app->getUsers()->getUserByUsernameOrEmail($email));
        self::assertNotSame($email, Order::find()->id($order->id)->status(null)->one()->getEmail());
    }

    public function testUnauthorizedOrderCreationDoesNotCreateCustomers(): void
    {
        $store = Plugin::getInstance()->getStores()->getStoreByHandle('ukStore');
        $email = 'mcp-denied-create@crafttest.com';
        $site = Craft::$app->getSites()->getPrimarySite();
        $this->asUser('customer3@crafttest.com', ['accessCp', 'accessPlugin-commerce', "editSite:$site->uid", 'commerce-editOrders']);
        self::assertNull(Craft::$app->getUsers()->getUserByUsernameOrEmail($email));
        $this->denied('elements.create', ['type' => 'orders', 'attributes' => ['storeId' => $store->id, 'email' => $email]]);
        self::assertNull(Craft::$app->getUsers()->getUserByUsernameOrEmail($email));
    }

    public function testFailedOrderSaveDoesNotLeaveACustomer(): void
    {
        $email = 'mcp-invalid-cart@crafttest.com';
        $this->denied('elements.create', ['type' => 'orders', 'attributes' => [
            'storeId' => Plugin::getInstance()->getStores()->getPrimaryStore()->id,
            'email' => $email, 'shippingAddress' => ['countryCode' => 'invalid'],
        ]]);
        self::assertNull(Craft::$app->getUsers()->getUserByUsernameOrEmail($email));
    }

    /** @dataProvider promotionTypes */
    public function testPromotionSchedulingDates(string $type): void
    {
        $storeId = Plugin::getInstance()->getStores()->getPrimaryStore()->id;
        $item = $this->ok('commerce.resources.create', ['type' => $type, 'attributes' => [
            ...($type === 'sales' ? [] : ['storeId' => $storeId]), ...($type === 'discounts' ? [] : ['applyAmount' => 10]),
            'name' => 'Scheduled promotion',
            'dateFrom' => '2030-02-03T04:05:06+02:00', 'dateTo' => '2031-07-08T09:10:11-04:00',
        ]])['item'];
        self::assertSame('2030-02-03T02:05:06+00:00', (new \DateTime($item['dateFrom']))->setTimezone(new \DateTimeZone('UTC'))->format(DATE_ATOM));
        self::assertSame('2031-07-08T13:10:11+00:00', (new \DateTime($item['dateTo']))->setTimezone(new \DateTimeZone('UTC'))->format(DATE_ATOM));
        $updated = $this->ok('commerce.resources.update', ['type' => $type, 'storeId' => $storeId, 'identifier' => ['id' => $item['id']],
            'attributes' => ['dateFrom' => '2030-04-05T06:07:08Z', 'dateTo' => null],
        ])['item'];
        self::assertSame('2030-04-05T06:07:08+00:00', (new \DateTime($updated['dateFrom']))->setTimezone(new \DateTimeZone('UTC'))->format(DATE_ATOM));
        self::assertNull($updated['dateTo']);
    }

    public static function promotionTypes(): array
    {
        return [['discounts'], ['sales'], ['catalogPricingRules']];
    }

    /** @dataProvider invalidTransferDrafts */
    public function testDispatchRejectsInvalidDraftTopology(string $state): void
    {
        $origin = Plugin::getInstance()->getInventoryLocations()->getAllInventoryLocations()->first();
        if ($state === 'inactive-origin') {
            $origin = new InventoryLocation(['name' => 'Other origin', 'handle' => 'otherOrigin']);
            self::assertTrue(Plugin::getInstance()->getInventoryLocations()->saveInventoryLocation($origin));
        }
        $destination = new InventoryLocation(['name' => 'Other location', 'handle' => 'otherLocation']);
        self::assertTrue(Plugin::getInstance()->getInventoryLocations()->saveInventoryLocation($destination));
        $item = Variant::find()->sku('hct-blue')->one()->inventoryItemId;
        $transfer = $this->ok('commerce.transfers.save', ['originLocationId' => $origin->id, 'destinationLocationId' => $destination->id,
            'items' => [['inventoryItemId' => $item, 'quantity' => 2]],
        ])['transfer'];
        if ($state === 'empty') {
            Craft::$app->getDb()->createCommand()->delete(Table::TRANSFERDETAILS, ['transferId' => $transfer['id']])->execute();
        } elseif (str_starts_with($state, 'inactive-')) {
            self::assertTrue(Plugin::getInstance()->getInventoryLocations()->executeDeactivateInventoryLocation(
                new \craft\commerce\models\inventory\DeactivateInventoryLocation([
                    'inventoryLocation' => $state === 'inactive-origin' ? $origin : $destination,
                    'destinationInventoryLocation' => $state === 'inactive-origin' ? $destination : $origin,
                ])));
        } else {
            Craft::$app->getDb()->createCommand()->update(Table::TRANSFERS,
                ['destinationLocationId' => $state === 'same-location' ? $origin->id : null], ['id' => $transfer['id']])->execute();
        }
        $this->denied('commerce.transfers.dispatch', ['id' => $transfer['id']]);
        self::assertSame(0, $this->ledgerCount($transfer['id']));
        self::assertTrue(Transfer::find()->id($transfer['id'])->status(null)->one()->isTransferDraft());
    }

    public static function invalidTransferDrafts(): array
    {
        return [['empty'], ['same-location'], ['missing-location'], ['inactive-origin'], ['inactive-destination']];
    }

    public function testCustomersCanEditOnlyTheirOwnIncompleteCarts(): void
    {
        $storeId = Plugin::getInstance()->getStores()->getPrimaryStore()->id;
        $cart = $this->ok('elements.create', ['type' => 'orders', 'attributes' => ['storeId' => $storeId, 'email' => 'customer3@crafttest.com']])['element'];
        $peer = $this->ok('elements.create', ['type' => 'orders', 'attributes' => ['storeId' => $storeId, 'email' => 'customer1@crafttest.com']])['element'];
        $guest = $this->ok('elements.create', ['type' => 'orders', 'attributes' => ['storeId' => $storeId]])['element'];
        $completed = Order::find()->isCompleted()->one();
        $variant = Variant::find()->sku('hct-blue')->one();
        $this->ok('commerce.resources.create', ['type' => 'discounts', 'attributes' => [
            'storeId' => $storeId, 'name' => 'Customer cart discount', 'enabled' => true,
            'allPurchasables' => true, 'allCategories' => true, 'requireCouponCode' => true,
            'baseDiscount' => -5, 'coupons' => [['code' => 'OWNCART']],
        ]]);
        $method = $this->ok('commerce.resources.create', ['type' => 'shippingMethods', 'attributes' => [
            'storeId' => $storeId, 'name' => 'Customer express', 'handle' => 'customerExpress', 'enabled' => true,
        ]])['item'];
        $this->ok('commerce.resources.create', ['type' => 'shippingRules', 'attributes' => ['name' => 'Express rate', 'methodId' => $method['id'], 'baseRate' => 7]]);
        $unavailable = $this->ok('elements.create', ['type' => 'variants', 'attributes' => [
            'productId' => $variant->primaryOwnerId, 'sku' => 'mcp-unavailable-cart', 'basePrice' => 13, 'enabled' => false,
        ]])['element'];
        $this->asUser('customer3@crafttest.com', []);
        Craft::$app->get('mcp')->tokenScopes = [Mcp::SCOPE_WRITE];
        $this->denied('commerce.orders.set-line-item', ['orderId' => $cart['id'], 'purchasableId' => $unavailable['id'], 'quantity' => 1]);
        $this->ok('commerce.orders.set-line-item', ['orderId' => $cart['id'], 'purchasableId' => $variant->id, 'quantity' => 2]);
        $items = $this->ok('commerce.orders.related', ['orderId' => $cart['id'], 'relation' => 'lineItems'])['items'];
        self::assertCount(1, $items);
        self::assertSame(2, $items[0]['qty']);
        $address = ['firstName' => 'Ada', 'lastName' => 'Lovelace', 'countryCode' => 'US', 'administrativeArea' => 'NY', 'locality' => 'New York', 'postalCode' => '10001', 'addressLine1' => '1 Customer St'];
        $this->ok('elements.update', ['type' => 'orders', 'id' => $cart['id'], 'attributes' => [
            'couponCode' => 'OWNCART', 'shippingMethodHandle' => 'usShipping', 'shippingAddress' => $address,
            'billingAddress' => [...$address, 'addressLine1' => '2 Billing St'],
        ]]);
        $saved = Order::find()->id($cart['id'])->status(null)->one();
        self::assertSame($cart['customerId'], $saved->customerId);
        self::assertSame('OWNCART', $saved->couponCode);
        self::assertSame('usShipping', $saved->shippingMethodHandle);
        self::assertSame('1 Customer St', $saved->getShippingAddress()->addressLine1);
        self::assertSame('2 Billing St', $saved->getBillingAddress()->addressLine1);
        self::assertEqualsWithDelta(-5, $saved->getTotalDiscount(), 0.000001);
        $this->ok('elements.update', ['type' => 'orders', 'id' => $cart['id'], 'attributes' => ['shippingMethodHandle' => 'customerExpress']]);
        $saved = Order::find()->id($cart['id'])->status(null)->one();
        self::assertSame('customerExpress', $saved->shippingMethodHandle);
        self::assertEqualsWithDelta(7, $saved->getTotalShippingCost(), 0.000001);
        self::assertSame($cart['id'], $this->ok('elements.get', ['type' => 'orders', 'id' => $cart['id']])['element']['id']);
        $this->ok('commerce.orders.set-line-item', ['orderId' => $cart['id'], 'lineItemId' => $items[0]['id'], 'quantity' => 3]);
        self::assertSame(3, Order::find()->id($cart['id'])->status(null)->one()->getLineItems()[0]->qty);
        $this->ok('commerce.orders.set-line-item', ['orderId' => $cart['id'], 'lineItemId' => $items[0]['id'], 'quantity' => 0]);
        self::assertSame([], $this->ok('commerce.orders.related', ['orderId' => $cart['id'], 'relation' => 'lineItems'])['items']);
        foreach ([$peer['id'], $guest['id'], $completed->id] as $id) {
            $this->denied('elements.update', ['type' => 'orders', 'id' => $id, 'attributes' => ['couponCode' => 'OWNCART']]);
            $this->denied('commerce.orders.set-line-item', ['orderId' => $id, 'purchasableId' => $variant->id, 'quantity' => 1]);
        }
        $this->asUser('customer1@crafttest.com', []);
        $this->denied('elements.update', ['type' => 'orders', 'id' => $completed->id, 'attributes' => ['couponCode' => 'OWNCART']]);
        $this->denied('commerce.orders.set-line-item', ['orderId' => $completed->id, 'purchasableId' => $variant->id, 'quantity' => 1]);
    }

    public function testCustomerCartEditingCannotEscalatePrivileges(): void
    {
        $storeId = Plugin::getInstance()->getStores()->getPrimaryStore()->id;
        $cart = $this->ok('elements.create', ['type' => 'orders', 'attributes' => ['storeId' => $storeId, 'email' => 'customer3@crafttest.com']])['element'];
        $peer = $this->ok('elements.create', ['type' => 'orders', 'attributes' => ['storeId' => $storeId, 'email' => 'customer1@crafttest.com']])['element'];
        $variant = Variant::find()->sku('hct-blue')->one();
        $this->ok('commerce.orders.set-line-item', ['orderId' => $peer['id'], 'purchasableId' => $variant->id, 'quantity' => 2]);
        $peerItem = Order::find()->id($peer['id'])->status(null)->one()->getLineItems()[0];
        $statusId = Plugin::getInstance()->getOrderStatuses()->getAllOrderStatuses($storeId)->first()->id;
        $this->asUser('customer3@crafttest.com', []);
        Craft::$app->get('mcp')->tokenScopes = [Mcp::SCOPE_WRITE];
        // Establish that this is the permitted owner path before probing restrictions.
        $this->ok('elements.update', ['type' => 'orders', 'id' => $cart['id'], 'attributes' => ['couponCode' => null]]);
        foreach ([['customerId' => $peer['customerId']], ['email' => 'mcp-cart-takeover@crafttest.com'], ['storeId' => $storeId + 1], ['isCompleted' => true], ['orderStatusId' => $statusId], ['message' => 'Administrative edit']] as $attributes) {
            $this->denied('elements.update', ['type' => 'orders', 'id' => $cart['id'], 'attributes' => $attributes]);
        }
        $this->denied('elements.update', ['type' => 'orders', 'id' => $cart['id'], 'fields' => ['untrusted' => 'value']]);
        $this->denied('elements.create', ['type' => 'orders', 'attributes' => ['storeId' => $storeId, 'email' => 'customer3@crafttest.com']]);
        $this->denied('commerce.orders.complete', ['orderId' => $cart['id']]);
        $this->denied('commerce.orders.set-status', ['orderId' => $cart['id'], 'statusId' => $statusId]);
        $this->denied('commerce.orders.send-email', ['orderId' => $cart['id'], 'emailId' => 1]);
        $this->denied('commerce.orders.related', ['orderId' => $cart['id'], 'relation' => 'transactions']);
        $this->denied('elements.delete', ['type' => 'orders', 'id' => $cart['id']]);
        $this->denied('commerce.orders.set-line-item', ['orderId' => $cart['id'], 'lineItemId' => $peerItem->id, 'quantity' => 0]);
        self::assertSame(2, Order::find()->id($peer['id'])->status(null)->one()->getLineItems()[0]->qty);
        Craft::$app->get('mcp')->tokenScopes = [Mcp::SCOPE_READ];
        $this->denied('elements.update', ['type' => 'orders', 'id' => $cart['id'], 'attributes' => ['couponCode' => null]]);
        $this->denied('commerce.orders.set-line-item', ['orderId' => $cart['id'], 'purchasableId' => $variant->id, 'quantity' => 1]);
        $saved = Order::find()->id($cart['id'])->status(null)->one();
        self::assertFalse($saved->isCompleted);
        self::assertSame($cart['customerId'], $saved->customerId);
        self::assertSame($storeId, $saved->storeId);
        self::assertSame($cart['orderStatusId'] ?? null, $saved->orderStatusId);
        self::assertNull(Craft::$app->getUsers()->getUserByUsernameOrEmail('mcp-cart-takeover@crafttest.com'));
        Craft::$app->get('mcp')->tokenScopes = null;
        self::assertFalse(Craft::$app->getElements()->canSave($saved, Craft::$app->getUser()->getIdentity()));
    }

    /** @dataProvider cartUpdateOperations */
    public function testCartWritesCannotReopenAnOrderDuringCheckout(string $operation): void
    {
        $storeId = Plugin::getInstance()->getStores()->getPrimaryStore()->id;
        $cart = $this->ok('elements.create', ['type' => 'orders', 'attributes' => ['storeId' => $storeId, 'email' => 'customer3@crafttest.com']])['element'];
        $variant = Variant::find()->sku('hct-blue')->one();
        $this->ok('commerce.orders.set-line-item', ['orderId' => $cart['id'], 'purchasableId' => $variant->id, 'quantity' => 2]);
        if ($operation !== 'commerce.orders.recalculate') {
            $this->asUser('customer3@crafttest.com', []);
        }
        Craft::$app->get('mcp')->tokenScopes = [Mcp::SCOPE_WRITE];
        $attempted = false;
        $completed = false;
        $listener = function($event) use ($cart, &$attempted, &$completed): void {
            if ($attempted || !$event->element instanceof Order || $event->element->id !== $cart['id']) {
                return;
            }
            $attempted = true;
            try {
                // Native checkout races after the MCP reload, before the stale cart is saved.
                $completed = Order::find()->id($cart['id'])->status(null)->one()->markAsComplete();
            } catch (\Exception) {
                // A serialized checkout can be retried after this edit finishes.
            }
        };
        $elements = Craft::$app->getElements();
        $elements->on(\craft\services\Elements::EVENT_BEFORE_SAVE_ELEMENT, $listener);
        try {
            $this->ok($operation, match ($operation) {
                'elements.update' => ['type' => 'orders', 'id' => $cart['id'], 'attributes' => ['couponCode' => null]],
                'commerce.orders.recalculate' => ['orderId' => $cart['id']],
                default => ['orderId' => $cart['id'], 'purchasableId' => $variant->id, 'quantity' => 3],
            });
        } finally {
            $elements->off(\craft\services\Elements::EVENT_BEFORE_SAVE_ELEMENT, $listener);
        }
        self::assertTrue($attempted);
        $saved = Order::find()->id($cart['id'])->status(null)->one();
        if ($completed) {
            self::assertTrue($saved->isCompleted, 'An MCP cart edit must never reopen an order completed by checkout.');
        } else {
            self::assertTrue($saved->markAsComplete());
        }
        $this->asUser('customer3@crafttest.com', []);
        $this->denied('elements.update', ['type' => 'orders', 'id' => $cart['id'], 'attributes' => ['couponCode' => null]]);
    }

    public static function cartUpdateOperations(): array
    {
        return [['elements.update'], ['commerce.orders.set-line-item'], ['commerce.orders.recalculate']];
    }

    /** @dataProvider competingInventoryWrites */
    public function testFulfillmentSerializesWithOtherInventoryWriters(string $operation): void
    {
        $inventory = Plugin::getInstance()->getInventory();
        $product = Product::find()->type('tShirts')->one();
        $created = $this->ok('elements.create', ['type' => 'variants', 'attributes' => [
            'productId' => $product->id, 'sku' => 'mcp-stock-race', 'basePrice' => 12, 'inventoryTracked' => true,
        ]])['element'];
        $variant = Variant::find()->id($created['id'])->one();
        $location = Plugin::getInstance()->getInventoryLocations()->getAllInventoryLocations()->first();
        $this->ok('commerce.stores.set-inventory-locations', ['storeId' => $variant->getStoreId(), 'ids' => [$location->id]]);
        $this->ok('commerce.inventory.update', ['locationId' => $location->id, 'itemId' => $variant->inventoryItemId,
            'type' => 'available', 'action' => 'set', 'quantity' => 10,
        ]);
        $orders = [];
        for ($i = 0; $i < 2; $i++) {
            $cart = $this->ok('elements.create', ['type' => 'orders', 'attributes' => ['storeId' => $variant->getStoreId()]])['element'];
            $this->ok('commerce.orders.set-line-item', ['orderId' => $cart['id'], 'purchasableId' => $variant->id, 'quantity' => 1]);
            $this->ok('commerce.orders.complete', ['orderId' => $cart['id']]);
            $orders[] = Order::find()->id($cart['id'])->status(null)->one();
        }
        $levels = array_map(fn($order) => $inventory->getInventoryFulfillmentLevels($order)->firstWhere('inventoryItemId', $variant->inventoryItemId), $orders);
        self::assertNotNull($levels[0]);
        self::assertNotNull($levels[1]);
        self::assertSame($levels[0]->inventoryLocationId, $levels[1]->inventoryLocationId);
        $locationId = $levels[0]->inventoryLocationId;
        $this->ok('commerce.inventory.update', ['locationId' => $locationId, 'itemId' => $variant->inventoryItemId,
            'type' => 'available', 'action' => 'adjust', 'quantity' => 1 - $inventory->getFulfillableQuantity($variant->inventoryItemId, $locationId),
        ]);
        self::assertSame(1, $inventory->getFulfillableQuantity($variant->inventoryItemId, $locationId));
        $validations = 0;
        $competing = null;
        $listener = function($event) use ($operation, $inventory, $variant, $levels, $orders, $locationId, &$validations, &$competing): void {
            if ($event->sender->lineItemId === $levels[0]->lineItemId && ++$validations === 2) {
                // Interleave after the final stock validation but before the ledger write.
                if ($operation === 'fulfill') {
                    $response = $this->call('commerce.orders.fulfill', ['orderId' => $orders[1]->id,
                        'lineItemId' => $levels[1]->lineItemId, 'locationId' => $locationId, 'quantity' => 1,
                    ]);
                    $competing = !($response['result']['isError'] ?? false);
                } else {
                    try {
                        $inventory->updateInventoryLevel($variant->inventoryItemId, -1, [
                            'inventoryLocationId' => $locationId, 'updateAction' => \craft\commerce\enums\InventoryUpdateQuantityType::ADJUST,
                        ]);
                        $competing = true;
                    } catch (\RuntimeException) {
                        $competing = false;
                    }
                }
            }
        };
        $class = \craft\commerce\models\inventory\InventoryFulfillMovement::class;
        \yii\base\Event::on($class, \yii\base\Model::EVENT_AFTER_VALIDATE, $listener);
        try {
            $this->ok('commerce.orders.fulfill', ['orderId' => $orders[0]->id, 'lineItemId' => $levels[0]->lineItemId,
                'locationId' => $locationId, 'quantity' => 1,
            ]);
        } finally {
            \yii\base\Event::off($class, \yii\base\Model::EVENT_AFTER_VALIDATE, $listener);
        }
        self::assertNotNull($competing);
        self::assertFalse($competing, 'A competing inventory write must not pass a fulfillment that already validated the remaining stock.');
        self::assertSame(0, $inventory->getFulfillableQuantity($variant->inventoryItemId, $locationId));
        self::assertSame($levels[1]->outstandingCommittedQuantity,
            $inventory->getInventoryFulfillmentLevels($orders[1])->firstWhere('lineItemId', $levels[1]->lineItemId)->outstandingCommittedQuantity);
        $inventory->updateInventoryLevel($variant->inventoryItemId, 1, [
            'inventoryLocationId' => $locationId, 'updateAction' => \craft\commerce\enums\InventoryUpdateQuantityType::ADJUST,
        ]);
        self::assertSame(1, $inventory->getFulfillableQuantity($variant->inventoryItemId, $locationId));
    }

    public static function competingInventoryWrites(): array
    {
        return [['fulfill'], ['native-adjust']];
    }

    private function asUser(string $email, array $permissions): void
    {
        $user = Craft::$app->getUsers()->getUserByUsernameOrEmail($email);
        Craft::$app->getUserPermissions()->saveUserPermissions($user->id, $permissions);
        Craft::$app->getUser()->setIdentity(Craft::$app->getUsers()->getUserById($user->id));
    }

    private function call(string $name, array $arguments): array
    {
        $request = new ServerRequest('POST', 'http://localhost/mcp/auth', [
            'Content-Type' => 'application/json', 'Accept' => 'application/json, text/event-stream',
            'MCP-Protocol-Version' => '2026-07-28', 'Mcp-Method' => 'tools/call', 'Mcp-Name' => $name,
        ], json_encode(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => [
            'name' => $name, 'arguments' => (object)$arguments, '_meta' => [
                'io.modelcontextprotocol/protocolVersion' => '2026-07-28',
                'io.modelcontextprotocol/clientCapabilities' => (object)[],
            ],
        ]], JSON_THROW_ON_ERROR));
        $container = Craft::$app->get('mcp')->getCapabilityContainer();
        $server = $container->get(ServerFactory::class)->authenticated();
        $response = $server->run(new StreamableHttpTransport($request, middleware: $container->get(HttpTransportMiddleware::class)->forRequest($request)));
        return json_decode((string)$response->getBody(), true, flags: JSON_THROW_ON_ERROR);
    }

    private function ok(string $name, array $arguments = []): array
    {
        $response = $this->call($name, $arguments);
        self::assertArrayNotHasKey('error', $response, json_encode($response));
        self::assertFalse($response['result']['isError'] ?? false, json_encode($response));
        return $response['result']['structuredContent'];
    }

    private function denied(string $name, array $arguments = []): void
    {
        $response = $this->call($name, $arguments);
        if (isset($response['error'])) {
            self::assertSame(-32602, $response['error']['code'], json_encode($response));
        } else {
            self::assertTrue($response['result']['isError'] ?? false, json_encode($response));
            self::assertStringNotContainsString('Check the server logs', json_encode($response));
        }
    }

    private function ledgerCount(int $id): int
    {
        return (int)(new Query())->from(Table::INVENTORYTRANSACTIONS)->where(['transferId' => $id])->count();
    }

    private function ledgerSum(int $id, int $locationId, string $type): int
    {
        return (int)(new Query())->from(Table::INVENTORYTRANSACTIONS)->where(['transferId' => $id, 'inventoryLocationId' => $locationId, 'type' => $type])->sum('quantity');
    }
}
