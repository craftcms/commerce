<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\fixtures;

use craft\commerce\elements\Product;
use craft\test\fixtures\elements\ElementFixture;

/**
 * Class ProductFixture.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.x
 */
class ProductFixture extends ElementFixture
{
    /**
     * @inheritdoc
     */
    public $dataFile = __DIR__.'/data/products.php';

    /**
     * @inheritdoc
     */
    public $modelClass = Product::class;

    /**
     * @inheritdoc
     */
    public $depends = [ProductTypeFixture::class];
}
