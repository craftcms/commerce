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
use craft\commerce\models\Address;
use craft\commerce\models\LineItem;
use craft\commerce\models\TaxAddressZone;
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
    public function testAdjust($addressData, $lineItemData, $taxRateData, $expected)
    {
        $order = new Order();

        $address = new Address();
        $address->countryId = $this->pluginInstance->getCountries()->getCountryByIso($addressData['countryIso'])->id;
        $address->businessTaxId = isset($addressData['businessTaxId']) ? $addressData['businessTaxId'] : null;

        $order->setShippingAddress($address);

        $taxRates = [];
        foreach ($taxRateData as $item) {
            $rate = $this->make(TaxRate::class, [
                'getIsEverywhere' => !isset($item['zone']),
                'getTaxZone' => function() use ($item) {
                    if (isset($item['zone'])) {

                        $countryIds = [];
                        foreach ($item['zone']['countryIsos'] as $iso) {
                            $countryIds[] = $this->pluginInstance->getCountries()->getCountryByIso($iso)->id;
                        }

                        return $this->make(TaxAddressZone::class, [
                            'getCountryIds' => $countryIds,
                            'getIsCountryBased' => true,
                        ]);
                    }

                    return null;
                }
            ]);

            $rate->name = $item['name'];
            $rate->code = $item['code'];
            $rate->rate = $item['rate'];
            $rate->include = $item['include'];
            $rate->removeIncluded = $item['removeIncluded'] ?? false;
            $rate->isVat = $item['isVat'];
            $rate->removeVatIncluded = $item['removeVatIncluded'] ?? false;
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

        self::assertCount(count($expected['adjustments']), $adjustments, 'Total number of adjustments');

        foreach ($expected['adjustments'] as $index => $item) {
            self::assertEquals($item['amount'], round($adjustments[$index]->amount, 2), 'Adjustment amount');
            self::assertEquals($item['included'], $adjustments[$index]->included, 'Adjustment included');
            self::assertEquals($item['description'], $adjustments[$index]->description, 'Adjustment description');
            self::assertEquals($item['type'], $adjustments[$index]->type, 'Adjustment type');
        }

        self::assertEquals($expected['orderTotalQty'], $order->getTotalQty(), 'Order total quantity');
        self::assertEquals($expected['orderTotalPrice'], $order->getTotalPrice(), 'Order total price');
        self::assertEquals($expected['orderTotalTax'], $order->getTotalTax(), 'Order total tax');
        self::assertEquals($expected['orderTotalTaxIncluded'], $order->getTotalTaxIncluded(), 'Order total included tax');
    }

    /**
     * @return array[]
     */
    public function dataCases()
    {
        return [

            // Example 1) 10% included tax
            [
                [ // Address
                    'countryIso' => 'AU'
                ],
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
                        'taxable' => 'order_total_price',
                        'zone' => [
                            'countryIsos' => ['AU']
                        ]
                    ]
                ],
                [
                    'adjustments' => [
                        [
                            'type' => 'tax',
                            'amount' => 9.09,
                            'included' => true,
                            'description' => '10% inc'
                        ]
                    ],
                    'orderTotalPrice' => 100,
                    'orderTotalQty' => 1,
                    'orderTotalTax' => 0,
                    'orderTotalTaxIncluded' => 9.09,
                ]
            ],

            // Example 2) 10% not included
            [
                [ // Address
                    'countryIso' => 'AU'
                ],
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
                [
                    'adjustments' => [
                        [
                            'type' => 'tax',
                            'amount' => 10,
                            'included' => false,
                            'description' => '10%'
                        ]
                    ],
                    'orderTotalPrice' => 110,
                    'orderTotalQty' => 1,
                    'orderTotalTax' => 10,
                    'orderTotalTaxIncluded' => 0,
                ]
            ],

            // Example 3) 10% included, 2 line items, isVat
            [
                [ // Address
                    'countryIso' => 'NL'
                ],
                [ // Line Items
                    ['salePrice' => 100, 'qty' => 1], // 100 total price
                    ['salePrice' => 50, 'qty' => 2] // 100 total price
                ],
                [ // Tax Rates
                    [
                        'name' => 'Netherlands',
                        'code' => 'NLVAT',
                        'rate' => 0.1,
                        'include' => true,
                        'isVat' => true,
                        'taxable' => 'price_shipping'
                        // zone is everywhere
                    ]
                ],
                [
                    'adjustments' => [
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
                    'orderTotalPrice' => 200,
                    'orderTotalQty' => 3,
                    'orderTotalTax' => 0,
                    'orderTotalTaxIncluded' => 18.18,
                ]
            ],

            // Example 4) 10% tax that does not apply due to zone mismatch
            [
                [ // Address
                    'countryIso' => 'AU'
                ],
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
                        'taxable' => 'order_total_price',
                        'zone' => [
                            'countryIsos' => ['NL'] // Not AU on purpose to create mismatch
                        ]
                    ]
                ],
                [
                    'adjustments' => [],
                    'orderTotalPrice' => 100,
                    'orderTotalQty' => 1,
                    'orderTotalTax' => 0,
                    'orderTotalTaxIncluded' => 0,
                ]
            ],

            // Example 5) 10% tax that gets removed due to zone mismatch
            [
                [ // Address
                    'countryIso' => 'AU'
                ],
                [ // Line Items
                    ['salePrice' => 100, 'qty' => 1] // 100 total price
                ],
                [ // Tax Rates
                    [
                        'name' => 'Netherlands',
                        'code' => 'NLVAT',
                        'rate' => 0.1,
                        'include' => true,
                        'removeIncluded' => true,
                        'isVat' => false,
                        'taxable' => 'order_total_price',
                        'zone' => [
                            'countryIsos' => ['NL'] // Not AU on purpose to create mismatch
                        ]
                    ]
                ],
                [
                    'adjustments' => [
                        [
                            'type' => 'discount',
                            'amount' => -9.09,
                            'included' => false,
                            'description' => '10% inc'
                        ],
                    ],
                    'orderTotalPrice' => 90.91,
                    'orderTotalQty' => 1,
                    'orderTotalTax' => 0,
                    'orderTotalTaxIncluded' => 0,
                ]
            ],

            // Example 6) 10% tax that gets removed due to valid VAT ID
            [
                [ // Address
                    'countryIso' => 'CZ',
                    'businessTaxId' => 'CZ25666011'
                ],
                [ // Line Items
                    ['salePrice' => 100, 'qty' => 1] // 100 total price
                ],
                [ // Tax Rates
                    [
                        'name' => 'CZ Vat',
                        'code' => 'CZVAT',
                        'rate' => 0.1,
                        'include' => true,
                        'isVat' => true,
                        'removeVatIncluded' => true,
                        'taxable' => 'order_total_price',
                        'zone' => [
                            'countryIsos' => ['CZ'] // Not AU on purpose to create mismatch
                        ]
                    ]
                ],
                [
                    'adjustments' => [
                        [
                            'type' => 'discount',
                            'amount' => -9.09,
                            'included' => false,
                            'description' => '10% inc'
                        ],
                    ],
                    'orderTotalPrice' => 90.91,
                    'orderTotalQty' => 1,
                    'orderTotalTax' => 0,
                    'orderTotalTaxIncluded' => 0,
                ]
            ],

            // Example 7) 10% tax that does not apply since it has a valid tax ID, but does not remove
            [
                [ // Address
                    'countryIso' => 'CZ',
                    'businessTaxId' => 'CZ25666011'
                ],
                [ // Line Items
                    ['salePrice' => 100, 'qty' => 1] // 100 total price
                ],
                [ // Tax Rates
                    [
                        'name' => 'CZ Vat',
                        'code' => 'CZVAT',
                        'rate' => 0.1,
                        'include' => true,
                        'isVat' => true,
                        'removeVatIncluded' => false,
                        'taxable' => 'order_total_price',
                        'zone' => [
                            'countryIsos' => ['CZ'] // Not AU on purpose to create mismatch
                        ]
                    ]
                ],
                [
                    'adjustments' => [],
                    'orderTotalPrice' => 100,
                    'orderTotalQty' => 1,
                    'orderTotalTax' => 0,
                    'orderTotalTaxIncluded' => 0,
                ]
            ]
        ];
    }
}