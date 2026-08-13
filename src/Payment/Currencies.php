<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Payment;

use Illuminate\Container\Attributes\Singleton;
use Illuminate\Support\Collection;
use Money\Currencies\ISOCurrencies;
use Money\Currency;
use Money\Formatter\DecimalMoneyFormatter;
use Money\Money;
use Money\Parser\DecimalMoneyParser;
use Money\Teller;

#[Singleton]
class Currencies
{
    private ISOCurrencies $isoCurrencies;

    /** @var array<string, Teller> */
    private array $tellersByIso = [];

    public function __construct()
    {
        $this->isoCurrencies = new ISOCurrencies();
    }

    public function getTeller(Currency|string $currency): Teller
    {
        if (is_string($currency)) {
            $currency = new Currency($currency);
        }

        $iso = $currency->getCode();
        if (isset($this->tellersByIso[$iso])) {
            return $this->tellersByIso[$iso];
        }

        $parser = new DecimalMoneyParser($this->isoCurrencies);
        $formatter = new DecimalMoneyFormatter($this->isoCurrencies);

        return $this->tellersByIso[$iso] = new Teller(
            $currency,
            $parser,
            $formatter,
            Money::ROUND_HALF_UP,
        );
    }

    public function getCurrencyByIso(string $iso): ?Currency
    {
        return $this->getAllCurrencies()->first(fn(Currency $currency) => $currency->getCode() === $iso);
    }

    /**
     * @return Collection<int, Currency>
     */
    public function getAllCurrencies(): Collection
    {
        return collect($this->isoCurrencies);
    }

    /**
     * @return list<array{label: string, value: string}>
     */
    public function getAllCurrenciesList(): array
    {
        return $this->getAllCurrencies()->map(fn(Currency $currency) => [
            'label' => $currency->getCode(), // TODO: get name somehow
            'value' => $currency->getCode(),
        ])->all();
    }

    public function getSubunitFor(Currency|string $currency): int
    {
        if (is_string($currency)) {
            $currency = $this->getCurrencyByIso($currency);
        }

        return $this->isoCurrencies->subunitFor($currency);
    }

    public function numericCodeFor(Currency|string $currency): int
    {
        if (is_string($currency)) {
            $currency = $this->getCurrencyByIso($currency);
        }

        return $this->isoCurrencies->numericCodeFor($currency);
    }
}
