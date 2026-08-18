<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Payment;

use craft\commerce\Plugin;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Payment\Events\PaymentCurrencyRateEvent;
use CraftCms\Commerce\Payment\Models\PaymentCurrency;
use CraftCms\Commerce\Payment\Models\Transaction;
use CraftCms\Commerce\Payment\Records\PaymentCurrency as PaymentCurrencyRecord;
use CraftCms\Commerce\Store\Stores;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Money\Converter;
use Money\Currencies\ISOCurrencies;
use Money\Currency;
use Money\Exchange\FixedExchange;
use Money\Money;
use function CraftCms\Cms\t;

#[Singleton]
class PaymentCurrencies
{
    /**
     * The event that is triggered when a payment currency rate is being resolved.
     * Set `$event->rate` to override the rate used for conversions and historical transaction snapshots.
     *
     * @since 5.7.0
     */
    public const EVENT_DEFINE_PAYMENT_CURRENCY_RATE = 'definePaymentCurrencyRate';

    /** @var array<int, Collection<int, PaymentCurrency>>|null */
    private ?array $allPaymentCurrencies = null;

    /**
     * Returns the rate for a payment currency, after giving event handlers a chance to override it.
     *
     * @since 5.7.0
     */
    public function getRateFor(PaymentCurrency $currency, ?Transaction $transaction = null): float
    {
        $event = new PaymentCurrencyRateEvent(
            rate: $currency->rate,
            paymentCurrency: $currency,
            transaction: $transaction,
        );

        // TODO: migrate event firing to Laravel once the event system is bridged
        /** @phpstan-ignore-next-line */
        if (Plugin::getInstance()->getPaymentCurrencies()->hasEventHandlers(self::EVENT_DEFINE_PAYMENT_CURRENCY_RATE)) {
            /** @phpstan-ignore-next-line */
            Plugin::getInstance()->getPaymentCurrencies()->trigger(self::EVENT_DEFINE_PAYMENT_CURRENCY_RATE, $event);
        }

        return $event->rate;
    }

    public function getPaymentCurrencyById(int $id, ?int $storeId = null): ?PaymentCurrency
    {
        $storeId ??= $this->currentStoreId();

        return $this->getAllPaymentCurrencies($storeId)->firstWhere('id', $id);
    }

    /**
     * @return Collection<int, PaymentCurrency>
     */
    public function getAllPaymentCurrencies(?int $storeId = null): Collection
    {
        $storeId ??= $this->currentStoreId();

        if ($this->allPaymentCurrencies === null || !isset($this->allPaymentCurrencies[$storeId])) {
            $rows = DB::table(Table::PAYMENTCURRENCIES)
                ->select(['dateCreated', 'dateUpdated', 'id', 'iso', 'storeId', 'rate'])
                ->orderBy('iso')
                ->where('storeId', $storeId)
                ->get()
                ->all();

            $this->allPaymentCurrencies ??= [];

            foreach ($rows as $row) {
                $paymentCurrency = new PaymentCurrency((array) $row);

                $this->allPaymentCurrencies[$paymentCurrency->storeId] ??= collect();
                $this->allPaymentCurrencies[$paymentCurrency->storeId]->push($paymentCurrency);
            }
        }

        return $this->allPaymentCurrencies[$storeId] ?? collect();
    }

    public function getPaymentCurrencyByIso(string $iso, ?int $storeId = null): ?PaymentCurrency
    {
        $storeId ??= $this->currentStoreId();

        return $this->getAllPaymentCurrencies($storeId)->firstWhere('iso', $iso);
    }

    public function getPrimaryPaymentCurrencyIso(?int $storeId = null): string
    {
        return $this->getPrimaryPaymentCurrency($storeId)?->iso ?? 'USD'; /** @phpstan-ignore-line */
    }

    public function getPrimaryPaymentCurrency(?int $storeId = null): ?PaymentCurrency
    {
        $storeId ??= $this->currentStoreId();

        /** @phpstan-ignore-next-line */
        $storeCurrency = app(Stores::class)->getStoreById($storeId)->getCurrency();

        return $this->getAllPaymentCurrencies($storeId)->firstWhere(
            fn(PaymentCurrency $currency) => $currency->getCode() === $storeCurrency->getCode(),
        );
    }

