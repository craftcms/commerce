<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\queue\jobs;

use craft\commerce\elements\Product;
use craft\commerce\elements\Variant;
use craft\queue\BaseJob;

/**
 * Resave Product Variants queue job
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.5.0
 */
class ResaveProductVariants extends BaseJob
{
    /**
     * @var int The product ID whose variants should be resaved
     */
    public int $productId;

    /**
     * @inheritdoc
     */
    public function execute($queue): void
    {
        $product = Product::find()
            ->id($this->productId)
            ->one();

        if (!$product) {
            return;
        }

        $variants = Variant::find()
            ->productId($this->productId)
            ->status(null)
            ->all();

        $total = count($variants);

        foreach ($variants as $i => $variant) {
            $this->setProgress($queue, $i / $total);
            \Craft::$app->getElements()->saveElement($variant);
        }
    }

    /**
     * @inheritdoc
     */
    protected function defaultDescription(): ?string
    {
        $product = Product::find()
            ->id($this->productId)
            ->one();

        if ($product) {
            return 'Resaving variants for product: ' . $product->title;
        }

        return 'Resaving product variants';
    }
}
