<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\fixtures;

use craft\commerce\records\Discount;
use craft\test\ActiveFixture;

/**
 * Class DiscountsFixture.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @author Global Network Group | Giel Tettelaar <giel@yellowflash.net>
 * @since 2.1
 */
class DiscountsFixture extends ActiveFixture
{
    /**
     * @inheritdoc
     */
    public $dataFile = __DIR__.'/data/discounts.php';

    /**
     * @inheritdoc
     */
    public $modeClass = Discount::class;
}
