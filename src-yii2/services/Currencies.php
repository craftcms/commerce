<?php

namespace craft\commerce\services;

use Illuminate\Support\Collection;
use Money\Currency;
use Money\Teller;
use yii\base\Component;

/**
 * @deprecated 6.0.0 use `app(\CraftCms\Commerce\Payment\Currencies::class)` instead.
 */
class Currencies extends Component
{
    public function getTeller(Currency|string $currency): Teller
    {
        return app(\CraftCms\Commerce\Payment\Currencies::class)->getTeller($currency);
    }

    public function getCurrencyByIso(string $iso): ?Currency
    {
        return app(\CraftCms\Commerce\Payment\Currencies::class)->getCurrencyByIso($iso);
    }

    /**
     * @return Collection<int, Currency>
     */
    public function getAllCurrencies(): Collection
    {
        return app(\CraftCms\Commerce\Payment\Currencies::class)->getAllCurrencies();
    }

    public function getAllCurrenciesList(): array
    {
        return app(\CraftCms\Commerce\Payment\Currencies::class)->getAllCurrenciesList();
    }

    public function getSubunitFor(Currency|string $currency): int
    {
        return app(\CraftCms\Commerce\Payment\Currencies::class)->getSubunitFor($currency);
    }

    public function numericCodeFor(Currency|string $currency): int
    {
        return app(\CraftCms\Commerce\Payment\Currencies::class)->numericCodeFor($currency);
    }
}
