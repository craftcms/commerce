<?php

namespace craft\commerce\services;

use Craft;
use craft\commerce\models\PaymentCurrency;
use craft\commerce\Plugin;
use craft\commerce\records\PaymentCurrency as PaymentCurrencyRecord;
use craft\db\Query;
use yii\base\Component;
use yii\base\Exception;

/**
 * Payment currency service.
 *
 * @property \craft\commerce\models\PaymentCurrency[]|array $allPaymentCurrencies
 * @property \craft\commerce\models\PaymentCurrency|null    $primaryPaymentCurrency
 * @property string                                         $primaryPaymentCurrencyIso
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.services
 * @since     1.2
 */
class PaymentCurrencies extends Component
{
    /**
     * @var PaymentCurrency[]
     */
    private $_allCurrenciesByIso;

    /**
     * @var PaymentCurrency[]
     */
    private $_allCurrenciesById;

    // Public Methods
    // =========================================================================

    /**
     * @param int $id
     *
     * @return PaymentCurrency|null
     */
    public function getPaymentCurrencyById($id)
    {
        if ($this->_allCurrenciesById === null) {
            $this->getAllPaymentCurrencies();
        }

        if (isset($this->_allCurrenciesById[$id])) {
            return $this->_allCurrenciesById[$id];
        }

        return null;
    }

    /**
     * @return PaymentCurrency[]
     */
    public function getAllPaymentCurrencies(): array
    {
        if (null === $this->_allCurrenciesByIso) {
            $rows = $this->_createPaymentCurrencyQuery()
                ->orderBy(['primary' => SORT_DESC, 'iso' => SORT_ASC])
                ->all();

            $this->_allCurrenciesByIso = [];

            foreach ($rows as $row) {
                $paymentCurrency = new PaymentCurrency($row);

                // TODO: Fix this with money/money package
                $currency = Plugin::getInstance()->getCurrencies()->getCurrencyByIso($paymentCurrency->iso);
                $paymentCurrency->setCurrency($currency);

                $this->_memoizePaymentCurrency($paymentCurrency);
            }
        }

        return $this->_allCurrenciesById;
    }

    /**
     * @param string $iso
     *
     * @return PaymentCurrency|null
     */
    public function getPaymentCurrencyByIso($iso)
    {
        if ($this->_allCurrenciesByIso === null) {
            $this->getAllPaymentCurrencies();
        }

        if (isset($this->_allCurrenciesByIso[$iso])) {
            return $this->_allCurrenciesByIso[$iso];
        }

        return null;
    }

    /**
     * Return the primary currencies ISO code as a string.
     *
     * @return string
     */
    public function getPrimaryPaymentCurrencyIso(): string
    {
        return $this->getPrimaryPaymentCurrency()->iso;
    }

    /**
     * Returns the primary currency all prices are entered as.
     *
     * @return PaymentCurrency|null
     */
    public function getPrimaryPaymentCurrency()
    {
        foreach ($this->getAllPaymentCurrencies() as $currency) {
            if ($currency->primary) {
                return $currency;
            }
        }

        return null;
    }

    /**
     * @param float  $amount This is the unit of price in the primary store currency
     * @param string $currency
     *
     * @return float
     */
    public function convert($amount, $currency): float
    {
        $destinationCurrency = $this->getPaymentCurrencyByIso($currency);

        return $amount * $destinationCurrency->rate;
    }

    /**
     * @param PaymentCurrency $model
     *
     * @return bool
     * @throws Exception
     */
    public function savePaymentCurrency(PaymentCurrency $model): bool
    {
        if ($model->id) {
            $record = PaymentCurrencyRecord::findOne($model->id);

            if (!$record) {
                throw new Exception(Craft::t('commerce', 'No currency exists with the ID “{id}”',
                    ['id' => $model->id]));
            }
        } else {
            $record = new PaymentCurrencyRecord();
        }

        $record->iso = strtoupper($model->iso);
        $record->primary = $model->primary;
        // If this rate is primary, the rate must be 1 since it is now the rate all prices are enter in as.
        $record->rate = $model->primary ? 1 : $model->rate;

        $record->validate();
        $model->addErrors($record->getErrors());

        if (!$model->hasErrors()) {
            if ($record->primary) {
                PaymentCurrencyRecord::updateAll(['primary' => 0]);
            }

            $record->save(false);

            // Now that we have a record ID, save it on the model
            $model->id = $record->id;

            return true;
        }

        return false;
    }

    /**
     * @param $id
     *
     * @return bool
     */
    public function deletePaymentCurrencyById($id): bool
    {
        $paymentCurrency = PaymentCurrencyRecord::findOne($id);

        if ($paymentCurrency) {
            return $paymentCurrency->delete();
        }

        return false;
    }

    // Private Methods
    // =========================================================================

    /**
     * Memoize a payment currency
     *
     * @param PaymentCurrency $paymentCurrency
     */
    private function _memoizePaymentCurrency(PaymentCurrency $paymentCurrency)
    {
        $this->_allCurrenciesByIso[$paymentCurrency->iso] = $paymentCurrency;
        $this->_allCurrenciesById[$paymentCurrency->id] = $paymentCurrency;
    }

    /**
     * Returns a Query object prepped for retrieving Emails
     *
     * @return Query
     */
    private function _createPaymentCurrencyQuery(): Query
    {
        return (new Query())
            ->select([
                'id',
                'iso',
                'primary',
                'rate',
            ])
            ->from(['{{%commerce_paymentcurrencies}}']);
    }
}
