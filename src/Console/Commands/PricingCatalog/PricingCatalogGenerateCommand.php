<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Console\Commands\PricingCatalog;

use CraftCms\Cms\Console\CraftCommand;
use CraftCms\Commerce\CatalogPricing\CatalogPricing;
use Illuminate\Console\Command;
use Override;

class PricingCatalogGenerateCommand extends Command
{
    use CraftCommand;

    #[Override]
    protected $signature = 'commerce:pricing-catalog:generate';

    #[Override]
    protected $description = 'Generates catalog pricing.';

    #[Override]
    protected $aliases = ['commerce/pricing-catalog/generate'];

    public function handle(CatalogPricing $catalogPricing): int
    {
        $this->line('Generating catalog pricing... ');

        $catalogPricing->generateCatalogPrices(showConsoleOutput: true);

        $this->line('<fg=green>Done!</>');

        return self::SUCCESS;
    }
}
