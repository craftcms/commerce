<?php

namespace craft\commerce\services;

use CraftCms\Commerce\Services\Currencies as NewCurrencies;
use Illuminate\Support\Collection;
use Money\Currency;
use Money\Teller;
use yii\base\Component;

/**
 * @deprecated 6.0.0 use `app(\CraftCms\Commerce\Services\Currencies::class)` instead.
 */
class Currencies extends Component
{
    private function impl(): NewCurrencies
    {
        return app(NewCurrencies::class);
    }

    public function getTeller(Currency|string $currency): Teller
    {
        return $this->impl()->getTeller($currency);
    }

    public function getCurrencyByIso(string $iso): ?Currency
    {
        return $this->impl()->getCurrencyByIso($iso);
    }

    /**
     * @return Collection<int, Currency>
     */
    public function getAllCurrencies(): Collection
    {
        return $this->impl()->getAllCurrencies();
    }

    public function getAllCurrenciesList(): array
    {
        return $this->impl()->getAllCurrenciesList();
    }

    public function getSubunitFor(Currency|string $currency): int
    {
        return $this->impl()->getSubunitFor($currency);
    }

    public function numericCodeFor(Currency|string $currency): int
    {
        return $this->impl()->numericCodeFor($currency);
    }
}
