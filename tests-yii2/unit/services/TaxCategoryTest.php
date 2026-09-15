<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\services;

use Codeception\Test\Unit;
use craft\commerce\db\Table;
use craft\commerce\elements\Product;
use craft\commerce\Plugin;
use craft\commerce\services\TaxCategories;
use craft\helpers\Db;
use CraftCms\Commerce\Tax\Models\TaxCategory;
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
                'class' => ProductFixture::class,
            ],
        ];
    }

    #[\Override]
    public function _before()
    {
        parent::_before();

        $this->taxCategories = Plugin::getInstance()->getTaxCategories();
    }

    public function testDeleteTaxCategory()
    {
        $product = Product::find()->where(['slug' => 'rad-hoodie'])->one();

        $variant = $product->getVariants()->first();
        $taxCategoryId = $variant->getTaxCategory()->id;

        $result = $this->taxCategories->deleteTaxCategoryById($taxCategoryId);

        $this->assertTrue($result);

        $taxCategory = TaxCategory::find($taxCategoryId);

        $this->assertNull($taxCategory);

        $taxCategory = TaxCategory::onlyTrashed()->where('id', $taxCategoryId)->first();

        $this->assertInstanceOf(TaxCategory::class, $taxCategory);

        // Return tax category to normal
        Db::update(Table::TAXCATEGORIES, ['dateDeleted' => null], ['id' => $taxCategoryId]);
    }
}
