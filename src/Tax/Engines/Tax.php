<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Tax\Engines;

use CraftCms\Cms\Component\Component;
use CraftCms\Commerce\Tax\Contracts\TaxEngineInterface;

class Tax extends Component implements TaxEngineInterface
{
    #[\Override]
    public static function displayName(): string
    {
        return 'Commerce Tax Engine';
    }

    #[\Override]
    public function taxAdjusterClass(): string
    {
        return \craft\commerce\adjusters\Tax::class;
    }

    #[\Override]
    public function viewTaxCategories(): bool
    {
        return true;
    }

    #[\Override]
    public function createTaxCategories(): bool
    {
        return true;
    }

    #[\Override]
    public function editTaxCategories(): bool
    {
        return true;
    }

    #[\Override]
    public function deleteTaxCategories(): bool
    {
        return true;
    }

    #[\Override]
    public function taxCategoryActionHtml(): string
    {
        return '';
    }

    #[\Override]
    public function viewTaxZones(): bool
    {
        return true;
    }

    #[\Override]
    public function editTaxZones(): bool
    {
        return true;
    }

    #[\Override]
    public function createTaxZones(): bool
    {
        return true;
    }

    #[\Override]
    public function deleteTaxZones(): bool
    {
        return true;
    }

    #[\Override]
    public function taxZoneActionHtml(): string
    {
        return '';
    }

    #[\Override]
    public function viewTaxRates(): bool
    {
        return true;
    }

    #[\Override]
    public function createTaxRates(): bool
    {
        return true;
    }

    #[\Override]
    public function deleteTaxRates(): bool
    {
        return true;
    }

    #[\Override]
    public function editTaxRates(): bool
    {
        return true;
    }

    #[\Override]
    public function taxRateActionHtml(): string
    {
        return '';
    }

    #[\Override]
    public function cpTaxNavSubItems(): array
    {
        return [];
    }
}
