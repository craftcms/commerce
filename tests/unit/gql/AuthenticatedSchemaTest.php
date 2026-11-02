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
use craft\commerce\Plugin;
use craft\elements\User;
use craft\enums\CmsEdition;
use craft\helpers\StringHelper;
use craft\models\GqlSchema;
use craft\test\TestCase;
use craftcommercetests\fixtures\OrdersFixture;

/**
 * Tests Commerce’s schema components for GraphQL requests authenticated as a user.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.8.0
 */
class AuthenticatedSchemaTest extends TestCase
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

    public function testProductsAreLimitedToViewableProductTypes(): void
    {
        $tShirts = Plugin::getInstance()->getProductTypes()->getProductTypeByHandle('tShirts');
        $user = $this->user('customer2@crafttest.com', ["commerce-viewProductType:$tShirts->uid"]);

        $schema = Craft::$app->getGql()->getSchemaForUser($user);
        self::assertContains("productTypes.$tShirts->uid:read", $schema->scope);
        self::assertNotContains('settings.producttypes:read', $schema->scope);

        $result = $this->execute($schema, '{ products { title } variants { sku } }');
        self::assertSame(['Hypercolor T-Shirt'], array_column($result['data']['products'], 'title'));
        $skus = array_column($result['data']['variants'], 'sku');
        sort($skus);
        self::assertSame(['hct-blue', 'hct-white'], $skus);
    }

    public function testCustomersCanOnlyQueryTheirOwnOrders(): void
    {
        $query = '{ orderCount stores { handle } }';

        $owner = $this->user('customer1@crafttest.com', []);
        $ownerSchema = Craft::$app->getGql()->getSchemaForUser($owner);
        self::assertNotContains('settings.producttypes:read', $ownerSchema->scope);
        $result = $this->execute($ownerSchema, $query);
        self::assertSame(3, $result['data']['orderCount']);
        self::assertNotEmpty($result['data']['stores']);

        $other = $this->user('customer2@crafttest.com', []);
        $otherSchema = Craft::$app->getGql()->getSchemaForUser($other);
        self::assertSame(0, $this->execute($otherSchema, $query)['data']['orderCount']);
        $orderId = Order::find()->customerId($owner->id)->one()->id;
        $result = $this->execute($otherSchema, "{ orders(customerId: $owner->id) { id } order(id: $orderId) { id } orderCount(customerId: $owner->id) }");
        self::assertSame(['orders' => [], 'order' => null, 'orderCount' => 0], $result['data']);

        $primaryStore = Plugin::getInstance()->getStores()->getPrimaryStore();
        $otherStore = Plugin::getInstance()->getStores()->getStoreByHandle('ukStore');
        $storeSchema = new GqlSchema(['name' => 'Primary store', 'uid' => StringHelper::UUID(), 'scope' => ["orders.$primaryStore->uid:read"]]);
        $result = $this->execute($storeSchema, "{ orders(storeId: $otherStore->id) { id } order(storeId: $otherStore->id) { id } orderCount(storeId: $otherStore->id) }");
        self::assertSame(['orders' => [], 'order' => null, 'orderCount' => 0], $result['data']);

        // Users who can manage orders can query everyone’s
        $other = $this->user('customer2@crafttest.com', ['commerce-manageOrders']);
        self::assertSame(3, $this->execute(Craft::$app->getGql()->getSchemaForUser($other), $query)['data']['orderCount']);
    }

    public function testProvisionalDraftsArePrivateButSavedDraftsAreShared(): void
    {
        $type = Plugin::getInstance()->getProductTypes()->getProductTypeByHandle('tShirts');
        $permissions = [
            'accessCp',
            'editSite:' . Craft::$app->getSites()->getPrimarySite()->uid,
            "commerce-viewProductType:$type->uid",
            "commerce-saveProductType:$type->uid",
        ];
        $owner = $this->user('customer1@crafttest.com', $permissions);
        $peer = $this->user('customer2@crafttest.com', $permissions);
        $product = Product::find()->typeId($type->id)->one();
        $variantId = $product->getVariants(true)->first()->id;
        $blueId = (string)$product->getVariants(true)->last()->id;
        $ownerSchema = Craft::$app->getGql()->getSchemaForUser($owner);
        $draftId = $this->execute($ownerSchema, "mutation { createProductDraft(id: $product->id, provisional: true, name: \"Private\") }")['data']['createProductDraft'];
        $result = $this->execute($ownerSchema, "mutation {
            save_tShirts_ProductDraft(draftId: $draftId, provisional: true, title: \"Private title\", variants: [{id: $variantId, basePrice: 87}]) { id title variants { id price } }
        }");
        self::assertSame('Private title', $result['data']['save_tShirts_ProductDraft']['title']);
        $privateProductId = $result['data']['save_tShirts_ProductDraft']['id'];
        $privateVariantId = $result['data']['save_tShirts_ProductDraft']['variants'][0]['id'];
        self::assertSame([['id' => $privateVariantId, 'price' => 87.0]], $this->execute($ownerSchema, "{ variants(id: $privateVariantId, status: null) { id price } }")['data']['variants']);
        self::assertSame([['id' => $privateVariantId], ['id' => $blueId]], $this->execute($ownerSchema, "{ variants(productId: $privateProductId, status: null) { id } }")['data']['variants']);

        $peerSchema = Craft::$app->getGql()->getSchemaForUser($peer);
        $result = $this->execute($peerSchema, "{
            products(draftId: $draftId, provisionalDrafts: true, status: null) { title }
            variants(id: $privateVariantId, status: null) { id price }
            draftVariants: variants(productId: $privateProductId, status: null) { id productTitle product { title } }
            product(id: $product->id) { drafts(provisionalDrafts: true) { draftId title variants { price } } }
        }");
        self::assertSame([], $result['data']['products']);
        self::assertSame([], $result['data']['variants']);
        self::assertSame([], $result['data']['draftVariants']);
        self::assertSame([], $result['data']['product']['drafts']);

        foreach ([
            "save_tShirts_ProductDraft(draftId: $draftId, provisional: true, title: \"Stolen\") { id }",
            "publishProductDraft(id: $draftId, provisional: true)",
            "save_tShirts_Variant(id: $privateVariantId, basePrice: 1) { id }",
            "deleteVariant(id: $privateVariantId)",
        ] as $mutation) {
            $result = $this->execute($peerSchema, "mutation { $mutation }");
            self::assertNotEmpty($result['errors']);
        }
        self::assertSame($product->title, Product::find()->id($product->id)->one()->title);

        // The owner can still publish their provisional draft.
        self::assertSame((string)$product->id, $this->execute($ownerSchema, "mutation { publishProductDraft(id: $draftId, provisional: true) }")['data']['publishProductDraft']);
        self::assertSame('Private title', Product::find()->id($product->id)->one()->title);
        self::assertEquals(87, Product::find()->id($product->id)->one()->getVariants(true)->first()->basePrice);

        // Saved drafts deliberately remain collaborative.
        $savedId = $this->execute($ownerSchema, "mutation { createProductDraft(id: $product->id, name: \"Shared\") }")['data']['createProductDraft'];
        $result = $this->execute($peerSchema, "mutation { save_tShirts_ProductDraft(draftId: $savedId, title: \"Shared edit\") { id title } }");
        self::assertSame('Shared edit', $result['data']['save_tShirts_ProductDraft']['title']);
        $sharedProductId = $result['data']['save_tShirts_ProductDraft']['id'];
        self::assertSame([['id' => (string)$variantId], ['id' => $blueId]], $this->execute($peerSchema, "{ variants(productId: $sharedProductId, status: null) { id } }")['data']['variants']);
        self::assertSame((string)$product->id, $this->execute($peerSchema, "mutation { publishProductDraft(id: $savedId) }")['data']['publishProductDraft']);
    }

    private function user(string $email, array $permissions): User
    {
        $user = Craft::$app->getUsers()->getUserByUsernameOrEmail($email);
        Craft::$app->getUserPermissions()->saveUserPermissions($user->id, $permissions);
        return Craft::$app->getUsers()->getUserById($user->id);
    }

    private function execute(GqlSchema $schema, string $query): array
    {
        $gql = Craft::$app->getGql();
        $gql->flushCaches();
        Craft::$app->getUser()->setIdentity($schema->user);
        return $gql->executeQuery($schema, $query, debugMode: true);
    }
}
