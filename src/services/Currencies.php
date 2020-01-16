<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\services;

use craft\commerce\models\Currency;
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
     * @var
     */
    private $_allCurrencies;


    /**
     * Get a currency by it's ISO code.
     *
     * @param string $iso
     * @return Currency|null
     */
    public function getCurrencyByIso(string $iso)
    {
        /** @var Currency $currency */
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
        if (null === $this->_allCurrencies) {
            $this->_allCurrencies = [];
            $data = require __DIR__ . '/../etc/currencies.php';
            foreach ($data as $key => $currency) {
                $this->_allCurrencies[$key] = new Currency($currency);
            }
        }

        return $this->_allCurrencies;
    }
}
