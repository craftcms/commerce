<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\services;

use Codeception\Test\Unit;
use craft\commerce\db\Table;
use craft\commerce\Plugin;
use craft\commerce\records\ShippingCategory;
use craft\commerce\services\ShippingCategories;
use craft\helpers\Db;
use craftcommercetests\fixtures\ProductFixture;
use UnitTester;

class ShippingCategoryTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected $tester;

    /**
     * @var ShippingCategories
     */
    protected $shippingCategories;

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

        $this->shippingCategories = Plugin::getInstance()->getShippingCategories();
    }

    public function testDeleteShippingCategory()
    {
        // Get the non-default shipping category from fixtures (anotherShippingCategory)
        $shippingCategory = ShippingCategory::find()
            ->where(['handle' => 'anotherShippingCategory'])
            ->one();

        $this->assertNotNull($shippingCategory, 'anotherShippingCategory fixture should exist');
        $this->assertFalse((bool)$shippingCategory->default, 'Test shipping category should not be default');

        $shippingCategoryId = $shippingCategory->id;

        $result = $this->shippingCategories->deleteShippingCategoryById($shippingCategoryId);

        $this->assertTrue($result);

        $shippingCategory = ShippingCategory::findOne($shippingCategoryId);

        $this->assertNull($shippingCategory);

        $shippingCategory = ShippingCategory::findTrashed()->where(['id' => $shippingCategoryId])->one();

        $this->assertInstanceOf(ShippingCategory::class, $shippingCategory);

        // Return shipping category to normal
        Db::update(Table::SHIPPINGCATEGORIES, ['dateDeleted' => null], ['id' => $shippingCategoryId]);
    }
}
