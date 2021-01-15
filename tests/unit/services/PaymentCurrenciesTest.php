<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\services;

use Codeception\Stub\Expected;
use Codeception\Test\Unit;
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
     */
    public function testGetPaymentCurrencyById() {
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
}
