<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\fixtures;

use craft\commerce\records\ProductType;
use craft\test\Fixture;

/**
 * Product Type Fixture
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.x
 */
class ProductTypeFixture extends Fixture
{
    /**
     * @inheritdoc
     */
    public $dataFile = __DIR__.'/data/product-types.php';

    /**
     * @inheritdoc
     */
    public $modelClass = ProductType::class;

    /**
     * @inheritdoc
     */
    public $depends = [ProductTypeSitesFixture::class];
}