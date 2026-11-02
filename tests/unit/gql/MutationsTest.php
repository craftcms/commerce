<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\gql;

use Craft;
use craft\commerce\db\Table;
use craft\commerce\elements\Order;
use craft\commerce\elements\Product;
use craft\commerce\elements\Transfer;
use craft\commerce\elements\Variant;
use craft\commerce\models\InventoryLocation;
use craft\commerce\Plugin;
use craft\db\Query;
use craft\enums\CmsEdition;
use craft\helpers\StringHelper;
use craft\models\GqlSchema;
use craft\test\TestCase;
use craftcommercetests\fixtures\OrdersFixture;
use RuntimeException;
use yii\base\Event;

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
        Craft::$app->getUser()->setIdentity(null);
    }

    public function testSaveProductWithVariants(): void
    {
        $uid = Plugin::getInstance()->getProductTypes()->getProductTypeByHandle('tShirts')->uid;
        $schema = $this->schema(["productTypes.$uid:read", "productTypes.$uid:create", "productTypes.$uid:save"]);

        $product = $this->execute($schema, 'mutation {
            save_tShirts_Product(title: "GraphQL Tee", sortOrder: ["new:2", "new:1"], variants: [
                {sku: "gql-small", basePrice: 10},
                {sku: "gql-large", basePrice: 12, isDefault: true}
            ]) { id title defaultSku variants { id sku price } }
        }')['data']['save_tShirts_Product'];

        self::assertSame('GraphQL Tee', $product['title']);
        self::assertSame('gql-large', $product['defaultSku']);
        self::assertSame(['gql-large', 'gql-small'], array_column($product['variants'], 'sku'));
        [$large, $small] = array_column($product['variants'], 'id');

        // Variants can be updated without changing which variants the product has
        $variants = $this->execute($schema, "mutation {
            save_tShirts_Product(id: {$product['id']}, variants: [{id: $small, basePrice: 9}, {sku: \"ignored\", basePrice: 1}]) { variants { sku price } }
        }")['data']['save_tShirts_Product']['variants'];

        self::assertSame([['sku' => 'gql-large', 'price' => 12.0], ['sku' => 'gql-small', 'price' => 9.0]], $variants);

        // Variants left out of the sort order are deleted
        $variants = $this->execute($schema, "mutation {
            save_tShirts_Product(id: {$product['id']}, sortOrder: [$large], variants: [{id: $large, basePrice: 15}]) { variants { id price } }
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

        $result = $this->execute($schema, 'mutation { save_tShirts_Product(title: "New", sortOrder: ["new:1"], variants: [{sku: "new", basePrice: 1}]) { id } }');
        self::assertSame('Unable to perform the action.', $result['errors'][0]['message']);

        $productId = Product::find()->typeId($productType->id)->one()->id;
        $result = $this->execute($schema, "mutation { save_tShirts_Product(id: $productId, title: \"Renamed\") { title } }");
        self::assertSame('Renamed', $result['data']['save_tShirts_Product']['title']);
    }

    public function testProductDrafts(): void
    {
        $productType = Plugin::getInstance()->getProductTypes()->getProductTypeByHandle('tShirts');
        $user = Craft::$app->getUsers()->getUserByUsernameOrEmail('customer2@crafttest.com');
        Craft::$app->getUserPermissions()->saveUserPermissions($user->id, [
            'accessCp',
            'editSite:' . Craft::$app->getSites()->getPrimarySite()->uid,
            "commerce-viewProductType:$productType->uid",
            "commerce-saveProductType:$productType->uid",
        ]);
        $user = Craft::$app->getUsers()->getUserById($user->id);
        $schema = Craft::$app->getGql()->getSchemaForUser($user);
        self::assertContains('elements.drafts:read', $schema->scope);

        $product = Product::find()->typeId($productType->id)->one();
        $variants = $product->getVariants(true)->all();
        $variantId = $variants[0]->id;
        $price = $variants[0]->basePrice;

        // creatorId is ignored for authenticated requests
        $draftId = $this->execute($schema, "mutation { createProductDraft(id: $product->id, name: \"Draft\", creatorId: 1) }")['data']['createProductDraft'];
        $draft = Product::find()->draftId($draftId)->status(null)->one();
        self::assertSame($user->id, $draft->creatorId);

        $result = $this->execute($schema, "mutation {
            save_tShirts_ProductDraft(draftId: $draftId, title: \"Draft title\", sortOrder: [$variantId], variants: [{id: $variantId, basePrice: 99}]) {
                title isDraft variants { price }
            }
        }")['data']['save_tShirts_ProductDraft'];
        self::assertSame(['title' => 'Draft title', 'isDraft' => true, 'variants' => [['price' => 99.0]]], $result);

        // The canonical product and its variants are untouched until the draft is published
        $canonical = Product::find()->id($product->id)->status(null)->one();
        self::assertSame($product->title, $canonical->title);
        self::assertCount(count($variants), $canonical->getVariants(true));
        self::assertEquals($price, Variant::find()->id($variantId)->status(null)->one()->basePrice);

        $drafts = $this->execute($schema, "{ products(drafts: true, draftOf: $product->id, status: null) { draftId draftName } }")['data']['products'];
        self::assertSame([['draftId' => (int)$draftId, 'draftName' => 'Draft']], $drafts);

        $id = $this->execute($schema, "mutation { publishProductDraft(id: $draftId) }")['data']['publishProductDraft'];
        self::assertSame((string)$product->id, $id);
        $canonical = Product::find()->id($product->id)->status(null)->one();
        self::assertSame('Draft title', $canonical->title);
        self::assertSame([$variantId], $canonical->getVariants(true)->pluck('id')->all());
        self::assertEquals(99, Variant::find()->id($variantId)->status(null)->one()->basePrice);
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

    public function testAuthenticatedUsersCanManageTransfers(): void
    {
        // Authenticated users with the “Manage inventory transfers” permission can manage transfers
        $user = Craft::$app->getUsers()->getUserByUsernameOrEmail('customer2@crafttest.com');
        Craft::$app->getUserPermissions()->saveUserPermissions($user->id, ['commerce-manageInventoryTransfers']);
        $schema = Craft::$app->getGql()->getSchemaForUser(Craft::$app->getUsers()->getUserById($user->id));
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

    public function testTransferDispatchValidatesDetailsAndWritesInventory(): void
    {
        $schema = $this->schema(['transfers.all:create', 'transfers.all:save', 'transfers.all:read']);
        $origin = Plugin::getInstance()->getInventoryLocations()->getAllInventoryLocations()->first()->id;
        $destination = new InventoryLocation(['name' => 'Warehouse', 'handle' => 'warehouse']);
        self::assertTrue(Plugin::getInstance()->getInventoryLocations()->saveInventoryLocation($destination, false));
        $variant = Variant::find()->sku('hct-white')->one();
        $blueId = Variant::find()->sku('hct-blue')->one()->id;
        $locations = "originLocationId: $origin, destinationLocationId: $destination->id";
        $draft = $this->execute($schema, "mutation { save_Transfer($locations, details: [{purchasableId: $variant->id, quantity: 2}]) { id } }")['data']['save_Transfer']['id'];
        $count = Transfer::find()->count();

        foreach ([-1, 0, 100000] as $quantity) {
            foreach ([$locations, "id: $draft"] as $identify) {
                $result = $this->execute($schema, "mutation {
                    save_Transfer($identify, markAsPending: true, details: [{purchasableId: $variant->id, quantity: $quantity}, {purchasableId: $blueId, quantity: 2}]) { id }
                }");
                self::assertNotEmpty($result['errors'] ?? [], "Quantity $quantity must be rejected.");
                self::assertSame($count, Transfer::find()->count());
                $fresh = Transfer::find()->id($draft)->one();
                self::assertSame('draft', $fresh->getTransferStatus()->value);
                self::assertSame(2, $fresh->getDetails()[0]->quantity);
                self::assertSame(0, (int)(new Query())->from(Table::INVENTORYTRANSACTIONS)->where(['transferId' => $draft])->count());
            }
        }

        $transactionCount = (new Query())->from(Table::INVENTORYTRANSACTIONS)->count();
        $handler = function(Event $event) {
            if ($event->sender->getTransferStatus()->value === 'pending') {
                throw new RuntimeException('Dispatch failed after inventory update.');
            }
        };
        Event::on(Transfer::class, Transfer::EVENT_AFTER_SAVE, $handler);
        try {
            foreach ([$locations, "id: $draft"] as $identify) {
                $result = $this->execute($schema, "mutation { save_Transfer($identify, markAsPending: true, details: [{purchasableId: $variant->id, quantity: 3}]) { id } }");
                self::assertNotEmpty($result['errors']);
                self::assertSame($count, Transfer::find()->count());
                $fresh = Transfer::find()->id($draft)->one();
                self::assertSame('draft', $fresh->getTransferStatus()->value);
                self::assertSame(2, $fresh->getDetails()[0]->quantity);
                self::assertSame($transactionCount, (new Query())->from(Table::INVENTORYTRANSACTIONS)->count());
            }
        } finally {
            Event::off(Transfer::class, Transfer::EVENT_AFTER_SAVE, $handler);
        }

        // Both create-and-dispatch and editing an existing draft must write the ledger exactly once.
        foreach ([[$locations, 1], ["id: $draft", 99999]] as [$identify, $quantity]) {
            $result = $this->execute($schema, "mutation {
                save_Transfer($identify, markAsPending: true, details: [{purchasableId: $variant->id, quantity: $quantity}]) { id transferStatus details { quantity } }
            }");
            self::assertArrayNotHasKey('errors', $result);
            $transfer = $result['data']['save_Transfer'];
            self::assertSame('pending', $transfer['transferStatus']);
            self::assertSame([['quantity' => $quantity]], $transfer['details']);
            $rows = (new Query())->from(Table::INVENTORYTRANSACTIONS)->where(['transferId' => $transfer['id']])->orderBy(['quantity' => SORT_ASC])->all();
            self::assertSame([-$quantity, $quantity], array_map(fn($row) => (int)$row['quantity'], $rows));
            self::assertSame(['available', 'incoming'], array_column($rows, 'type'));
            self::assertSame([$origin, $destination->id], array_map(fn($row) => (int)$row['inventoryLocationId'], $rows));
            self::assertSame([$variant->getInventoryItem()->id, $variant->getInventoryItem()->id], array_map(fn($row) => (int)$row['inventoryItemId'], $rows));
            $retry = $this->execute($schema, "mutation { save_Transfer(id: {$transfer['id']}, markAsPending: true) { id } }");
            self::assertNotEmpty($retry['errors']);
            self::assertSame(2, (int)(new Query())->from(Table::INVENTORYTRANSACTIONS)->where(['transferId' => $transfer['id']])->count());
        }
    }

    public function testDraftSaveRequiresDraftOrUnpublishedCreation(): void
    {
        $type = Plugin::getInstance()->getProductTypes()->getProductTypeByHandle('tShirts');
        $schema = $this->schema(["productTypes.$type->uid:read", "productTypes.$type->uid:create", "productTypes.$type->uid:save", 'elements.drafts:read']);
        $count = Product::find()->status(null)->count();
        $result = $this->execute($schema, 'mutation { save_tShirts_ProductDraft(title: "Not canonical") { id } }');
        self::assertSame('A draft ID is required unless creating an unpublished draft.', $result['errors'][0]['message']);
        self::assertSame($count, Product::find()->status(null)->count());

        $result = $this->execute($schema, 'mutation {
            save_tShirts_ProductDraft(asUnpublishedDraft: true, title: "Unpublished", sortOrder: ["new:1"], variants: [{sku: "unpublished", basePrice: 5}]) { id draftId isDraft isUnpublishedDraft }
        }');
        self::assertTrue($result['data']['save_tShirts_ProductDraft']['isDraft']);
        self::assertTrue($result['data']['save_tShirts_ProductDraft']['isUnpublishedDraft']);
        self::assertSame($count, Product::find()->status(null)->count());

        $result = $this->execute($schema, 'mutation { save_tShirts_Product(title: "Null variant", variants: [null]) { id } }');
        self::assertNotEmpty($result['errors']);
        self::assertStringNotContainsString('Something went wrong', $result['errors'][0]['message']);
    }

    public function testVariantDeletionUsesProductSavePermission(): void
    {
        $type = Plugin::getInstance()->getProductTypes()->getProductTypeByHandle('tShirts');
        $user = Craft::$app->getUsers()->getUserByUsernameOrEmail('customer2@crafttest.com');
        $permissions = ['editSite:' . Craft::$app->getSites()->getPrimarySite()->uid, "commerce-viewProductType:$type->uid"];
        $variant = Variant::find()->sku('hct-white')->one();

        Craft::$app->getUserPermissions()->saveUserPermissions($user->id, [...$permissions, "commerce-deleteProductType:$type->uid"]);
        $schema = Craft::$app->getGql()->getSchemaForUser(Craft::$app->getUsers()->getUserById($user->id));
        $result = $this->execute($schema, "mutation { deleteVariant(id: $variant->id) }");
        self::assertNotEmpty($result['errors']);
        self::assertNotNull(Variant::find()->id($variant->id)->one());

        Craft::$app->getUserPermissions()->saveUserPermissions($user->id, [...$permissions, "commerce-saveProductType:$type->uid"]);
        $schema = Craft::$app->getGql()->getSchemaForUser(Craft::$app->getUsers()->getUserById($user->id));
        $foreign = Variant::find()->sku('rad-hood')->one();
        $result = $this->execute($schema, "mutation { deleteVariant(id: $foreign->id) }");
        self::assertNotEmpty($result['errors'] ?? [], json_encode($result));
        $result = $this->execute($schema, "mutation { deleteVariant(id: $variant->id) }");
        self::assertArrayNotHasKey('errors', $result);
        self::assertTrue($result['data']['deleteVariant']);
        self::assertNull(Variant::find()->id($variant->id)->one());
    }

    public function testOrderAddressesCanBeClearedIndependently(): void
    {
        $uid = Plugin::getInstance()->getStores()->getPrimaryStore()->uid;
        $schema = $this->schema(["orders.$uid:create", "orders.$uid:save"]);
        $result = $this->execute($schema, 'mutation { save_Order(email: "addresses@example.com", billingAddress: {countryCode: "US", administrativeArea: "NY", locality: "New York", addressLine1: "1 Test", postalCode: "10001"}, shippingAddress: {countryCode: "CA", administrativeArea: "ON", locality: "Toronto", addressLine1: "2 Test", postalCode: "M5V 3L9"}) { id } }');
        self::assertArrayNotHasKey('errors', $result, json_encode($result));
        $id = $result['data']['save_Order']['id'];
        $result = $this->execute($schema, "mutation { save_Order(id: $id, billingAddress: null) { billingAddress { locality } shippingAddress { locality } } }");
        self::assertSame(['billingAddress' => null, 'shippingAddress' => ['locality' => 'Toronto']], $result['data']['save_Order']);
        $result = $this->execute($schema, "mutation { save_Order(id: $id, shippingAddress: null) { billingAddress { locality } shippingAddress { locality } } }");
        self::assertSame(['billingAddress' => null, 'shippingAddress' => null], $result['data']['save_Order']);
        $fresh = Order::find()->id($id)->one();
        self::assertNull($fresh->billingAddressId);
        self::assertNull($fresh->shippingAddressId);
    }

    public function testOrderCompletionErrorCanLeaveCompletedOrder(): void
    {
        $uid = Plugin::getInstance()->getStores()->getPrimaryStore()->uid;
        $schema = $this->schema(["orders.$uid:create", "orders.$uid:save"]);
        $id = $this->execute($schema, 'mutation { save_Order(email: "completion@example.com") { id } }')['data']['save_Order']['id'];
        $handler = function() {
            throw new RuntimeException('Completion event failed.');
        };

        Event::on(Order::class, Order::EVENT_AFTER_COMPLETE_ORDER, $handler);
        try {
            $result = $this->execute($schema, "mutation { save_Order(id: $id, message: \"Saved before completion\", isCompleted: true) { id } }");
            self::assertNotEmpty($result['errors']);
        } finally {
            Event::off(Order::class, Order::EVENT_AFTER_COMPLETE_ORDER, $handler);
        }
        $fresh = Order::find()->id($id)->one();
        self::assertSame('Saved before completion', $fresh->message);
        self::assertTrue($fresh->isCompleted);
        self::assertNotNull($fresh->dateOrdered);

        // A retry refetches the already-completed order instead of completing it a second time.
        $orderedAt = $fresh->dateOrdered;
        $result = $this->execute($schema, "mutation { save_Order(id: $id, isCompleted: true) { isCompleted } }");
        self::assertSame(['isCompleted' => true], $result['data']['save_Order']);
        self::assertEquals($orderedAt, Order::find()->id($id)->one()->dateOrdered);
    }

    public function testProductRevisionsIncludeHistoricalVariantsAndLatestRevision(): void
    {
        $type = Plugin::getInstance()->getProductTypes()->getProductTypeByHandle('tShirts');
        $product = Product::find()->typeId($type->id)->one();
        $first = Craft::$app->getRevisions()->createRevision($product, null, 'First', force: true);
        $second = Craft::$app->getRevisions()->createRevision(Product::find()->id($product->id)->one(), null, 'Second', force: true);
        $schema = $this->schema(["productTypes.$type->uid:read", "productTypes.$type->uid:save", 'elements.revisions:read', 'elements.inactive:read']);
        $result = $this->execute($schema, "{
            products(revisions: true, revisionOf: $product->id, status: null) { id variants { sku } }
            product(id: $product->id) { currentRevision { id revisionNotes } }
        }");
        self::assertCount(2, $result['data']['products']);
        foreach ($result['data']['products'] as $revision) {
            self::assertSame(['hct-white', 'hct-blue'], array_column($revision['variants'], 'sku'));
        }
        self::assertSame('Second', $result['data']['product']['currentRevision']['revisionNotes']);
        self::assertSame((string)$second, $result['data']['product']['currentRevision']['id']);
        self::assertNotSame($first, $second);

        // Canonical and historical variants must remain separate, even in the same eager-loading batch.
        $whiteId = $product->getVariants(true)->first()->id;
        $result = $this->execute($schema, "mutation { save_tShirts_Product(id: $product->id, sortOrder: [$whiteId]) { id } }");
        self::assertArrayNotHasKey('errors', $result);
        $result = $this->execute($schema, "{ products(id: [$product->id, $first], revisions: null, status: null) { id variants { sku } } }");
        $products = array_column($result['data']['products'], 'variants', 'id');
        self::assertSame([['sku' => 'hct-white']], $products[$product->id]);
        self::assertSame([['sku' => 'hct-white'], ['sku' => 'hct-blue']], $products[$first]);
    }

    private function schema(array $scope): GqlSchema
    {
        return new GqlSchema(['name' => 'Test', 'uid' => StringHelper::UUID(), 'scope' => $scope]);
    }

    private function execute(GqlSchema $schema, string $query): array
    {
        $gql = Craft::$app->getGql();
        $gql->flushCaches();
        Craft::$app->getUser()->setIdentity($schema->user);
        return $gql->executeQuery($schema, $query, debugMode: true);
    }
}
