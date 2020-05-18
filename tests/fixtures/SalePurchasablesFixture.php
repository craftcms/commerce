<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\fixtures;

use craft\commerce\records\SalePurchasable;
use craft\test\Fixture;

/**
 * Sale Purchasables Fixture
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.1.4
 */
class SalePurchasablesFixture extends Fixture
{
    /**
     * @inheritdoc
     */
    public $dataFile = __DIR__.'/data/sale-purchasables.php';

    /**
     * @inheritdoc
     */
    public $modelClass = SalePurchasable::class;

    public $depends = [SalesFixture::class];
}