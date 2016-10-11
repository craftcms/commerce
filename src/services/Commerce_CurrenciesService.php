<?php
namespace Craft;

/**
 * Currency service.
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.services
 * @since     1.2
 */
class Commerce_CurrenciesService extends BaseApplicationComponent
{
    private $_allCurrencies;

    /**
     * @param string $iso
     *
     * @return Commerce_CurrencyModel|null
     */
    public function getCurrencyByIso($iso)
    {
        /** @var Commerce_CurrencyModel $currency */
        foreach ($this->getAllCurrencies() as $currency)
        {
            if ($currency->alphabeticCode == $iso)
            {
                return $currency;
            }
        }
    }

    /**
     * @return Commerce_CurrencyModel[]
     */
    public function getAllCurrencies()
    {
        if (!isset($this->_allCurrencies))
        {
            $this->_allCurrencies = [];
            $data = require(__DIR__.'/../etc/currencies.php');
            foreach ($data as $key => $currency)
            {
                $this->_allCurrencies[$key] = Commerce_CurrencyModel::populateModel($currency);
            }
        }

        return $this->_allCurrencies;
    }
}
