<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\fixtures;

use craft\commerce\records\ProductTypeShippingCategory;
use craft\test\Fixture;

/**
 * Class ShippingCategoryFixture
 * @package craftcommercetests\fixtures
 */
class ProductTypesShippingCategoriesFixture extends Fixture
{
    /**
     * @inheritdoc
     */
    public $dataFile = __DIR__.'/data/product-types-shipping-categories.php';

    /**
     * @inheritdoc
     */
    public $modelClass = ProductTypeShippingCategory::class;
}
