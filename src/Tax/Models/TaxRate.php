<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Tax\Models;

use craft\commerce\records\TaxRate as TaxRateRecord;
use CraftCms\Cms\Component\Component;
use CraftCms\Cms\Component\Contracts\Chippable;
use CraftCms\Cms\Support\Facades\I18N;
use CraftCms\Commerce\Store\Concerns\StoreTrait;
use CraftCms\Commerce\Store\Contracts\HasStoreInterface;
use DateTime;
use function CraftCms\Cms\t;

class TaxRate extends Component implements HasStoreInterface, Chippable
{
    use StoreTrait;

    public ?int $id = null;

    public ?string $name = null;

    public ?string $code = null;

    public float $rate = 0.00;

    public bool $include = false;

    public bool $removeIncluded = false;

    public bool $removeVatIncluded = false;

    public string $taxable = 'price';

    public ?int $taxCategoryId = null;

    public ?int $taxZoneId = null;

    public array $taxIdValidators = [];

    public ?DateTime $dateCreated = null;

    public ?DateTime $dateUpdated = null;

    public bool $enabled = true;

    private ?TaxCategory $_taxCategory = null;

    private ?TaxAddressZone $_taxZone = null;

    #[\Override]
    public static function get(int|string $id): ?static
    {
        /** @phpstan-ignore-next-line */
        return app(\CraftCms\Commerce\Services\TaxRates::class)->getTaxRateById($id);
    }

    #[\Override]
    public function getUiLabel(): string
    {
        return t($this->name ?? '', category: 'site');
    }

    #[\Override]
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCpEditUrl(): string
    {
        return $this->getStore()->getStoreSettingsUrl('taxrates/' . $this->id);
    }

    public function getRateAsPercent(): string
    {
        return I18N::getFormatter()->asPercent($this->rate);
    }

    public function getTaxZone(): ?TaxAddressZone
    {
        if ($this->_taxZone === null && $this->taxZoneId) {
            $this->_taxZone = app(\CraftCms\Commerce\Services\TaxZones::class)->getTaxZoneById($this->taxZoneId, $this->storeId);
        }

        return $this->_taxZone;
    }

    public function getTaxCategory(): ?TaxCategory
    {
        if (!isset($this->_taxCategory) && $this->taxCategoryId) {
            $this->_taxCategory = app(\CraftCms\Commerce\Services\TaxCategories::class)->getTaxCategoryById($this->taxCategoryId);
        }

        return $this->_taxCategory;
    }

    public function getIsEverywhere(): bool
    {
        return !$this->getTaxZone();
    }

    public function hasTaxIdValidators(): bool
    {
        return count($this->taxIdValidators) > 0;
    }

    public function hasTaxIdValidator(string $className): bool
    {
        return in_array($className, $this->taxIdValidators, true);
    }

    public function getSelectedEnabledTaxIdValidators(): array
    {
        $selectedValidators = $this->taxIdValidators;
        $validators = app(\CraftCms\Commerce\Services\Taxes::class)->getEnabledTaxIdValidators();
        $activeValidators = [];
        foreach ($validators as $validator) {
            if (in_array($validator::class, $selectedValidators)) {
                $activeValidators[] = $validator;
            }
        }

        return $activeValidators;
    }

    #[\Override]
    public function extraFields(): array
    {
        return array_merge(parent::extraFields(), ['taxCategory', 'taxZone', 'rateAsPercent', 'isEverywhere']);
    }

    #[\Override]
    public function getRules(): array
    {
        $rules = [
            'name' => ['required', 'string'],
        ];

        if (!in_array($this->taxable, TaxRateRecord::ORDER_TAXABALES, true)) {
            $rules['taxCategoryId'] = ['required', 'integer'];
        }

        return $rules;
    }
}
