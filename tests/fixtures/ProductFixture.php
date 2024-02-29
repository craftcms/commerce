<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\fixtures;

use Craft;
use craft\base\ElementInterface;
use craft\commerce\elements\Product;
use craft\commerce\elements\Variant;
use craft\commerce\elements\VariantCollection;
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
    public $dataFile = __DIR__ . '/data/products.php';

    /**
     * @inheritdoc
     */
    public $depends = [ProductTypeFixture::class];

    private ?VariantCollection $_variants = null;

    /**
     * @inheritdoc
     */
    protected function populateElement(ElementInterface $element, array $attributes): void
    {
        /** @var Product $element */
        foreach ($attributes as $name => $value) {
            if ($name !== '_variants') {
                $element->$name = $value;
            } else {
                $this->_variants = VariantCollection::make($value);
                $element->setVariants($value);
            }
        }
    }

    /**
     * @inheritdoc
     */
    protected function saveElement(ElementInterface $element): bool
    {
        $return = parent::saveElement($element);

        // Save the variants
        $this->_variants->each(function(Variant $v) use ($element) {
            $v->setPrimaryOwnerId($element->id);
            $v->setOwnerId($element->id);
            \Craft::$app->getElements()->saveElement($v,false);
        });

        $this->_variants = null;

        return $return;
    }

    /**
     * @inheritdoc
     */
    protected function deleteElement(ElementInterface $element): bool
    {
        /** @var Product $element */
        $variants = $element->getVariants(true);

        foreach ($variants as $variant) {
            Craft::$app->getElements()->deleteElement($variant, true);
        }

        return parent::deleteElement($element);
    }
}
