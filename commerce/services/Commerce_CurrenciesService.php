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
 * @since     1.0
 */
class Commerce_CurrenciesService extends BaseApplicationComponent
{

	private $_allCurrencies;

    /**
     * @param int $id
     *
     * @return Commerce_CurrencyModel|null
     */
    public function getCurrencyById($id)
    {
	    foreach ($this->getAllCurrencies() as $currency)
	    {
		    if ($currency->id == $id)
		    {
			    return $currency;
		    }
	    }
    }

    /**
     * @param array $attr
     *
     * @return Commerce_CurrencyModel|null
     */
    public function getCurrencyByAttributes(array $attr)
    {
        $result = Commerce_CurrencyRecord::model()->findByAttributes($attr);

        if ($result) {
            return Commerce_CurrencyModel::populateModel($result);
        }

        return null;
    }


    /**
     * @param string $iso
     *
     * @return Commerce_CurrencyModel|null
     */
    public function getCurrencyByIso($iso)
    {
	    foreach ($this->getAllCurrencies() as $currency)
	    {
		    if ($currency->iso == $iso)
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
		    $records = Commerce_CurrencyRecord::model()->findAll(['order' => 'name']);
		    $this->_allCurrencies = Commerce_CurrencyModel::populateModels($records);
	    }

	    return $this->_allCurrencies;
    }

	/**
	 * Returns the default currency all prices are entered as.
	 *
	 * @return Commerce_CurrencyModel
	 */
	public function getDefaultCurrency()
	{
		foreach ($this->getAllCurrencies() as $currency)
		{
			if ($currency->default)
			{
				return $currency;
			}
		}
	}

	/**
	 * Return the default currencies ISO code as a string.
	 *
	 * @return string
	 */
	public function getDefaultCurrencyIso()
	{
		return $this->getDefaultCurrency()->iso;
	}


	public function convertPrice($price, $currency)
	{
		$destinationCurrency = craft()->commerce_currencies->getCurrencyByIso($currency);
		return $price * $destinationCurrency->rate;
	}

	/**
     * @param Commerce_CurrencyModel $model
     *
     * @return bool
     * @throws Exception
     * @throws \CDbException
     * @throws \Exception
     */
    public function saveCurrency(Commerce_CurrencyModel $model)
    {
        if ($model->id) {
            $record = Commerce_CurrencyRecord::model()->findById($model->id);

            if (!$record) {
                throw new Exception(Craft::t('No currency exists with the ID “{id}”',
                    ['id' => $model->id]));
            }
        } else {
            $record = new Commerce_CurrencyRecord();
        }

        $record->name = $model->name;
        $record->iso = strtoupper($model->iso);
        $record->default = $model->default;
        // If this rate is default, the rate must be 1 since it is now the rate all prices are enter in as.
        $record->rate =  $model->default ? 1 : $model->rate;

        $record->validate();
        $model->addErrors($record->getErrors());

        if (!$model->hasErrors()) {

            if ($record->default)
            {
                Commerce_OrderStatusRecord::model()->updateAll(['default' => 0]);
            }

            $record->save(false);

            // Now that we have a record ID, save it on the model
            $model->id = $record->id;

            return true;
        } else {
            return false;
        }
    }

    /**
     * @param int $id
     *
     * @throws \CDbException
     */
    public function deleteCurrencyById($id)
    {
        Commerce_CurrencyRecord::model()->deleteByPk($id);
    }
}