    /**
     * @return Collection<int, PaymentCurrency>
     */
    public function getNonPrimaryPaymentCurrencies(?int $storeId = null): Collection
    {
        /** @phpstan-ignore-next-line */
        $storeCurrency = app(Stores::class)->getStoreById($storeId)->getCurrency();

        return $this->getAllPaymentCurrencies($storeId)->where(
            fn(PaymentCurrency $currency) => $currency->getCode() !== $storeCurrency->getCode(),
        );
    }

    /**
     * Convert an amount in the store's primary currency to another by ISO code.
     *
     * @throws \RuntimeException
     */
    public function convert(float $amount, string $iso): float
    {
        $destination = $this->getPaymentCurrencyByIso($iso);

        if (!$destination) {
            throw new \RuntimeException('No payment currency found with ISO code: ' . $iso);
        }

        $primary = $this->getPrimaryPaymentCurrency();
        if (!$primary) {
            return $amount * $this->getRateFor($destination);
        }

        // Amount is already in the primary currency; convert to destination.
        return $amount * $this->getRateFor($destination);
    }

    public function savePaymentCurrency(PaymentCurrency $model, bool $runValidation = true): bool
    {
        if ($model->id) {
            $record = PaymentCurrencyRecord::find($model->id);
            if (!$record) {
                throw new \RuntimeException(t('No currency exists with the ID "{id}"', ['id' => $model->id], category: 'commerce'));
            }
        } else {
            $record = new PaymentCurrencyRecord();
        }

        if ($runValidation && !$model->validate()) {
            return false;
        }

        $record->iso = strtoupper((string) $model->iso);
        $record->storeId = $model->storeId;
        // If this rate is primary, the rate must be 1.
        $record->rate = $model->getPrimary() ? 1 : $model->rate;

        $record->save();

        $model->id = $record->id;

        return true;
    }

    public function deletePaymentCurrencyById(int $id): bool
    {
        $paymentCurrency = PaymentCurrencyRecord::find($id);

        if (!$paymentCurrency) {
            return false;
        }

        $baseCurrency = $this->getPrimaryPaymentCurrency($paymentCurrency->storeId);

        DB::table(Table::ORDERS)
            ->where('paymentCurrency', $paymentCurrency->iso)
            ->where('storeId', $paymentCurrency->storeId)
            ->update(['paymentCurrency' => $baseCurrency->iso]);

        return (bool) $paymentCurrency->delete();
    }

    /**
     * @throws \RuntimeException
     */
    public function convertAmount(Money $amount, Currency|string $currency, ?int $storeId = null): Money
    {
        if (is_string($currency)) {
            $currency = new Currency($currency);
        }

        $storeId ??= $this->currentStoreId();

        $fromPaymentCurrency = $this->getPaymentCurrencyByIso($amount->getCurrency()->getCode(), $storeId);
        $toPaymentCurrency = $this->getPaymentCurrencyByIso($currency->getCode(), $storeId);

        if (!$fromPaymentCurrency || !$toPaymentCurrency) {
            throw new \RuntimeException('Currency not found in store: ' . $currency->getCode());
        }

        $converter = new Converter(new ISOCurrencies(), $this->buildExchange($storeId));
        return $converter->convert($amount, $toPaymentCurrency->getCurrency());
    }

    private function buildExchange(?int $storeId = null): FixedExchange
    {
        $storeId ??= $this->currentStoreId();

        /** @phpstan-ignore-next-line */
        $storeCurrency = app(Stores::class)->getStoreById($storeId)->getCurrency();
        $nonPrimaryCurrencies = $this->getNonPrimaryPaymentCurrencies($storeId)
            ->mapWithKeys(fn(PaymentCurrency $c) => [$c->iso => (string) $this->getRateFor($c)]);

        $exchange = [$storeCurrency->getCode() => $nonPrimaryCurrencies->all()];

        foreach ($nonPrimaryCurrencies->all() as $iso => $rate) {
            $exchange[$iso] = [$storeCurrency->getCode() => (string) (1 / (float) $rate)];
        }

        return new FixedExchange($exchange);
    }

    private function currentStoreId(): int
    {
        /** @phpstan-ignore-next-line */
        return app(Stores::class)->getCurrentStore()->id;
    }
}
