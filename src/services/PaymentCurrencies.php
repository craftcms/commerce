<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\services;

use Craft;
use craft\commerce\errors\CurrencyException;
use craft\commerce\models\PaymentCurrency;
use craft\commerce\Plugin;
use craft\commerce\records\PaymentCurrency as PaymentCurrencyRecord;
use craft\db\Query;
use yii\base\Component;
use yii\base\Exception;
use yii\base\InvalidConfigException;

/**
 * Payment currency service.
 *
 * @property PaymentCurrency[]|array $allPaymentCurrencies
 * @property PaymentCurrency|null $primaryPaymentCurrency the primary currency all prices are entered as
 * @property string $primaryPaymentCurrencyIso the primary currencies ISO code as a string
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class PaymentCurrencies extends Component
{
    // Properties
    // =========================================================================

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
     * Get payment currency by its ID.
     *
     * @param int $id
     * @return PaymentCurrency|null
     * @throws InvalidConfigException if currency has invalid iso code defined
     */
    public function getPaymentCurrencyById($id)
    {
        if ($this->_allCurrenciesById === null) {
            try {
                $this->getAllPaymentCurrencies();
            } catch (CurrencyException $exception) {
                throw new InvalidConfigException($exception->getMessage());
            }
        }

        if (isset($this->_allCurrenciesById[$id])) {
            return $this->_allCurrenciesById[$id];
        }

        return null;
    }

    /**
     * Get all payment currencies.
     *
     * @return PaymentCurrency[]
     * @throws CurrencyException if currency does not exist with tat iso code
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
                if (!$currency = Plugin::getInstance()->getCurrencies()->getCurrencyByIso($paymentCurrency->iso)) {
                    throw new CurrencyException(Craft::t('commerce', 'No payment currency found with ISO code “{iso}”.', ['iso' => $paymentCurrency->iso]));
                }

                $paymentCurrency->setCurrency($currency);

                $this->_memoizePaymentCurrency($paymentCurrency);
            }
        }

        return $this->_allCurrenciesByIso;
    }

    /**
     * Get a payment currency by its ISO code.
     *
     * @param string $iso
     * @return PaymentCurrency
     * @throws CurrencyException if currency does not exist with tat iso code
     */
    public function getPaymentCurrencyByIso($iso): PaymentCurrency
    {
        if ($this->_allCurrenciesByIso === null) {
            $this->getAllPaymentCurrencies();
        }

        if (isset($this->_allCurrenciesByIso[$iso])) {
            return $this->_allCurrenciesByIso[$iso];
        }

        throw new CurrencyException(Craft::t('commerce', 'No currency found with ISO code “{iso}”.', ['iso' => $iso]));
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
     * Convert an amount in site's primary currency to a different currecny by its ISO code.
     *
     * @param float $amount This is the unit of price in the primary store currency
     * @param string $currency
     * @return float
     * @throws CurrencyException if currency not found by its ISO code
     */
    public function convert(float $amount, string $currency): float
    {
        $destinationCurrency = $this->getPaymentCurrencyByIso($currency);

        return $amount * $destinationCurrency->rate;
    }

    /**
     * Save a payment currency.
     *
     * @param PaymentCurrency $model
     * @param bool $runValidation should we validate this payment currency before saving.
     * @return bool
     * @throws Exception
     */
    public function savePaymentCurrency(PaymentCurrency $model, bool $runValidation = true): bool
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

        if ($runValidation && !$model->validate()) {
            Craft::info('Payment currency not saved due to validation error.', __METHOD__);

            return false;
        }


        $record->iso = strtoupper($model->iso);
        $record->primary = $model->primary;
        // If this rate is primary, the rate must be 1 since it is now the rate all prices are enter in as.
        $record->rate = $model->primary ? 1 : $model->rate;

        $record->save(false);

        // Now that we have a record ID, save it on the model
        $model->id = $record->id;

        if ($record->primary) {
            PaymentCurrencyRecord::updateAll(['primary' => 0], ['not', ['id' => $record->id]]);
        }

        return true;
    }

    /**
     * Delete a payment currency by its ID.
     *
     * @param $id
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
