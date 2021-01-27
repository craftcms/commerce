<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\fixtures;

use craft\base\ElementInterface;
use craft\commerce\elements\Product;
use craft\test\fixtures\elements\BaseElementFixture;

/**
 * Class ProductFixture.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.1.4
 */
class ProductFixture extends BaseElementFixture
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
    public $depends = [ProductTypeFixture::class, ProductTypesShippingCategoriesFixture::class, ShippingCategoryFixture::class, ProductTypesTaxCategoriesFixture::class, TaxCategoryFixture::class];

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

    /**
     * @inheritdoc
     */
    protected function createElement(): ElementInterface
    {
        return new Product();
    }
}
