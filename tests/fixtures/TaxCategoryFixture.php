<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\fixtures;

use craft\commerce\records\TaxCategory;
use craft\test\ActiveFixture;

/**
 * Class TaxCategoryFixture
 * @package craftcommercetests\fixtures
 */
class TaxCategoryFixture extends ActiveFixture
{
    /**
     * @inheritdoc
     */
    public $dataFile = __DIR__.'/data/tax-category.php';

    /**
     * @inheritdoc
     */
    public $modelClass = TaxCategory::class;
}
