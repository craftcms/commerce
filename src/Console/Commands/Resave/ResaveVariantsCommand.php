<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Console\Commands\Resave;

use CraftCms\Cms\Element\Commands\Resave\ResaveCommand;
use CraftCms\Commerce\Catalog\Elements\Variant;
use CraftCms\Commerce\Catalog\ProductType\Data\ProductType;
use CraftCms\Commerce\Catalog\ProductType\ProductTypes;
use CraftCms\Commerce\Database\Table;
use Illuminate\Support\Facades\DB;
use Override;

class ResaveVariantsCommand extends ResaveCommand
{
    #[Override]
    protected $signature = 'craft:resave:variants'
        . self::DEFAULT_OPTIONS
        . '
        {--type= : The product type handle(s) of the variants to resave, comma-separated.}
    ';

    #[Override]
    protected $description = 'Re-saves Commerce variants.';

    #[Override]
    protected $aliases = ['resave/variants'];

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
                ->filter(fn(ProductType $productType) => $this->hasTheFields($productType->getVariantFieldLayout()))
                ->map(fn(ProductType $productType) => $productType->handle)
                ->all();

            if (isset($criteria['type'])) {
                $criteria['type'] = array_intersect($criteria['type'], $handles);
            } else {
                $criteria['type'] = $handles;
            }

            if (empty($criteria['type'])) {
                $this->components->warn('No variant types satisfy `--with-fields`.');

                return self::FAILURE;
            }
        }

        // Convert type handles to type IDs for the variant query
        if (!empty($criteria['type'])) {
            $criteria['typeId'] = DB::table(Table::PRODUCTTYPES)
                ->whereParam('handle', $criteria['type'])
                ->pluck('id')
                ->all();

            unset($criteria['type']);
        }

        return $this->resaveElements(Variant::class, $criteria);
    }
}
