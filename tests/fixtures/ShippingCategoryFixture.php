<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\fixtures;

use craft\commerce\records\ShippingCategory;
use craft\test\ActiveFixture;

/**
 * Class ShippingCategoryFixture
 * @package craftcommercetests\fixtures
 */
class ShippingCategoryFixture extends ActiveFixture
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
