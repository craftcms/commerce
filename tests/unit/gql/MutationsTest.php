<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\gql;

use Craft;
use craft\commerce\elements\Order;
use craft\commerce\elements\Product;
use craft\commerce\elements\Variant;
use craft\commerce\models\InventoryLocation;
use craft\commerce\Plugin;
use craft\enums\CmsEdition;
use craft\helpers\StringHelper;
use craft\models\GqlSchema;
use craft\test\TestCase;
use craftcommercetests\fixtures\OrdersFixture;
use craftcommercetests\fixtures\SubscriptionsFixture;

/**
 * Tests Commerce’s GraphQL mutations.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.8.0
 */
class MutationsTest extends TestCase
{
    private bool $lazyGqlTypes;

    public function _fixtures(): array
    {
        return [
            'orders' => [
                'class' => OrdersFixture::class,
            ],
            'subscriptions' => [
                'class' => SubscriptionsFixture::class,
            ],
        ];
    }

    protected function _before(): void
    {
        Craft::$app->edition = CmsEdition::Pro;

        // Prebuilt schemas can only be built once per process
        $generalConfig = Craft::$app->getConfig()->getGeneral();
        $this->lazyGqlTypes = $generalConfig->lazyGqlTypes;
        $generalConfig->lazyGqlTypes = true;
    }

    protected function _after(): void
    {
        Craft::$app->getConfig()->getGeneral()->lazyGqlTypes = $this->lazyGqlTypes;
        Craft::$app->getGql()->flushCaches();
    }

