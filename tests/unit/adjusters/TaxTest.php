<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\adjusters;

use Codeception\Test\Unit;
use craft\commerce\adjusters\Tax;
use craft\commerce\elements\Order;
use craft\commerce\models\LineItem;
use craft\commerce\models\TaxRate;
use craft\commerce\Plugin;

/**
 * CartTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.3.4
 */
class TaxTest extends Unit
{

    /**
     *
     */
    public $pluginInstance;

    /**
     *
     */
    public $originalEdition;

    /**
     *
     */
    protected function _before()
    {
        parent::_before();

        $this->pluginInstance = Plugin::getInstance();
        $this->originalEdition = $this->pluginInstance->edition;
        $this->pluginInstance->edition = Plugin::EDITION_PRO;
    }

    /**
     *
     */
    protected function _after()
    {
        parent::_after();

        $this->pluginInstance->edition = $this->originalEdition;
    }

    /**
     * @dataProvider dataCases
     */
    public function testAdjust($lineItemData, $taxRateData, $expectedAdjustmentData, $expectedOther)
    {
        $order = new Order();

        $taxRates = [];
        foreach ($taxRateData as $item) {
            $rate = new TaxRate();
            $rate->name = $item['name'];
            $rate->code = $item['code'];
            $rate->rate = $item['rate'];
            $rate->include = $item['include'];
            $rate->isVat = $item['isVat'];
            $rate->taxable = $item['taxable'];
            $taxRates[] = $rate;
        }

        $lineItems = [];
        foreach ($lineItemData as $item) {
            $lineItem = new LineItem();
            $lineItem->qty = $item['qty'];
            $lineItem->salePrice = $item['salePrice'];
            $lineItems[] = $lineItem;
        }

        $order->setLineItems($lineItems);

        $taxAdjuster = $this->make(Tax::class, [
            'getTaxRates' => $taxRates
        ]);

        $adjustments = $taxAdjuster->adjust($order);
        $order->setAdjustments($adjustments);

        self::assertEquals($expectedOther['orderTotalQty'], $order->getTotalQty(), 'Order total quantity');
        self::assertEquals($expectedOther['orderTotalPrice'], $order->getTotalPrice(), 'Order total price');
        self::assertEquals($expectedOther['orderTotalTax'], $order->getTotalTax(), 'Order total tax');
        self::assertEquals($expectedOther['orderTotalTaxIncluded'], $order->getTotalTaxIncluded(), 'Order total included tax');

        self::assertCount(count($expectedAdjustmentData), $adjustments, 'Total number of adjustments');
        foreach ($expectedAdjustmentData as $index => $item) {
            self::assertEquals($item['amount'], round($adjustments[$index]->amount, 2), 'Adjustment amount');
            self::assertEquals($item['included'], $adjustments[$index]->included, 'Adjustment included');
            self::assertEquals($item['description'], $adjustments[$index]->description, 'Adjustment description');
            self::assertEquals($item['type'], $adjustments[$index]->type, 'Adjustment type');
        }
    }

//    /**
//     * @param false $withValidVatId
//     * @return Address
//     */
//    private function _getAddress($withValidVatId = false)
//    {
//        $address = new Address();
//        $address->businessTaxId = $withValidVatId ? 'CZ25666011' : null;
//        $address->countryId = 1;
//
//        return $address;
//    }

    /**
     * @return array[]
     */
    public function dataCases()
    {
        return [

            // Example 1) 10% included tax
            [
                [ // Line Items
                    ['salePrice' => 100, 'qty' => 1] // 100 total price
                ],
                [ // Tax Rates
                    [
                        'name' => 'Australia',
                        'code' => 'GST',
                        'rate' => 0.1,
                        'include' => true,
                        'isVat' => false,
                        'taxable' => 'order_total_price'
                        // zone is everywhere
                    ]
                ],
                [ // Expected Adjustments
                    [
                        'type' => 'tax',
                        'amount' => 9.09,
                        'included' => true,
                        'description' => '10% inc'
                    ]
                ],
                [
                    'orderTotalPrice' => 100,
                    'orderTotalQty' => 1,
                    'orderTotalTax' => 0,
                    'orderTotalTaxIncluded' => 9.09,
                ]
            ],

            // Example 2) 10% not included
            [
                [ // Line Items
                    ['salePrice' => 100, 'qty' => 1] // 100 total price
                ],
                [ // Tax Rates
                    [
                        'name' => 'Australia',
                        'code' => 'GST',
                        'rate' => 0.1,
                        'include' => false,
                        'isVat' => false,
                        'taxable' => 'order_total_price'
                        // zone is everywhere
                    ]
                ],
                [ // Expected Adjustments
                    [
                        'type' => 'tax',
                        'amount' => 10,
                        'included' => false,
                        'description' => '10%'
                    ]
                ],
                [
                    'orderTotalPrice' => 110,
                    'orderTotalQty' => 1,
                    'orderTotalTax' => 10,
                    'orderTotalTaxIncluded' => 0,
                ]
            ],

            // Example 3) 10% included, 2 line items, isVat
            [
                [ // Line Items
                    ['salePrice' => 100, 'qty' => 1], // 100 total price
                    ['salePrice' => 50, 'qty' => 2] // 100 total price
                ],
                [ // Tax Rates
                    [
                        'name' => 'Netherlands',
                        'code' => 'EUNETHERVAT',
                        'rate' => 0.1,
                        'include' => true,
                        'isVat' => true,
                        'taxable' => 'price_shipping'
                        // zone is everywhere
                    ]
                ],
                [ // Expected Adjustments
                    [
                        'type' => 'tax',
                        'amount' => 9.09,
                        'included' => true,
                        'description' => '10% inc'
                    ],
                    [
                        'type' => 'tax',
                        'amount' => 9.09,
                        'included' => true,
                        'description' => '10% inc'
                    ]
                ],
                [
                    'orderTotalPrice' => 200,
                    'orderTotalQty' => 3,
                    'orderTotalTax' => 0,
                    'orderTotalTaxIncluded' => 18.18,
                ]
            ]

        ];
    }
}