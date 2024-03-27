<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\services;

use Illuminate\Support\Collection;
use Money\Currencies\ISOCurrencies;
use Money\Currency;
use Money\Formatter\DecimalMoneyFormatter;
use Money\Money;
use Money\Parser\DecimalMoneyParser;
use Money\Teller;
use yii\base\Component;

/**
 * Currency service.
 *
 * @property array|Currency[] $allCurrencies
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class Currencies extends Component
{
    private ?ISOCurrencies $_isoCurrencies = null;

    public function init()
    {
        $this->_isoCurrencies = new ISOCurrencies();
    }

    /**
     * @param Currency|string $currency
     * @return Teller
     */
    public function getTeller(\Money\Currency|string $currency): Teller
    {
        if (is_string($currency)) {
            $currency = new \Money\Currency($currency);
        }

        $parser = new DecimalMoneyParser($this->_isoCurrencies);
        $formatter = new DecimalMoneyFormatter($this->_isoCurrencies);
        $roundingMode = Money::ROUND_HALF_UP;
        return new \Money\Teller(
            $currency,
            $parser,
            $formatter,
            $roundingMode
        );
    }

    /**
     * Get a currency by it's ISO code.
     *
     * @param string $iso
     * @return \Money\Currency|null
     */
    public function getCurrencyByIso(string $iso): ?\Money\Currency
    {
        return $this->getAllCurrencies()->first(function(\Money\Currency $currency) use ($iso) {
            return $currency->getCode() == $iso;
        });
    }


    /**
     * Get a list of all available currencies.
     *
     * @return Collection<\Money\Currency>
     */
    public function getAllCurrencies(): Collection
    {
        return collect($this->_isoCurrencies);
    }

    /**
     * @return array
     */
    public function getAllCurrenciesList(): array
    {
        return $this->getAllCurrencies()->map(function($currency) {
            return [
                'label' => $currency->getCode(), // TODO get name somehow
                'value' => $currency->getCode(),
            ];
        })->toArray();
    }

    /**
     * @param Currency|string $currency
     * @return int
     */
    public function getSubunitFor(Currency|string $currency)
    {
        if (is_string($currency)) {
            $currency = $this->getCurrencyByIso($currency);
        }

        return $this->_isoCurrencies->subunitFor($currency);
    }

    /**
     * @param Currency|string $currency
     * @return int
     */
    public function numericCodeFor(Currency|string $currency)
    {
        if (is_string($currency)) {
            $currency = $this->getCurrencyByIso($currency);
        }

        return $this->_isoCurrencies->subunitFor($currency);
    }
}
