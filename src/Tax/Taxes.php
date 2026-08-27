<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Tax;

use craft\commerce\Plugin;
use CraftCms\Commerce\Tax\Contracts\TaxEngineInterface;
use CraftCms\Commerce\Tax\Contracts\TaxIdValidatorInterface;
use CraftCms\Commerce\Tax\Engines\Tax;
use CraftCms\Commerce\Tax\Events\TaxEngineEvent;
use CraftCms\Commerce\Tax\Events\TaxIdValidatorsEvent;
use CraftCms\Commerce\Tax\VatValidator\Eu;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Support\Collection;
use RuntimeException;

#[Singleton]
class Taxes implements TaxEngineInterface
{
    public const string EVENT_REGISTER_TAX_ID_VALIDATORS = 'registerTaxIdValidators';

    public const string EVENT_REGISTER_TAX_ENGINE = 'registerTaxEngine';

    private ?TaxEngineInterface $taxEngine = null;

    /**
     * @return Collection<int, TaxIdValidatorInterface>
     */
    public function getTaxIdValidators(): Collection
    {
        $validators = [new Eu()];

        $event = new TaxIdValidatorsEvent(
            validators: $validators,
        );

        // TODO: migrate event firing to Laravel once event system is bridged
        if (Plugin::getInstance()->getTaxes()->hasEventHandlers(self::EVENT_REGISTER_TAX_ID_VALIDATORS)) {
            /** @phpstan-ignore-next-line */
            Plugin::getInstance()->getTaxes()->trigger(self::EVENT_REGISTER_TAX_ID_VALIDATORS, $event);
        }

        foreach ($event->validators as $validator) {
            if (!$validator instanceof TaxIdValidatorInterface) {
                throw new RuntimeException('Tax ID validator must implement TaxIdValidatorInterface');
            }
        }

        return collect($event->validators);
    }

    /**
     * @return Collection<int, TaxIdValidatorInterface>
     */
    public function getEnabledTaxIdValidators(): Collection
    {
        return $this->getTaxIdValidators()->filter(fn(TaxIdValidatorInterface $validator) => $validator::isEnabled());
    }

    public function getEngine(): TaxEngineInterface
    {
        if ($this->taxEngine !== null) {
            return $this->taxEngine;
        }

        $event = new TaxEngineEvent(engine: new Tax());

        // TODO: migrate event firing to Laravel once event system is bridged
        if (Plugin::getInstance()->getTaxes()->hasEventHandlers(self::EVENT_REGISTER_TAX_ENGINE)) {
            /** @phpstan-ignore-next-line */
            Plugin::getInstance()->getTaxes()->trigger(self::EVENT_REGISTER_TAX_ENGINE, $event);
        }

        $this->taxEngine = $event->engine;

        return $this->taxEngine;
    }

    #[\Override]
    public static function displayName(): string
    {
        return 'Commerce Taxes Service';
    }

    #[\Override]
    public static function isSelectable(): bool
    {
        return true;
    }

    #[\Override]
    public function taxAdjusterClass(): string
    {
        return $this->getEngine()->taxAdjusterClass();
    }

    #[\Override]
    public function viewTaxCategories(): bool
    {
        return $this->getEngine()->viewTaxCategories();
    }

    #[\Override]
    public function createTaxCategories(): bool
    {
        return $this->getEngine()->createTaxCategories();
    }

    #[\Override]
    public function editTaxCategories(): bool
    {
        return $this->getEngine()->editTaxCategories();
    }

    #[\Override]
    public function deleteTaxCategories(): bool
    {
        return $this->getEngine()->deleteTaxCategories();
    }

    #[\Override]
    public function taxCategoryActionHtml(): string
    {
        return $this->getEngine()->taxCategoryActionHtml();
    }

    #[\Override]
    public function viewTaxZones(): bool
    {
        return $this->getEngine()->viewTaxZones();
    }

    #[\Override]
    public function editTaxZones(): bool
    {
        return $this->getEngine()->editTaxZones();
    }

    #[\Override]
    public function viewTaxRates(): bool
    {
        return $this->getEngine()->viewTaxRates();
    }

    #[\Override]
    public function editTaxRates(): bool
    {
        return $this->getEngine()->editTaxRates();
    }

    #[\Override]
    public function cpTaxNavSubItems(): array
    {
        return $this->getEngine()->cpTaxNavSubItems();
    }

    #[\Override]
    public function createTaxZones(): bool
    {
        return $this->getEngine()->createTaxZones();
    }

    #[\Override]
    public function deleteTaxZones(): bool
    {
        return $this->getEngine()->deleteTaxZones();
    }

    #[\Override]
    public function taxZoneActionHtml(): string
    {
        return $this->getEngine()->taxZoneActionHtml();
    }

    #[\Override]
    public function createTaxRates(): bool
    {
        return $this->getEngine()->createTaxRates();
    }

    #[\Override]
    public function deleteTaxRates(): bool
    {
        return $this->getEngine()->deleteTaxRates();
    }

    #[\Override]
    public function taxRateActionHtml(): string
    {
        return $this->getEngine()->taxRateActionHtml();
    }
}
