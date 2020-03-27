<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\fixtures;

use craft\commerce\elements\Variant;
use craft\test\fixtures\elements\ElementFixture;

/**
 * Class VariantFixture.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.x
 */
class VariantFixture extends ElementFixture
{
    /**
     * @inheritdoc
     */
    public $dataFile = __DIR__.'/data/variants.php';

    /**
     * @inheritdoc
     */
    public $modelClass = Variant::class;

    /**
     * @inheritdoc
     */
    public $depends = [ProductFixture::class];
}
