<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\fixtures;

use craft\commerce\records\Customer;
use craft\test\Fixture;

/**
 * Class CustomerFixture.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @author Global Network Group | Giel Tettelaar <giel@yellowflash.net>
 * @since 2.1
 */
class CustomerFixture extends Fixture
{
    /**
     * @inheritdoc
     */
    public $dataFile = __DIR__.'/data/customers.php';

    /**
     * @inheritdoc
     */
    public $modelClass = Customer::class;
}
