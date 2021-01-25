<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\fixtures;

use craft\commerce\records\ShippingCategory;
use craft\test\Fixture;

/**
 * Class ShippingCategoryFixture
 * @package craftcommercetests\fixtures
 */
class ShippingCategoryFixture extends Fixture
{
    /**
     * @inheritdoc
     */
    public $dataFile = __DIR__.'/data/shipping-category.php';

    /**
     * @inheritdoc
     */
    public $modelClass = ShippingCategory::class;
}
