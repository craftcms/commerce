<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\fixtures;

use craft\commerce\records\SaleCategory;
use craft\test\Fixture;

/**
 * Sale Categories Fixture
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.1.4
 */
class SaleCategoriesFixture extends Fixture
{
    /**
     * @inheritdoc
     */
    public $dataFile = __DIR__.'/data/sale-categories.php';

    /**
     * @inheritdoc
     */
    public $modelClass = SaleCategory::class;

    public $depends = [SalesFixture::class, CategoriesFixture::class];
}