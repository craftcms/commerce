<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Catalog\Jobs;

use CraftCms\Cms\Element\Elements;
use CraftCms\Cms\Queue\Job;
use CraftCms\Commerce\Catalog\Elements\Product;
use CraftCms\Commerce\Catalog\Elements\Variant;

class ResaveProductVariantsJob extends Job
{
    public function __construct(
        public readonly int $productId,
    ) {
        parent::__construct();
    }

    public function handle(): void
    {
        $product = $this->getProduct();

        if (!$product) {
            return;
        }

        $variants = Variant::find()
            ->productId($this->productId)
            ->status(null)
            ->all();

        $total = count($variants);

        foreach ($variants as $i => $variant) {
            $this->setProgress((int) ($i / $total * 100));
            app(Elements::class)->saveElement($variant);
        }
    }

    #[\Override]
    protected function defaultDescription(): string
    {
        $product = $this->getProduct();

        return $product ? 'Resaving variants for product: ' . $product->title : 'Resaving product variants';
    }

    private function getProduct(): ?Product
    {
        return Product::find()->id($this->productId)->one();
    }
}
