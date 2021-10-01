<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\fixtures;

use craft\test\fixtures\elements\UserFixture;

/**
 * Class CustomerFixture.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @author Global Network Group | Giel Tettelaar <giel@yellowflash.net>
 * @since 2.1
 */
class CustomerFixture extends UserFixture
{
    /**
     * @inheritdoc
     */
    public $dataFile = __DIR__.'/data/customers.php';

    /**
     * @inheritdoc
     */
    public $depends = [FieldLayoutFixture::class];
}
