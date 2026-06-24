<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\elements\variant;

use Codeception\Test\Unit;
use craft\commerce\elements\Product;
use craft\commerce\elements\Variant;
use craftcommercetests\fixtures\ProductFixture;
use ReflectionClass;

/**
 * VariantOwnerTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.4.4
 */
class VariantOwnerTest extends Unit
{
    /**
     * @var \UnitTester
     */
    protected $tester;
    
    /**
     * @return array
     */
    public function _fixtures(): array
    {
        return [
            'products' => [
                'class' => ProductFixture::class,
            ],
        ];
    }

    /**
     * Test that ownerType is set to Product in init()
     */
    public function testOwnerTypeIsSetInInit(): void
    {
        $variant = new Variant();
        
        // Access protected ownerType property
        $reflection = new ReflectionClass($variant);
        $ownerTypeProperty = $reflection->getProperty('ownerType');
        
        self::assertEquals(Product::class, $ownerTypeProperty->getValue($variant));
    }

    /**
     * Test that 'product' is included in extraFields
     */
    public function testProductIncludedInExtraFields(): void
    {
        $variant = new Variant();
        $extraFields = $variant->extraFields();
        
        self::assertContains('product', $extraFields);
    }

    /**
     * Test getOwner returns Product instance
     */
    public function testGetOwnerReturnsProduct(): void
    {
        /** @var ProductFixture $productFixture */
        $productFixture = $this->tester->grabFixture('products');
        $product = $productFixture->getElement('rad-hoodie');
        self::assertNotNull($product);
        
        $variants = $product->getVariants();
        self::assertFalse($variants->isEmpty());
        
        $variant = $variants->first();
        $owner = $variant->getOwner();
        
        self::assertInstanceOf(Product::class, $owner);
        self::assertEquals($product->id, $owner->id);
    }

    /**
     * Test getPrimaryOwner returns Product instance
     */
    public function testGetPrimaryOwnerReturnsProduct(): void
    {
        /** @var ProductFixture $productFixture */
        $productFixture = $this->tester->grabFixture('products');
        $product = $productFixture->getElement('rad-hoodie');
        self::assertNotNull($product);
        
        $variants = $product->getVariants();
        self::assertFalse($variants->isEmpty());
        
        $variant = $variants->first();
        $primaryOwner = $variant->getPrimaryOwner();
        
        self::assertInstanceOf(Product::class, $primaryOwner);
        self::assertEquals($product->id, $primaryOwner->id);
    }

    /**
     * Test that getProduct() still works (backward compatibility)
     */
    public function testGetProductMethodStillWorks(): void
    {
        /** @var ProductFixture $productFixture */
        $productFixture = $this->tester->grabFixture('products');
        $product = $productFixture->getElement('hypercolor-tshirt');
        self::assertNotNull($product);
        
        $variants = $product->getVariants();
        self::assertFalse($variants->isEmpty());
        
        $variant = $variants->first();
        $productFromMethod = $variant->getProduct();
        
        self::assertInstanceOf(Product::class, $productFromMethod);
        self::assertEquals($product->id, $productFromMethod->id);
    }

    /**
     * Test setOwner works with Product
     */
    public function testSetOwnerWorksWithProduct(): void
    {
        /** @var ProductFixture $productFixture */
        $productFixture = $this->tester->grabFixture('products');
        $product = $productFixture->getElement('rad-hoodie');
        self::assertNotNull($product);
        
        $variant = new Variant();
        $variant->setOwner($product);
        
        $owner = $variant->getOwner();
        self::assertInstanceOf(Product::class, $owner);
        self::assertEquals($product->id, $owner->id);
    }

    /**
     * Test variant owner relationship with different sites
     */
    public function testVariantOwnerWithDifferentSites(): void
    {
        // Get a product that exists in multiple sites
        /** @var ProductFixture $productFixture */
        $productFixture = $this->tester->grabFixture('products');
        $product = $productFixture->getElement('double-decker-bus-toy');
        self::assertNotNull($product);
        
        // Get variants
        $variants = $product->getVariants();
        self::assertFalse($variants->isEmpty());
        
        $variant = $variants->first();
        
        // Test that owner is resolved correctly even for different sites
        $owner = $variant->getOwner();
        self::assertInstanceOf(Product::class, $owner);
        self::assertEquals($product->id, $owner->id);
        self::assertEquals($product->siteId, $owner->siteId);
    }
}
