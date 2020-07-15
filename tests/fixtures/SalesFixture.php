<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\fixtures;

use craft\commerce\Plugin;
use craft\commerce\records\Sale;
use craft\test\Fixture;

/**
 * Sales Fixture
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.1.4
 */
class SalesFixture extends Fixture
{
    /**
     * @inheritdoc
     */
    public $dataFile = __DIR__.'/data/sales.php';

    /**
     * @inheritdoc
     */
    public $modelClass = Sale::class;

    /**
     * @var string[]
     */
    public $depends = [ProductFixture::class];

    /**
     * @inheritDoc
     */
    public function afterLoad()
    {
        parent::afterLoad();

        Plugin::getInstance()->getSales()->clearCaches();
    }
}