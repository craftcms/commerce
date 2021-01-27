<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\fixtures;

use craft\commerce\records\ProductTypeTaxCategory;
use craft\test\Fixture;

/**
 * Class ProductTypesTaxCategoriesFixture
 * @package craftcommercetests\fixtures
 */
class ProductTypesTaxCategoriesFixture extends Fixture
{
    /**
     * @inheritdoc
     */
    public $dataFile = __DIR__.'/data/product-types-tax-categories.php';

    /**
     * @inheritdoc
     */
    public $modelClass = ProductTypeTaxCategory::class;
}
