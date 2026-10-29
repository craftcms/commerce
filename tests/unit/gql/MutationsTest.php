<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\gql;

use Craft;
use craft\commerce\elements\Product;
use craft\commerce\elements\Variant;
use craft\commerce\Plugin;
use craft\enums\CmsEdition;
use craft\helpers\StringHelper;
use craft\models\GqlSchema;
use craft\test\TestCase;
use craftcommercetests\fixtures\OrdersFixture;

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
