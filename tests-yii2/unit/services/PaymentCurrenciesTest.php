<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\services;

use Codeception\Test\Unit;
use craft\commerce\errors\CurrencyException;
use craft\commerce\events\PaymentCurrencyRateEvent;
use craft\commerce\Plugin;
use craft\commerce\services\PaymentCurrencies;
use CraftCms\Commerce\Payment\Models\PaymentCurrency as PaymentCurrencyRecord;
use craftcommercetests\fixtures\PaymentCurrenciesFixture;
use Money\Currency;
use Money\Money;
use UnitTester;
use yii\base\Event;
use yii\base\InvalidConfigException;

/**
 * Payment Currencies Test
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.2.14
 */
class PaymentCurrenciesTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected UnitTester $tester;

    /**
     * @var PaymentCurrencies $pc
     */
    protected PaymentCurrencies $pc;

    /**
     * @var PaymentCurrenciesFixture
     */
    protected PaymentCurrenciesFixture $fixtureData;

    /**
     * @return array
     */
    public function _fixtures(): array
    {
        return [
            'payment-currencies' => [
                'class' => PaymentCurrenciesFixture::class,
            ],
        ];
    }

    /**
     *
     */
    #[\Override]
    protected function _before(): void
    {
        parent::_before();

        $this->pc = Plugin::getInstance()->getPaymentCurrencies();
        $this->fixtureData = $this->tester->grabFixture('payment-currencies');
    }

    /**
     * @throws CurrencyException
     * @throws InvalidConfigException
     * @group PaymentCurrencies
     */
    public function testGetPaymentCurrenciesData(): void
    {
        $eurCurrencyModel = $this->pc->getPaymentCurrencyByIso('EUR');
        $audCurrencyModel = $this->pc->getPaymentCurrencyByIso('AUD');

        // Install's USD, plus 2 additional currencies in fixture data.
        self::assertCount(3, $this->pc->getAllPaymentCurrencies());

        // $this->assertSame(1, $getAllCallCount, 'Test memoization of get all call.');
        self::assertNotNull($eurCurrencyModel);
        self::assertEquals('EUR', $eurCurrencyModel->iso);
        self::assertNotNull($audCurrencyModel);
        self::assertEquals('AUD', $audCurrencyModel->iso);

        // Deafult install has a USD primary currency
        $iso = $this->pc->getPrimaryPaymentCurrencyIso();
        self::assertNotNull($iso);
        self::assertEquals('USD', $iso);
    }

    /**
     * @group PaymentCurrencies
     */
    public function testConvert(): void
    {
        $eurCurrencyModel = $this->pc->getPaymentCurrencyByIso('EUR');
        $audCurrencyModel = $this->pc->getPaymentCurrencyByIso('AUD');

        // Converting to the same base currency
        $iso = $this->pc->getPrimaryPaymentCurrencyIso();
        $converted = $this->pc->convert(10, $iso);
        self::assertEquals($converted, 10);

        // Converting to the EUR currency
        $iso = $eurCurrencyModel->iso;
        $converted = $this->pc->convert(10, $iso);
        self::assertEquals($converted, 5);

        // Converting to the AUD currency
        $iso = $audCurrencyModel->iso;
        $converted = $this->pc->convert(10, $iso);
        self::assertEquals($converted, 13);
    }

    /**
     * @group PaymentCurrencies
     */
    public function testConvertCurrency(): void
    {
        $eurCurrencyModel = $this->pc->getPaymentCurrencyByIso('EUR');
        $audCurrencyModel = $this->pc->getPaymentCurrencyByIso('AUD');

        // Converting from EUR to USD and back
        $converted = $this->pc->convertCurrency(20, $eurCurrencyModel->iso, $this->pc->getPrimaryPaymentCurrencyIso());
        self::assertEquals($converted, 40);
        $converted = $this->pc->convertCurrency(40, $this->pc->getPrimaryPaymentCurrencyIso(),  $eurCurrencyModel->iso);
        self::assertEquals($converted, 20);

        // Converting from AUD to USD and back
        $converted = $this->pc->convertCurrency(13, $audCurrencyModel->iso, $this->pc->getPrimaryPaymentCurrencyIso());
        self::assertEquals($converted, 10);
        $converted = $this->pc->convertCurrency(10, $this->pc->getPrimaryPaymentCurrencyIso(), $audCurrencyModel->iso);
        self::assertEquals($converted, 13);

        // Converting from AUD to EUR and back
        $converted = $this->pc->convertCurrency(13, $audCurrencyModel->iso, $eurCurrencyModel->iso);
        self::assertEquals($converted, 5);
        $converted = $this->pc->convertCurrency(5, $eurCurrencyModel->iso, $audCurrencyModel->iso);
        self::assertEquals($converted, 13);
    }

    /**
     * @group PaymentCurrencies
     */
    public function testConvertCurrencyException(): void
    {
        $this->expectException(CurrencyException::class);
        $this->pc->convertCurrency(20, 'aaa', 'bbb');
    }

    /**
     * @group PaymentCurrencies
     */
    public function testConvertException(): void
    {
        $this->expectException(CurrencyException::class);
        $this->pc->convert(20, 'aaa');
    }

    /**
     * @group PaymentCurrencies
     */
    public function testGetRateForReturnsRawRateWithoutHandler(): void
    {
        $eur = $this->pc->getPaymentCurrencyByIso('EUR');
        self::assertSame(0.5, $this->pc->getRateFor($eur));
    }

    /**
     * @group PaymentCurrencies
     */
    public function testGetRateForReturnsEventRate(): void
    {
        $handler = static function(PaymentCurrencyRateEvent $event) {
            if ($event->paymentCurrency->iso === 'EUR') {
                $event->rate = 0.25;
            }
        };
        Event::on(PaymentCurrencies::class, PaymentCurrencies::EVENT_DEFINE_PAYMENT_CURRENCY_RATE, $handler);

        try {
            $eur = $this->pc->getPaymentCurrencyByIso('EUR');
            self::assertSame(0.25, $this->pc->getRateFor($eur));

            $aud = $this->pc->getPaymentCurrencyByIso('AUD');
            self::assertSame(1.3, $this->pc->getRateFor($aud), 'Untouched currencies fall through to the raw rate.');
        } finally {
            Event::off(PaymentCurrencies::class, PaymentCurrencies::EVENT_DEFINE_PAYMENT_CURRENCY_RATE, $handler);
        }
    }

    /**
     * @group PaymentCurrencies
     */
    public function testConvertCurrencyUsesEventRate(): void
    {
        $handler = static function(PaymentCurrencyRateEvent $event) {
            if ($event->paymentCurrency->iso === 'EUR') {
                $event->rate = 0.25;
            }
        };
        Event::on(PaymentCurrencies::class, PaymentCurrencies::EVENT_DEFINE_PAYMENT_CURRENCY_RATE, $handler);

        try {
            $converted = $this->pc->convertCurrency(40, $this->pc->getPrimaryPaymentCurrencyIso(), 'EUR');
            self::assertSame(10.0, $converted);
        } finally {
            Event::off(PaymentCurrencies::class, PaymentCurrencies::EVENT_DEFINE_PAYMENT_CURRENCY_RATE, $handler);
        }
    }

    /**
     * @group PaymentCurrencies
     */
    public function testConvertAmountUsesEventRate(): void
    {
        $handler = static function(PaymentCurrencyRateEvent $event) {
            if ($event->paymentCurrency->iso === 'EUR') {
                $event->rate = 0.25;
            }
        };
        Event::on(PaymentCurrencies::class, PaymentCurrencies::EVENT_DEFINE_PAYMENT_CURRENCY_RATE, $handler);

        try {
            $usd = new Money(4000, new Currency('USD'));
            $converted = $this->pc->convertAmount($usd, 'EUR');
            self::assertSame('EUR', $converted->getCurrency()->getCode());
            self::assertSame('1000', $converted->getAmount());
        } finally {
            Event::off(PaymentCurrencies::class, PaymentCurrencies::EVENT_DEFINE_PAYMENT_CURRENCY_RATE, $handler);
        }
    }

    /**
     * The event must not affect the rate that gets persisted when saving a
     * payment currency — saving isn't a conversion.
     *
     * @group PaymentCurrencies
     */
    public function testSavePaymentCurrencyIgnoresEventRate(): void
    {
        $handler = static function(PaymentCurrencyRateEvent $event) {
            $event->rate = 999.0;
        };
        Event::on(PaymentCurrencies::class, PaymentCurrencies::EVENT_DEFINE_PAYMENT_CURRENCY_RATE, $handler);

        try {
            $eur = $this->pc->getPaymentCurrencyByIso('EUR');
            $originalRate = $eur->rate;
            $eur->rate = 0.75;

            self::assertTrue($this->pc->savePaymentCurrency($eur));

            $record = PaymentCurrencyRecord::find($eur->id);
            self::assertNotNull($record);
            self::assertEquals(0.75, $record->rate, 'Raw admin-entered rate is persisted, not the event rate.');

            // Restore for any tests that run after this one without isolation.
            $eur->rate = $originalRate;
            $this->pc->savePaymentCurrency($eur);
        } finally {
            Event::off(PaymentCurrencies::class, PaymentCurrencies::EVENT_DEFINE_PAYMENT_CURRENCY_RATE, $handler);
        }
    }
}