    public function testSaveProductWithVariants(): void
    {
        $uid = Plugin::getInstance()->getProductTypes()->getProductTypeByHandle('tShirts')->uid;
        $schema = $this->schema(["productTypes.$uid:read", "productTypes.$uid:create", "productTypes.$uid:save"]);

        $product = $this->execute($schema, 'mutation {
            save_tShirts_Product(title: "GraphQL Tee", variants: [
                {sku: "gql-small", basePrice: 10},
                {sku: "gql-large", basePrice: 12, isDefault: true}
            ]) { id title defaultSku variants { id sku price } }
        }')['data']['save_tShirts_Product'];

        self::assertSame('GraphQL Tee', $product['title']);
        self::assertSame('gql-large', $product['defaultSku']);
        self::assertSame(['gql-small', 'gql-large'], array_column($product['variants'], 'sku'));
        [$small, $large] = array_column($product['variants'], 'id');

        // Variants left out of the list are deleted
        $variants = $this->execute($schema, "mutation {
            save_tShirts_Product(id: {$product['id']}, variants: [{id: $large, basePrice: 15}]) { variants { id price } }
        }")['data']['save_tShirts_Product']['variants'];

        self::assertSame([['id' => $large, 'price' => 15.0]], $variants);
        self::assertNull(Variant::find()->id($small)->status(null)->one());

        // Variants can also be saved individually
        $variant = $this->execute($schema, "mutation {
            save_tShirts_Variant(productId: {$product['id']}, sku: \"gql-medium\", basePrice: 11) { sku product { id } }
        }")['data']['save_tShirts_Variant'];

        self::assertSame(['sku' => 'gql-medium', 'product' => ['id' => $product['id']]], $variant);
    }

    public function testAuthenticatedUsersNeedPermissionToCreateProducts(): void
    {
        $productType = Plugin::getInstance()->getProductTypes()->getProductTypeByHandle('tShirts');
        $user = Craft::$app->getUsers()->getUserByUsernameOrEmail('customer2@crafttest.com');
        Craft::$app->getUserPermissions()->saveUserPermissions($user->id, [
            'editSite:' . Craft::$app->getSites()->getPrimarySite()->uid,
            "commerce-viewProductType:$productType->uid",
            "commerce-saveProductType:$productType->uid",
        ]);
        $schema = Craft::$app->getGql()->getSchemaForUser(Craft::$app->getUsers()->getUserById($user->id));

        $result = $this->execute($schema, 'mutation { save_tShirts_Product(title: "New", variants: [{sku: "new", basePrice: 1}]) { id } }');
        self::assertSame('Unable to perform the action.', $result['errors'][0]['message']);

        $productId = Product::find()->typeId($productType->id)->one()->id;
        $result = $this->execute($schema, "mutation { save_tShirts_Product(id: $productId, title: \"Renamed\") { title } }");
        self::assertSame('Renamed', $result['data']['save_tShirts_Product']['title']);
    }

    public function testSaveOrder(): void
    {
        $storeUid = Plugin::getInstance()->getStores()->getPrimaryStore()->uid;
        $schema = $this->schema(["orders.$storeUid:create", "orders.$storeUid:save"]);
        $white = Variant::find()->sku('hct-white')->one()->id;
        $blue = Variant::find()->sku('hct-blue')->one()->id;

        $order = $this->execute($schema, "mutation {
            save_Order(email: \"gql@example.com\", lineItems: [{purchasableId: $white, qty: 2}]) {
                id email isCompleted lineItems { id sku qty }
            }
        }")['data']['save_Order'];

        self::assertSame('gql@example.com', $order['email']);
        self::assertFalse($order['isCompleted']);
        self::assertSame([['sku' => 'hct-white', 'qty' => 2]], array_map(fn($li) => ['sku' => $li['sku'], 'qty' => $li['qty']], $order['lineItems']));

        // Line items left out of the list are removed, and the order can be completed
        $order = $this->execute($schema, "mutation {
            save_Order(id: {$order['id']}, lineItems: [{purchasableId: $blue, qty: 3}], isCompleted: true) {
                isCompleted dateOrdered lineItems { sku qty }
            }
        }")['data']['save_Order'];

        self::assertTrue($order['isCompleted']);
        self::assertNotNull($order['dateOrdered']);
        self::assertSame([['sku' => 'hct-blue', 'qty' => 3]], $order['lineItems']);
    }

    public function testAuthenticatedUsersNeedPermissionToEditOrders(): void
    {
        $orderId = Order::find()->isCompleted()->one()->id;
        $user = Craft::$app->getUsers()->getUserByUsernameOrEmail('customer1@crafttest.com');
        $mutation = "mutation { save_Order(id: $orderId, message: \"Hi\") { id } }";

        // Customers can query their own orders, but not edit them
        Craft::$app->getUserPermissions()->saveUserPermissions($user->id, []);
        $schema = Craft::$app->getGql()->getSchemaForUser(Craft::$app->getUsers()->getUserById($user->id));
        $storeUid = Plugin::getInstance()->getStores()->getPrimaryStore()->uid;
        self::assertContains("orders.$storeUid:read", $schema->scope);
        self::assertNotContains("orders.$storeUid:save", $schema->scope);
        self::assertArrayNotHasKey('save_Order', $this->execute($schema, $mutation)['data'] ?? []);

        Craft::$app->getUserPermissions()->saveUserPermissions($user->id, ['commerce-manageOrders', 'commerce-editOrders']);
        $schema = Craft::$app->getGql()->getSchemaForUser(Craft::$app->getUsers()->getUserById($user->id));
        self::assertSame((string)$orderId, $this->execute($schema, $mutation)['data']['save_Order']['id']);
        self::assertSame('Hi', Order::find()->id($orderId)->one()->message);
    }

    public function testPendingTransfersAreLocked(): void
    {
        $schema = $this->schema(['transfers.all:create', 'transfers.all:save', 'transfers.all:delete']);
        $origin = Plugin::getInstance()->getInventoryLocations()->getAllInventoryLocations()->first()->id;
        $destination = new InventoryLocation(['name' => 'Warehouse', 'handle' => 'warehouse']);
        Plugin::getInstance()->getInventoryLocations()->saveInventoryLocation($destination, false);
        $purchasableId = Variant::find()->sku('hct-white')->one()->id;

        $transfer = $this->execute($schema, "mutation {
            save_Transfer(originLocationId: $origin, destinationLocationId: $destination->id, details: [{purchasableId: $purchasableId, quantity: 2}]) {
                id transferStatus details { inventoryItemDescription quantity }
            }
        }")['data']['save_Transfer'];

        self::assertSame('draft', $transfer['transferStatus']);
        self::assertSame([['inventoryItemDescription' => 'hct-white', 'quantity' => 2]], $transfer['details']);

        $status = $this->execute($schema, "mutation { save_Transfer(id: {$transfer['id']}, markAsPending: true) { transferStatus } }");
        self::assertSame('pending', $status['data']['save_Transfer']['transferStatus']);

        $result = $this->execute($schema, "mutation { save_Transfer(id: {$transfer['id']}, details: [{purchasableId: $purchasableId, quantity: 1}]) { id } }");
        self::assertSame('Only draft transfers’ locations and items can be changed.', $result['errors'][0]['message']);

        $result = $this->execute($schema, "mutation { deleteTransfer(id: {$transfer['id']}) }");
        self::assertSame('Only draft transfers can be deleted.', $result['errors'][0]['message']);
    }

    public function testAuthenticatedUsersCanOnlyQueryTheirOwnSubscriptions(): void
    {
        $user = Craft::$app->getUsers()->getUserByUsernameOrEmail('customer2@crafttest.com');

        Craft::$app->getUserPermissions()->saveUserPermissions($user->id, []);
        $schema = Craft::$app->getGql()->getSchemaForUser(Craft::$app->getUsers()->getUserById($user->id));
        self::assertSame(0, $this->execute($schema, '{ subscriptionCount }')['data']['subscriptionCount']);

        Craft::$app->getUserPermissions()->saveUserPermissions($user->id, ['commerce-manageSubscriptions']);
        $schema = Craft::$app->getGql()->getSchemaForUser(Craft::$app->getUsers()->getUserById($user->id));
        self::assertSame(1, $this->execute($schema, '{ subscriptionCount }')['data']['subscriptionCount']);
    }

    private function schema(array $scope): GqlSchema
    {
        return new GqlSchema(['name' => 'Test', 'uid' => StringHelper::UUID(), 'scope' => $scope]);
    }

    private function execute(GqlSchema $schema, string $query): array
    {
        $gql = Craft::$app->getGql();
        $gql->flushCaches();
        return $gql->executeQuery($schema, $query);
    }
}
