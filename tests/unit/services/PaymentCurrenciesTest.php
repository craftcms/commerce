<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\services;

use Codeception\Stub\Expected;
use Codeception\Test\Unit;
use craft\commerce\errors\CurrencyException;
use craft\commerce\models\PaymentCurrency;
use craft\commerce\Plugin;

use craft\commerce\services\PaymentCurrencies;
use craftcommercetests\fixtures\PaymentCurrenciesFixture;
use UnitTester;

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
    protected $tester;

    /**
     * @var PaymentCurrencies $pc
     */
    protected $pc;

    /**
     * @var PaymentCurrenciesFixture
     */
    protected $fixtureData;

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
    protected function _before()
    {
        parent::_before();

        $this->pc = Plugin::getInstance()->getPaymentCurrencies();
        $this->fixtureData = $this->tester->grabFixture('payment-currencies');
    }

    /**
     * @throws \yii\base\InvalidConfigException
     * @group PaymentCurrencies
     */
    public function testGetPaymentCurrenciesData()
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
    public function testConvert()
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
    public function testConvertCurrency()
    {
        $eurCurrencyModel = $this->pc->getPaymentCurrencyByIso('EUR');
        $audCurrencyModel = $this->pc->getPaymentCurrencyByIso('AUD');

        // Converting from EUR to USD
        $converted = $this->pc->convertCurrency(20, $eurCurrencyModel->iso, $this->pc->getPrimaryPaymentCurrencyIso());
        self::assertEquals($converted, 40);

        // Converting from AUD to USD
        $converted = $this->pc->convertCurrency(13, $audCurrencyModel->iso, $this->pc->getPrimaryPaymentCurrencyIso());
        self::assertEquals($converted, 10);

        // Converting from USD to AUD
        $converted = $this->pc->convertCurrency(10, $this->pc->getPrimaryPaymentCurrencyIso(), $audCurrencyModel->iso);
        self::assertEquals($converted, 13);

        // Converting from AUD to EUR
        $converted = $this->pc->convertCurrency(13, $audCurrencyModel->iso, $eurCurrencyModel->iso);
        self::assertEquals($converted, 5);
    }

    /**
     * @group PaymentCurrencies
     */
    public function testConvertCurrencyException()
    {
        $this->expectException(CurrencyException::class);
        $this->pc->convertCurrency(20, 'aaa', 'bbb');
    }

    /**
     * @group PaymentCurrencies
     */
    public function testConvertException()
    {
        $this->expectException(CurrencyException::class);
        $this->pc->convert(20, 'aaa');
    }
}
