<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\services;

use Codeception\Stub\Expected;
use Codeception\Test\Unit;
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
     * @dataProvider convertDataProvider
     * @param $iso
     * @param $paymentCurrency
     * @param $amount
     * @param $convertedAmount
     * @throws \craft\commerce\errors\CurrencyException
     */
    public function testConvert($iso, $paymentCurrency, $amount, $convertedAmount, $exception)
    {
        /**
         * @var PaymentCurrencies $paymentCurrenciesService
         */
        $paymentCurrenciesService = $this->make(PaymentCurrencies::class, [
            'getPaymentCurrencyByIso' => function($currencyIso) use ($paymentCurrency) {
                return $paymentCurrency;
            },
        ]);

        if ($exception) {
            $this->expectExceptionMessage('No payment currency found with ISO code “' . $iso . '”.');
            $paymentCurrenciesService->convert($amount, $iso);
        } else {
            self::assertEquals($convertedAmount, $paymentCurrenciesService->convert($amount, $iso));
        }
    }

    /**
     * @throws \yii\base\InvalidConfigException
     */
    public function testGetPaymentCurrencyById()
    {
        /**
         * @var PaymentCurrencies $paymentCurrenciesService
         */
        $paymentCurrenciesService = $this->make(PaymentCurrencies::class, [
            'getAllPaymentCurrencies' => Expected::exactly(1, [$this, 'getAllPaymentCurrencies']),
        ]);

        $paymentCurrenciesService->getPaymentCurrencyById($this->fixtureData->data['craftCoin']['id']);

        $coinModel = $this->pc->getPaymentCurrencyById($this->fixtureData->data['craftCoin']['id']);
        $tokenModel = $this->pc->getPaymentCurrencyById($this->fixtureData->data['ptTokens']['id']);

        // $this->assertSame(1, $getAllCallCount, 'Test memoization of get all call.');
        self::assertNotNull($coinModel);
        self::assertEquals($this->fixtureData->data['craftCoin']['iso'], $coinModel->iso);
        self::assertNotNull($tokenModel);
        self::assertEquals($this->fixtureData->data['ptTokens']['iso'], $tokenModel->iso);
    }

    /**
     * @return array[]
     */
    public function convertDataProvider(): array
    {
        return [
            ['xxx', new PaymentCurrency(['rate' => 0.5, 'iso' => 'xxx']), 10, 5, false],
            ['xxx', new PaymentCurrency(['rate' => 2, 'iso' => 'xxx']), 10, 20, false],
            ['xyz', null, 10, 5, true],
        ];
    }
}
