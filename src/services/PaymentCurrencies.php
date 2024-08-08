<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\services;

use Craft;
use craft\commerce\db\Table;
use craft\commerce\errors\CurrencyException;
use craft\commerce\helpers\Currency as CurrencyHelper;
use craft\commerce\models\PaymentCurrency;
use craft\commerce\Plugin;
use craft\commerce\records\PaymentCurrency as PaymentCurrencyRecord;
use craft\db\Query;
use craft\errors\SiteNotFoundException;
use craft\helpers\Db;
use Illuminate\Support\Collection;
use Money\Converter;
use Money\Currencies\ISOCurrencies;
use Money\Currency;
use Money\Exchange\FixedExchange;
use Money\Money;
use yii\base\Component;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\db\StaleObjectException;

/**
 * Payment currency service.
 *
 * @property PaymentCurrency[]|array $allPaymentCurrencies
 * @property PaymentCurrency|null $primaryPaymentCurrency the primary currency all prices are entered as
 * @property-read PaymentCurrency[] $nonPrimaryPaymentCurrencies
 * @property string $primaryPaymentCurrencyIso the primary currencies ISO code as a string
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class PaymentCurrencies extends Component
{
    /**
     * @var null|Collection<PaymentCurrency>[]
     */
    private ?array $_allPaymentCurrencies = null;

    /**
     * Get payment currency by its ID.
     *
     * @throws InvalidConfigException if currency has invalid iso code defined
     */
    public function getPaymentCurrencyById(int $id, ?int $storeId = null): ?PaymentCurrency
    {
        $storeId = $storeId ?? Plugin::getInstance()->getStores()->getCurrentStore()->id;

        $all = $this->getAllPaymentCurrencies($storeId);

        return $all->where('id', $id)->first();
    }

    /**
     * Get all payment currencies.
     *
     * @param int|null $storeId
     * @return Collection<PaymentCurrency>
     * @throws InvalidConfigException
     * @throws SiteNotFoundException
     */
    public function getAllPaymentCurrencies(?int $storeId = null): Collection
    {
        $storeId = $storeId ?? Plugin::getInstance()->getStores()->getCurrentStore()->id;

        if ($this->_allPaymentCurrencies === null || !isset($this->_allPaymentCurrencies[$storeId])) {
            $results = $this->_createPaymentCurrencyQuery()
                ->orderBy(['iso' => SORT_ASC])
                ->where(['storeId' => $storeId])
                ->all();

            if ($this->_allPaymentCurrencies === null) {
                $this->_allPaymentCurrencies = [];
            }

            foreach ($results as $result) {
                $paymentCurrency = Craft::createObject([
                    'class' => PaymentCurrency::class,
                    'attributes' => $result,
                ]);

                if (!isset($this->_allPaymentCurrencies[$paymentCurrency->storeId])) {
                    $this->_allPaymentCurrencies[$paymentCurrency->storeId] = collect();
                }

                $this->_allPaymentCurrencies[$paymentCurrency->storeId]->push($paymentCurrency);
            }
        }

        return $this->_allPaymentCurrencies[$storeId] ?? collect();
    }

    /**
     * Get a payment currency by its ISO code.
     *
     * @param string $iso
     * @param int|null $storeId
     * @return PaymentCurrency|null
     * @throws CurrencyException if currency does not exist with that iso code
     * @throws InvalidConfigException
     * @throws SiteNotFoundException
     */
    public function getPaymentCurrencyByIso(string $iso, ?int $storeId = null): ?PaymentCurrency
    {
        $storeId = $storeId ?? Plugin::getInstance()->getStores()->getCurrentStore()->id;

        return $this->getAllPaymentCurrencies($storeId)->firstWhere('iso', $iso);
    }

    /**
     * Return the primary currencies ISO code as a string.
     */
    public function getPrimaryPaymentCurrencyIso(?int $storeId = null): string
    {
        return $this->getPrimaryPaymentCurrency($storeId)?->iso ?? 'USD';
    }

    /**
     * Returns the primary currency all prices are entered as.
     *
     * @throws CurrencyException
     * @throws InvalidConfigException
     */
    public function getPrimaryPaymentCurrency(?int $storeId = null): ?PaymentCurrency
    {
        $storeId = $storeId ?? Plugin::getInstance()->getStores()->getCurrentStore()->id;

        $storeCurrency = Plugin::getInstance()->getStores()->getStoreById($storeId)->getCurrency();

        return $this->getAllPaymentCurrencies($storeId)->firstWhere(function(PaymentCurrency $currency) use ($storeCurrency) {
            return $currency->getCode() == $storeCurrency->getCode();
        });
    }

    /**
     * Returns the non primary payment currencies
     *
     * @return Collection<PaymentCurrency>
     * @throws CurrencyException
     * @throws InvalidConfigException
     */
    public function getNonPrimaryPaymentCurrencies(?int $storeId = null): Collection
    {
        $storeCurrency = Plugin::getInstance()->getStores()->getStoreById($storeId)->getCurrency();

        return $this->getAllPaymentCurrencies($storeId)->where(function(PaymentCurrency $currency) use ($storeCurrency) {
            return $currency->getCode() != $storeCurrency->getCode();
        });
    }

    /**
     * Convert an amount in site's primary currency to a different currency by its ISO code.
     *
     * @param float $amount This is the unit of price in the primary store currency
     * @throws CurrencyException if currency not found by its ISO code
     * @throws InvalidConfigException
     */
    public function convert(float $amount, string $currency): float
    {
        $destinationCurrency = $this->getPaymentCurrencyByIso($currency);

        if (!$destinationCurrency) {
            throw new CurrencyException('No payment currency found with ISO code: ' . $currency);
        }

        return $this->convertCurrency($amount, $this->getPrimaryPaymentCurrencyIso(), $currency);
    }

    /**
     * Convert an amount between currencies based on rates configured.
     *
     * @param float $amount
     * @param string $fromCurrency
     * @param string $toCurrency
     * @param bool $round
     * @return float
     * @throws CurrencyException if currency not found by its ISO code
     * @throws InvalidConfigException
     * @deprecated 5.0.0
     */
    public function convertCurrency(float $amount, string $fromCurrency, string $toCurrency, bool $round = false): float
    {
        $fromCurrency = $this->getPaymentCurrencyByIso($fromCurrency);
        $toCurrency = $this->getPaymentCurrencyByIso($toCurrency);

        if (!$fromCurrency) {
            throw new CurrencyException('Currency not found: ' . $fromCurrency);
        }

        if (!$toCurrency) {
            throw new CurrencyException('Currency not found: ' . $toCurrency);
        }

        if ($this->getPrimaryPaymentCurrency()->iso != $fromCurrency) {
            // now the amount is in the primary currency
            $amount /= $fromCurrency->rate;
        }

        $result = $amount * $toCurrency->rate;

        if ($round) {
            return CurrencyHelper::round($result, $toCurrency);
        }

        return $result;
    }


    /**
     * Save a payment currency.
     *
     * @param bool $runValidation should we validate this payment currency before saving.
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

        $originalIso = $record->iso;
        $record->iso = strtoupper($model->iso);
        $record->storeId = $model->storeId;
        // If this rate is primary, the rate must be 1 since it is now the rate all prices are enter in as.
        $record->rate = $model->getPrimary() ? 1 : $model->rate;

        $record->save(false);

        // Now that we have a record ID, save it on the model
        $model->id = $record->id;

        return true;
    }

    /**
     * Delete a payment currency by its ID.
     *
     * @param int $id
     * @return bool
     * @throws StaleObjectException
     */
    public function deletePaymentCurrencyById(int $id): bool
    {
        $paymentCurrency = PaymentCurrencyRecord::findOne($id);

        if (!$paymentCurrency) {
            return false;
        }

        $baseCurrency = Plugin::getInstance()->getPaymentCurrencies()->getPrimaryPaymentCurrency($paymentCurrency->storeId);

        Db::update(Table::ORDERS, ['paymentCurrency' => $baseCurrency->iso], ['paymentCurrency' => $paymentCurrency->iso, 'storeId' => $paymentCurrency->storeId]);

        return $paymentCurrency->delete();
    }

    private function _getExchange(?int $storeId = null)
    {
        $storeId = $storeId ?? Plugin::getInstance()->getStores()->getCurrentStore()->id;

        $storeCurrency = Plugin::getInstance()->getStores()->getStoreById($storeId)->getCurrency();
        $nonPrimaryCurrencies = $this->getNonPrimaryPaymentCurrencies($storeId)->mapWithKeys(fn(PaymentCurrency $currency) => [$currency->iso => (string)$currency->rate]);
        return new FixedExchange([
            $storeCurrency->getCode() => $nonPrimaryCurrencies->all(),
        ]);
    }

    /**
     * @param Money $amount
     * @param Currency|string $currency
     * @param int|null $storeId
     * @return Money
     * @throws CurrencyException
     * @throws InvalidConfigException
     * @throws \craft\errors\SiteNotFoundException
     * @since 5.0.0
     */
    public function convertAmount(Money $amount, Currency|string $currency, ?int $storeId = null): Money
    {
        if (is_string($currency)) {
            $currency = new Currency($currency);
        }

        $storeId = $storeId ?? Plugin::getInstance()->getStores()->getCurrentStore()->id;

        $fromPaymentCurrency = $this->getPaymentCurrencyByIso($amount->getCurrency(), $storeId);
        $toPaymentCurrency = $this->getPaymentCurrencyByIso($currency, $storeId);

        if (!$fromPaymentCurrency || !$toPaymentCurrency) {
            throw new CurrencyException('Currency not found in store: ' . $currency);
        }

        $converter = new Converter(new ISOCurrencies(), $this->_getExchange($storeId));
        return $converter->convert($amount, $toPaymentCurrency->getCurrency());
    }

    /**
     * Returns a Query object prepped for retrieving Emails
     */
    private function _createPaymentCurrencyQuery(): Query
    {
        return (new Query())
            ->select([
                'dateCreated',
                'dateUpdated',
                'id',
                'iso',
                'storeId',
                'rate',
            ])
            ->from([Table::PAYMENTCURRENCIES]);
    }
}
