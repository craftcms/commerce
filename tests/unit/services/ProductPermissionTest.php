<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\services;

use Codeception\Test\Unit;
use Craft;
use craft\commerce\elements\Product;
use craft\commerce\models\ProductType;
use craft\commerce\Plugin;
use craft\commerce\services\ProductTypes;
use craft\elements\User;
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
     * @var ProductTypes
     */
    protected ProductTypes $productTypes;


    public function testCanAUserCreateOrDeleteAProduct()
    {
        $user = new User();
        $user->id = 1;
        $user->admin = false;

        $product = $this->make(Product::class, ['getType' => $this->make(ProductType::class, ['id' => 1, 'uid' => 'randomuid']) ]);

        // User has no create product permission on a specific product type.
        $this->mockPermissions(['commerce-editproducttype:randomuid']);

        $this->assertFalse($this->productTypes->hasPermission($user, $product->getType(), 'commerce-createProducts'));

        // User has create product permission on a specific product type.
        $this->mockPermissions(['commerce-editproducttype:randomuid', 'commerce-createproducts:randomuid']);

        $this->assertTrue($this->productTypes->hasPermission($user, $product->getType(), 'commerce-createProducts'));

        // User has no delete product permission on a specific product type.
        $this->mockPermissions(['commerce-editproducttype:randomuid']);

        $this->assertFalse($this->productTypes->hasPermission($user, $product->getType(), 'commerce-deleteProducts:randomuid'));

        // User has delete product permission on a specific product type.
        $this->mockPermissions(['commerce-editproducttype:randomuid', 'commerce-deleteproducts:randomuid']);

        $this->assertTrue($this->productTypes->hasPermission($user, $product->getType(), 'commerce-deleteProducts'));
    }

    public function testCanAUserEditThisProduct()
    {
        $user = new User();
        $user->id = 1;
        $user->admin = false;

        $product = $this->make(Product::class, ['getType' => $this->make(ProductType::class, ['id' => 1, 'uid' => 'randomuid'])]);

        $this->mockPermissions([]);

        $this->assertFalse($this->productTypes->hasPermission($user, $product->getType()));

        $this->mockPermissions(['commerce-editproducttype:randomuid']);
        $this->assertTrue($this->productTypes->hasPermission($user, $product->getType(), 'commerce-editproducttype'));

        // if user has access to another product type
        $this->mockPermissions(['commerce-editProductType:anotherrandomuid']);
        $this->assertFalse($this->productTypes->hasPermission($user, $product->getType(), 'commerce-editproducttype'));
    }

    public function testCanAdminUserAbleToEditProduct()
    {
        $user = new User();
        $user->id = 1;
        $user->admin = true;

        $product = $this->make(Product::class, ['getType' => $this->make(ProductType::class, ['id' => 1, 'uid' => 'randomuid'])]);

        $this->assertTrue($this->productTypes->hasPermission($user, $product->getType(), 'commerce-createProducts'));

        $user->admin = false;
        $this->assertFalse($this->productTypes->hasPermission($user, $product->getType(), 'commerce-createProducts'));
    }

    private function mockPermissions(array $permissions = [])
    {
        $this->tester->mockMethods(
            Craft::$app,
            'userPermissions',
            [
                'getPermissionsByUserId' => fn() => $permissions,
            ],
            []
        );
    }

    protected function _before()
    {
        parent::_before();

        $this->productTypes = Plugin::getInstance()->getProductTypes();
    }
}
