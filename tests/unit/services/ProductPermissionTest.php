<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit;

use Codeception\Stub;
use Codeception\Test\Unit;
use Craft;
use craft\commerce\db\Table;
use craft\commerce\elements\Product;
use craft\commerce\elements\Variant;
use craft\commerce\models\ProductType;
use craft\commerce\models\Sale;
use craft\commerce\Plugin;
use craft\commerce\services\Customers;
use craft\commerce\services\Products;
use craft\commerce\services\Sales;
use craft\config\GeneralConfig;
use craft\console\Application;
use craft\db\Query;
use craft\elements\Category;
use craft\elements\User;
use craft\helpers\ArrayHelper;
use craft\services\Config;
use craft\services\UserPermissions;
use craftcommercetests\fixtures\SalesFixture;
use UnitTester;

/**
 * SalesTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.1.4
 */
class ProductPermissionTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected $tester;

    /**
     * @var Products
     */
    protected $products;

    public function testCanAdminUserAbleToEditProduct()
    {

        $user = new User();
        $user->id = 1;
        $user->admin = true;

        $product = $this->make(Product::class, ['getType' => $this->make(ProductType::class, ['id' => 1, 'uid' => 'randomuid']) ]);

        $this->assertTrue($this->products->hasPermission($user, $product));

        $user->admin = false;
        $this->assertFalse($this->products->hasPermission($user, $product));
    }

    public function testCanAUserEditThisProduct()
    {
        $user = new User();
        $user->id = 1;
        $user->admin = false;

        $product = $this->make(Product::class, ['getType' => $this->make(ProductType::class, ['id' => 1, 'uid' => 'randomuid']) ]);

        $this->mockPermissions([]);

        $this->assertFalse($this->products->hasPermission($user, $product));

        $this->mockPermissions(['commerce-editproducttype:randomuid']);
        $this->assertTrue($this->products->hasPermission($user, $product));

        // if user has access to another product type
        $this->mockPermissions(['commerce-editProductType:anotherrandomuid']);
        $this->assertFalse($this->products->hasPermission($user, $product));
    }

    public function testCanAUserCreateOrDeleteAProduct()
    {
        $user = new User();
        $user->id = 1;
        $user->admin = false;

        $product = $this->make(Product::class, ['getType' => $this->make(ProductType::class, ['id' => 1, 'uid' => 'randomuid']) ]);

        // User has no create product permission on a specific product type.
        $this->mockPermissions(['commerce-editproducttype:randomuid']);

        $this->assertFalse($this->products->hasPermission($user, $product, 'commerce-createProducts'));

        // User has create product permission on a specific product type.
        $this->mockPermissions(['commerce-editproducttype:randomuid', 'commerce-createproducts']);

        $this->assertTrue($this->products->hasPermission($user, $product, 'commerce-createProducts'));

        // User has no delete product permission on a specific product type.
        $this->mockPermissions(['commerce-editproducttype:randomuid']);

        $this->assertFalse($this->products->hasPermission($user, $product, 'commerce-deleteProducts'));

        // User has delete product permission on a specific product type.
        $this->mockPermissions(['commerce-editproducttype:randomuid', 'commerce-deleteproducts']);

        $this->assertTrue($this->products->hasPermission($user, $product, 'commerce-deleteProducts'));
    }

    private function mockPermissions(array $permissions = [])
    {
        $this->tester->mockMethods(
            Craft::$app,
            'userPermissions',
            [
                'getPermissionsByUserId' => function() use ($permissions) { return $permissions; }
            ],
            []
        );
    }

    protected function _before()
    {
        parent::_before();

        $this->products = Plugin::getInstance()->getProducts();
    }
}
