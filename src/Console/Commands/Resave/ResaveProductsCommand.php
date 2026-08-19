<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Console\Commands\Resave;

use CraftCms\Cms\Element\Commands\Resave\ResaveCommand;
use CraftCms\Commerce\Catalog\Elements\Product;
use CraftCms\Commerce\Catalog\ProductType\Data\ProductType;
use CraftCms\Commerce\Catalog\ProductType\ProductTypes;
use Override;

class ResaveProductsCommand extends ResaveCommand
{
    #[Override]
    protected $signature = 'craft:resave:products'
        . self::DEFAULT_OPTIONS
        . '
        {--type= : The product type handle(s) of the products to resave, comma-separated.}
    ';

    #[Override]
    protected $description = 'Re-saves Commerce products.';

    #[Override]
    protected $aliases = ['resave/products'];

    public function handle(ProductTypes $productTypes): int
    {
        if (!$this->validateResaveOptions()) {
            return self::FAILURE;
        }

        $criteria = [];

        if ($this->option('type')) {
            $criteria['type'] = str($this->option('type'))
                ->explode(',')
                ->all();
        }

        $withFields = $this->resolvedWithFields;

        if (!empty($withFields)) {
            $handles = collect($productTypes->getAllProductTypes())
                ->filter(fn(ProductType $productType) => $this->hasTheFields($productType->getProductFieldLayout()))
                ->map(fn(ProductType $productType) => $productType->handle)
                ->all();

            if (isset($criteria['type'])) {
                $criteria['type'] = array_intersect($criteria['type'], $handles);
            } else {
                $criteria['type'] = $handles;
            }

            if (empty($criteria['type'])) {
                $this->components->warn('No product types satisfy `--with-fields`.');

                return self::FAILURE;
            }
        }

        return $this->resaveElements(Product::class, $criteria);
    }
}
