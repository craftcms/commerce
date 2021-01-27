<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\services;

use Codeception\Test\Unit;
use craft\commerce\elements\Product;
use craft\commerce\Plugin;
use craft\commerce\records\TaxCategory;
use craft\commerce\services\TaxCategories;
use craftcommercetests\fixtures\ProductFixture;
use UnitTester;


class TaxCategoryTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected $tester;

    /**
     * @var TaxCategories
     */
    protected $taxCategories;

    /**
     * @return array
     */
    public function _fixtures(): array
    {
        return [
            'products' => [
                'class' => ProductFixture::class
            ]
        ];
    }
    
    public function _before()
    {
        parent::_before();
        
        $this->taxCategories = Plugin::getInstance()->getTaxCategories();
    }
    
    public function testDeleteTaxCategory()
    {
        $product = Product::find()->where(['slug' => 'rad-hoodie'])->one();
        
        $taxCategoryId = $product->getTaxCategory()->id;
        
        $result = $this->taxCategories->deleteTaxCategoryById($taxCategoryId);
        
        $this->assertTrue($result);
        
        $taxCategory = TaxCategory::findOne($taxCategoryId);
        
        $this->assertNull($taxCategory);

        $taxCategory = TaxCategory::findTrashed()->where(['id' => $taxCategoryId])->one();
        
        $this->assertInstanceOf(TaxCategory::class, $taxCategory);
    }
}
