<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\fixtures;

use craft\commerce\elements\Product;
use craftcommercetests\fixtures\elements\ProductFixture as BaseProductFixture;

/**
 * Class ProductFixture.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.1.4
 * @method Product getElement(string $key)
 */
class ProductFixture extends BaseProductFixture
{
    /**
     * @inheritdoc
     */
    public $dataFile = __DIR__ . '/data/products.php';

    /**
     * @inheritdoc
     */
    public $depends = [ProductTypeFixture::class];
}
