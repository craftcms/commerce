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
use craft\elements\User;
use UnitTester;

/**
 * ProductPermissionTest
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

    public function testCanViewWithNoPermissions()
    {
        [$user, $product] = $this->_existingProduct();

        $this->mockPermissions([]);
        $this->assertFalse($product->canView($user));
    }

    public function testCanViewWithViewPermission()
    {
        [$user, $product] = $this->_existingProduct();

        $this->mockPermissions(['commerce-viewproducttype:randomuid']);
        $this->assertTrue($product->canView($user));
    }

    public function testCanViewWithWrongProductType()
    {
        [$user, $product] = $this->_existingProduct();

        $this->mockPermissions(['commerce-viewproducttype:anotherrandomuid']);
        $this->assertFalse($product->canView($user));
    }

    public function testCanViewWithOnlySavePermission()
    {
        [$user, $product] = $this->_existingProduct();

        // Save without view should not grant view
        $this->mockPermissions(['commerce-saveproducttype:randomuid']);
        $this->assertFalse($product->canView($user));
    }

    public function testCanSaveExistingProductWithSavePermission()
    {
        [$user, $product] = $this->_existingProduct();

        $this->mockPermissions(['commerce-viewproducttype:randomuid', 'commerce-saveproducttype:randomuid']);
        $this->assertTrue($product->canSave($user));
    }

    public function testCannotSaveExistingProductWithViewOnly()
    {
        [$user, $product] = $this->_existingProduct();

        $this->mockPermissions(['commerce-viewproducttype:randomuid']);
        $this->assertFalse($product->canSave($user));
    }

    public function testCannotSaveExistingProductWithCreatePermission()
    {
        [$user, $product] = $this->_existingProduct();

        // Create permission does not grant save on existing products
        $this->mockPermissions(['commerce-viewproducttype:randomuid', 'commerce-createproducttype:randomuid']);
        $this->assertFalse($product->canSave($user));
    }

    public function testCanSaveNewProductWithCreatePermission()
    {
        [$user, $product] = $this->_newProduct();

        $this->mockPermissions(['commerce-viewproducttype:randomuid', 'commerce-createproducttype:randomuid']);
        $this->assertTrue($product->canSave($user));
    }

    public function testCannotSaveNewProductWithViewOnly()
    {
        [$user, $product] = $this->_newProduct();

        $this->mockPermissions(['commerce-viewproducttype:randomuid']);
        $this->assertFalse($product->canSave($user));
    }

    public function testCannotSaveNewProductWithSavePermission()
    {
        [$user, $product] = $this->_newProduct();

        // Save permission does not grant create on new products
        $this->mockPermissions(['commerce-viewproducttype:randomuid', 'commerce-saveproducttype:randomuid']);
        $this->assertFalse($product->canSave($user));
    }

    public function testCanDeleteWithDeletePermission()
    {
        [$user, $product] = $this->_existingProduct();

        $this->mockPermissions(['commerce-viewproducttype:randomuid', 'commerce-deleteproducttype:randomuid']);
        $this->assertTrue($product->canDelete($user));
    }

    public function testCannotDeleteWithViewOnly()
    {
        [$user, $product] = $this->_existingProduct();

        $this->mockPermissions(['commerce-viewproducttype:randomuid']);
        $this->assertFalse($product->canDelete($user));
    }

    public function testCannotDeleteWithSavePermission()
    {
        [$user, $product] = $this->_existingProduct();

        // Save permission does not grant delete
        $this->mockPermissions(['commerce-viewproducttype:randomuid', 'commerce-saveproducttype:randomuid']);
        $this->assertFalse($product->canDelete($user));
    }

    public function testCanDuplicateWithCreateAndSave()
    {
        [$user, $product] = $this->_existingProduct();

        $this->mockPermissions([
            'commerce-viewproducttype:randomuid',
            'commerce-createproducttype:randomuid',
            'commerce-saveproducttype:randomuid',
        ]);
        $this->assertTrue($product->canDuplicate($user));
    }

    public function testCannotDuplicateWithCreateOnly()
    {
        [$user, $product] = $this->_existingProduct();

        $this->mockPermissions(['commerce-viewproducttype:randomuid', 'commerce-createproducttype:randomuid']);
        $this->assertFalse($product->canDuplicate($user));
    }

    public function testCannotDuplicateWithSaveOnly()
    {
        [$user, $product] = $this->_existingProduct();

        $this->mockPermissions(['commerce-viewproducttype:randomuid', 'commerce-saveproducttype:randomuid']);
        $this->assertFalse($product->canDuplicate($user));
    }

    public function testCanCreateDraftsAlwaysReturnsTrue()
    {
        [$user, $product] = $this->_existingProduct();

        $this->mockPermissions([]);
        $this->assertTrue($product->canCreateDrafts($user));
    }

    public function testAdminBypassesAllPermissions()
    {
        $user = new User();
        $user->id = 1;
        $user->admin = true;

        $product = $this->make(Product::class, [
            'id' => 100,
            'getType' => $this->_makeProductType(),
        ]);

        $this->mockPermissions([]);
        $this->assertTrue($product->canView($user));
        $this->assertTrue($product->canSave($user));
        $this->assertTrue($product->canDelete($user));
        $this->assertTrue($product->canDuplicate($user));
    }

    /**
     * @return array{User, Product}
     */
    private function _existingProduct(): array
    {
        $user = new User();
        $user->id = 1;
        $user->admin = false;

        $product = $this->make(Product::class, [
            'id' => 100,
            'getType' => $this->_makeProductType(),
        ]);

        return [$user, $product];
    }

    /**
     * @return array{User, Product}
     */
    private function _newProduct(): array
    {
        $user = new User();
        $user->id = 1;
        $user->admin = false;

        $product = $this->make(Product::class, [
            'getType' => $this->_makeProductType(),
        ]);

        return [$user, $product];
    }

    private function _makeProductType(): ProductType
    {
        return $this->make(ProductType::class, ['id' => 1, 'uid' => 'randomuid']);
    }

    private function mockPermissions(array $permissions = []): void
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
}
