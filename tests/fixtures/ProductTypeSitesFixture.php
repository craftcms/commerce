<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\fixtures;

use craft\commerce\records\ProductType;
use craft\commerce\records\ProductTypeSite;
use craft\test\Fixture;

/**
 * Product Type Sites Fixture
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.1.4
 */
class ProductTypeSitesFixture extends Fixture
{
    /**
     * @inheritdoc
     */
    public $dataFile = __DIR__.'/data/product-types-sites.php';

    /**
     * @inheritdoc
     */
    public $modelClass = ProductTypeSite::class;
}