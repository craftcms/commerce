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
     * @var PaymentCurrencies $sales
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
        $id1 = $this->fixtureData->data['Euro']['id'];
        $id2 = $this->fixtureData->data['Aussie']['id'];
        $eurCurrencyModel = $this->pc->getPaymentCurrencyById($id1);
        $audCurrencyModel = $this->pc->getPaymentCurrencyById($id2);

        // Install's USD plus 2 additional currencies.
        self::assertCount(3, $this->pc->getAllPaymentCurrencies());

        // $this->assertSame(1, $getAllCallCount, 'Test memoization of get all call.');
        self::assertNotNull($eurCurrencyModel);
        self::assertEquals($this->fixtureData->data['Euro']['iso'], $eurCurrencyModel->iso);
        self::assertNotNull($audCurrencyModel);
        self::assertEquals($this->fixtureData->data['Aussie']['iso'], $audCurrencyModel->iso);

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
        $id1 = $this->fixtureData->data['Euro']['id'];
        $id2 = $this->fixtureData->data['Aussie']['id'];
        $eurCurrencyModel = $this->pc->getPaymentCurrencyById($id1);
        $audCurrencyModel = $this->pc->getPaymentCurrencyById($id2);

        // Converting to the same base currency
        $iso = $this->pc->getPrimaryPaymentCurrencyIso();
        $converted = $this->pc->convert(10, $iso);
        self::assertEquals($converted, 10);

        // Converting to the EUR currency
        $iso = $eurCurrencyModel->iso;
        $converted = $this->pc->convert(10, $iso);
        self::assertEquals($converted, 20);

        // Converting to the AUD currency
        $iso = $audCurrencyModel->iso;
        $converted = $this->pc->convert(10, $iso); // ->convert only converts to the primary currency
        self::assertEquals($converted, 7);
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
    public function testConvertCurrency()
    {
        $id1 = $this->fixtureData->data['Euro']['id'];
        $id2 = $this->fixtureData->data['Aussie']['id'];
        $eurCurrencyModel = $this->pc->getPaymentCurrencyById($id1);
        $audCurrencyModel = $this->pc->getPaymentCurrencyById($id2);

        // Converting between EUR and primary USD
        $fromCurrency = $eurCurrencyModel->iso;
        $toCurrency = $this->pc->getPrimaryPaymentCurrencyIso();
        $converted = $this->pc->convertCurrency(20, $fromCurrency, $toCurrency);
        self::assertEquals($converted, 10);

        // Converting between AUD and primary USD
        $fromCurrency = $audCurrencyModel->iso;
        $toCurrency = $this->pc->getPrimaryPaymentCurrencyIso();
        $converted = $this->pc->convertCurrency(10, $fromCurrency, $toCurrency);
        self::assertEquals($converted, 14);

        // Converting between AUD and EUR
        $fromCurrency = $audCurrencyModel->iso;
        $toCurrency = $eurCurrencyModel->iso;
        $converted = $this->pc->convertCurrency(7, $fromCurrency, $toCurrency);
        self::assertEquals($converted, 20);
    }
}
