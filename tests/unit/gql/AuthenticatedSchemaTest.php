<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\gql;

use Craft;
use craft\commerce\Plugin;
use craft\elements\User;
use craft\enums\CmsEdition;
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
        self::assertSame(0, $this->execute(Craft::$app->getGql()->getSchemaForUser($other), $query)['data']['orderCount']);

        // Users who can manage orders can query everyone’s
        $other = $this->user('customer2@crafttest.com', ['commerce-manageOrders']);
        self::assertSame(3, $this->execute(Craft::$app->getGql()->getSchemaForUser($other), $query)['data']['orderCount']);
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
        return $gql->executeQuery($schema, $query);
    }
}
