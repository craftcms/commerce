<?php
namespace Craft;

/**
 * Payment currency service.
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.services
 * @since     1.0
 */
class Commerce_PaymentCurrenciesService extends BaseApplicationComponent
{

	private $_allCurrencies;

    /**
     * @param int $id
     *
     * @return Commerce_PaymentCurrencyModel|null
     */
    public function getPaymentCurrencyById($id)
    {
	    foreach ($this->getAllPaymentCurrencies() as $currency)
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
     * @return Commerce_PaymentCurrencyModel|null
     */
    public function getPaymentCurrencyByAttributes(array $attr)
    {
        $result = Commerce_PaymentCurrencyRecord::model()->findByAttributes($attr);

        if ($result) {
            return Commerce_PaymentCurrencyModel::populateModel($result);
        }

        return null;
    }


    /**
     * @param string $iso
     *
     * @return Commerce_PaymentCurrencyModel|null
     */
    public function getPaymentCurrencyByIso($iso)
    {
	    foreach ($this->getAllPaymentCurrencies() as $currency)
	    {
		    if ($currency->iso == $iso)
		    {
			    return $currency;
		    }
	    }
    }


    /**
     * @return Commerce_PaymentCurrencyModel[]
     */
    public function getAllPaymentCurrencies()
    {
	    if (!isset($this->_allCurrencies))
	    {
		    $records = Commerce_PaymentCurrencyRecord::model()->findAll(['order' => 'name']);
		    $this->_allCurrencies = Commerce_PaymentCurrencyModel::populateModels($records);
	    }

	    return $this->_allCurrencies;
    }

	/**
	 * Returns the default currency all prices are entered as.
	 *
	 * @return Commerce_PaymentCurrencyModel
	 */
	public function getDefaultPaymentCurrency()
	{
		foreach ($this->getAllPaymentCurrencies() as $currency)
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
	public function getDefaultPaymentCurrencyIso()
	{
		return $this->getDefaultPaymentCurrency()->iso;
	}

	/**
	 * @param float $amount This is the unit of price in the default store currency
	 * @param string $currency
	 *
	 * @return float
	 */
	public function convert($amount, $currency)
	{
		$destinationCurrency = craft()->commerce_paymentCurrencies->getPaymentCurrencyByIso($currency);

		return $amount * $destinationCurrency->rate;
	}

	/**
     * @param Commerce_PaymentCurrencyModel $model
     *
     * @return bool
     * @throws Exception
     * @throws \CDbException
     * @throws \Exception
     */
    public function savePaymentCurrency(Commerce_PaymentCurrencyModel $model)
    {
        if ($model->id) {
            $record = Commerce_PaymentCurrencyRecord::model()->findById($model->id);

            if (!$record) {
                throw new Exception(Craft::t('No currency exists with the ID “{id}”',
                    ['id' => $model->id]));
            }
        } else {
            $record = new Commerce_PaymentCurrencyRecord();
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
    public function deletePaymentCurrencyById($id)
    {
        Commerce_PaymentCurrencyRecord::model()->deleteByPk($id);
    }
}
