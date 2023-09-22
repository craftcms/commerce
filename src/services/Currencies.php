<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\services;

use craft\commerce\models\Currency;
use Money\Currencies\ISOCurrencies;
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

        $currencies = new ISOCurrencies();
        foreach ($currencies as $currency) {

            $currencies->contains(new Currency('USD')); // returns boolean whether USD is available in this repository
            $currencies->subunitFor(new Currency('USD')); // returns the subunit for the dollar (2)

        }
    }
}
