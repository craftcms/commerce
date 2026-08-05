<?php

namespace craft\commerce\services;

use craft\base\Component;
use craft\commerce\base\TaxEngineInterface;
use craft\commerce\base\TaxIdValidatorInterface;
use CraftCms\Commerce\Tax\Contracts\TaxEngineInterface as NewTaxEngineInterface;
use Illuminate\Support\Collection;

/**
 * @deprecated 6.0.0 use `app(\CraftCms\Commerce\Services\Taxes::class)` instead.
 */
class Taxes extends Component implements TaxEngineInterface
{
    /**
     * @event \craft\commerce\events\TaxIdValidatorsEvent The event that is raised when tax ID validators are registered.
     */
    public const EVENT_REGISTER_TAX_ID_VALIDATORS = 'registerTaxIdValidators';

    /**
     * @event \craft\commerce\events\TaxEngineEvent The event that is triggered when determining the tax engine.
     */
    public const EVENT_REGISTER_TAX_ENGINE = 'registerTaxEngine';

    /**
     * @return Collection<TaxIdValidatorInterface>
     */
    public function getTaxIdValidators(): Collection
    {
        return app(\CraftCms\Commerce\Services\Taxes::class)->getTaxIdValidators();
    }

    /**
     * @return Collection<TaxIdValidatorInterface>
     */
    public function getEnabledTaxIdValidators(): Collection
    {
        return app(\CraftCms\Commerce\Services\Taxes::class)->getEnabledTaxIdValidators();
    }

    public function getEngine(): NewTaxEngineInterface
    {
        return app(\CraftCms\Commerce\Services\Taxes::class)->getEngine();
    }

    public function taxAdjusterClass(): string
    {
        return app(\CraftCms\Commerce\Services\Taxes::class)->taxAdjusterClass();
    }

    public function viewTaxCategories(): bool
    {
        return app(\CraftCms\Commerce\Services\Taxes::class)->viewTaxCategories();
    }

    public function createTaxCategories(): bool
    {
        return app(\CraftCms\Commerce\Services\Taxes::class)->createTaxCategories();
    }

    public function editTaxCategories(): bool
    {
        return app(\CraftCms\Commerce\Services\Taxes::class)->editTaxCategories();
    }

    public function deleteTaxCategories(): bool
    {
        return app(\CraftCms\Commerce\Services\Taxes::class)->deleteTaxCategories();
    }

    public function taxCategoryActionHtml(): string
    {
        return app(\CraftCms\Commerce\Services\Taxes::class)->taxCategoryActionHtml();
    }

    public function viewTaxZones(): bool
    {
        return app(\CraftCms\Commerce\Services\Taxes::class)->viewTaxZones();
    }

    public function editTaxZones(): bool
    {
        return app(\CraftCms\Commerce\Services\Taxes::class)->editTaxZones();
    }

    public function viewTaxRates(): bool
    {
        return app(\CraftCms\Commerce\Services\Taxes::class)->viewTaxRates();
    }

    public function editTaxRates(): bool
    {
        return app(\CraftCms\Commerce\Services\Taxes::class)->editTaxRates();
    }

    public function cpTaxNavSubItems(): array
    {
        return app(\CraftCms\Commerce\Services\Taxes::class)->cpTaxNavSubItems();
    }

    public function createTaxZones(): bool
    {
        return app(\CraftCms\Commerce\Services\Taxes::class)->createTaxZones();
    }

    public function deleteTaxZones(): bool
    {
        return app(\CraftCms\Commerce\Services\Taxes::class)->deleteTaxZones();
    }

    public function taxZoneActionHtml(): string
    {
        return app(\CraftCms\Commerce\Services\Taxes::class)->taxZoneActionHtml();
    }

    public function createTaxRates(): bool
    {
        return app(\CraftCms\Commerce\Services\Taxes::class)->createTaxRates();
    }

    public function deleteTaxRates(): bool
    {
        return app(\CraftCms\Commerce\Services\Taxes::class)->deleteTaxRates();
    }

    public function taxRateActionHtml(): string
    {
        return app(\CraftCms\Commerce\Services\Taxes::class)->taxRateActionHtml();
    }
}
