<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Tax\Contracts;

use CraftCms\Cms\Component\Contracts\ComponentInterface;

interface TaxEngineInterface extends ComponentInterface
{
    public function taxAdjusterClass(): string;

    public function viewTaxCategories(): bool;

    public function createTaxCategories(): bool;

    public function editTaxCategories(): bool;

    public function deleteTaxCategories(): bool;

    public function taxCategoryActionHtml(): string;

    public function viewTaxZones(): bool;

    public function createTaxZones(): bool;

    public function editTaxZones(): bool;

    public function deleteTaxZones(): bool;

    public function taxZoneActionHtml(): string;

    public function viewTaxRates(): bool;

    public function createTaxRates(): bool;

    public function editTaxRates(): bool;

    public function deleteTaxRates(): bool;

    public function taxRateActionHtml(): string;

    public function cpTaxNavSubItems(): array;
}
