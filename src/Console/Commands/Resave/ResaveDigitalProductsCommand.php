<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Console\Commands\Resave;

use CraftCms\Cms\Element\Commands\Resave\ResaveCommand;
use CraftCms\Commerce\DigitalProduct\Data\DigitalProductType;
use CraftCms\Commerce\DigitalProduct\DigitalProductTypes;
use CraftCms\Commerce\DigitalProduct\Elements\DigitalProduct;
use Override;

class ResaveDigitalProductsCommand extends ResaveCommand
{
    #[Override]
    protected $signature = 'craft:resave:digital-products'
        . self::DEFAULT_OPTIONS
        . '
        {--type= : The digital product type handle(s) of the digital products to resave, comma-separated.}
    ';

    #[Override]
    protected $description = 'Re-saves Commerce digital products.';

    #[Override]
    protected $aliases = ['resave/digital-products'];

    public function handle(DigitalProductTypes $types): int
    {
        if (!$this->validateResaveOptions()) {
            return self::FAILURE;
        }

        $criteria = [];

        if ($this->option('type')) {
            $criteria['type'] = str($this->option('type'))->explode(',')->all();
        }

        if (!empty($withFields = $this->resolvedWithFields)) {
            $handles = collect($types->getAllDigitalProductTypes())
                ->filter(fn(DigitalProductType $type) => $this->hasTheFields($type->getFieldLayout()))
                ->map(fn(DigitalProductType $type) => $type->handle)
                ->all();

            $criteria['type'] = isset($criteria['type']) ? array_intersect($criteria['type'], $handles) : $handles;

            if (empty($criteria['type'])) {
                $this->components->warn('No digital product types satisfy `--with-fields`.');

                return self::FAILURE;
            }
        }

        return $this->resaveElements(DigitalProduct::class, $criteria);
    }
}
