<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\fixtures;

use craft\base\ElementInterface;
use craft\commerce\test\fixtures\elements\ProductFixture as BaseProductFixture;

/**
 * Class ProductFixture.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.1.4
 */
class ProductFixture extends BaseProductFixture
{
    /**
     * @inheritdoc
     */
    public $dataFile = __DIR__.'/data/products.php';

    /**
     * @inheritdoc
     */
    public $depends = [ProductTypeFixture::class];

    /**
     * @inheritdoc
     */
    protected function populateElement(ElementInterface $element, array $attributes): void
    {
        foreach ($attributes as $name => $value) {
            if ($name !== '_variants') {
                $element->$name = $value;
            } else {
                $element->setVariants($value);
            }
        }
    }
}
