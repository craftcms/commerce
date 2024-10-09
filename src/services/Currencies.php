<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\services;

use craft\commerce\models\Currency;
use Money\Currencies\ISOCurrencies;
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
    /**
     * @var array
     */
    private array $_tellersByIso = [];

    /**
     * @var array
     */
    private array $_allCurrencies;

    /**
     * @param \Money\Currency|string $currency
     * @return Teller
     * @since 4.7.0
     */
    public function getTeller(\Money\Currency|string $currency): Teller
    {
        if (is_string($currency)) {
            $currency = new \Money\Currency($currency);
        }

        $iso = $currency->getCode();
        if (isset($this->_tellersByIso[$iso])) {
            return $this->_tellersByIso[$iso];
        }

        $isoCurrencies = new ISOCurrencies(); // remove in 5.0 and use $this->_isoCurrencies
        $parser = new DecimalMoneyParser($isoCurrencies); // in 5.0 use $this->_isoCurrencies
        $formatter = new DecimalMoneyFormatter($isoCurrencies); // in 5.0 use $this->_isoCurrencies
        $roundingMode = Money::ROUND_HALF_UP;
        $this->_tellersByIso[$iso] = new \Money\Teller(
            $currency,
            $parser,
            $formatter,
            $roundingMode
        );

        return $this->_tellersByIso[$iso];
    }

    /**
     * Get a currency by it's ISO code.
     *
     * @param string $iso
     * @return Currency|null
     */
    public function getCurrencyByIso(string $iso): ?Currency
    {
        foreach ($this->getAllCurrencies() as $currency) {
            if ($currency->alphabeticCode == $iso) {
                return $currency;
            }
        }

        return null;
    }

    /**
     * Get a list of all available currencies.
     *
     * @return Currency[]
     */
    public function getAllCurrencies(): array
    {
        if (!isset($this->_allCurrencies)) {
            $this->_allCurrencies = [];
            $data = require __DIR__ . '/../etc/currencies.php';
            foreach ($data as $key => $currency) {
                $this->_allCurrencies[$key] = new Currency($currency);
            }
        }

        return $this->_allCurrencies;
    }
}
