<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\adjusters;

use Codeception\Test\Unit;
use Craft;
use craft\commerce\adjusters\Tax;
use craft\commerce\elements\conditions\addresses\ZoneAddressCondition;
use craft\commerce\elements\Order;
use craft\commerce\models\LineItem;
use craft\commerce\models\TaxAddressZone;
use craft\commerce\models\TaxRate;
use craft\commerce\Plugin;
use craft\elements\Address;
use craft\elements\conditions\addresses\CountryConditionRule;
use craft\helpers\Json;
use craft\helpers\StringHelper;

/**
 * CartTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.3.4
 */
class TaxTest extends Unit
{
    /**
     * @var Plugin|null
     */
    public ?Plugin $pluginInstance;

    /**
     * @inheritdoc
     */
    protected function _before(): void
    {
        parent::_before();

        // start with fresh cache
        Craft::$app->getCache()->flush();
        $this->pluginInstance = Plugin::getInstance();
    }

    /**
     * @inheritdoc
     */
    protected function _after(): void
    {
        parent::_after();
    }

    /**
     * @dataProvider dataCases
     */
    public function testAdjust($addressData, $lineItemData, $taxRateData, $expected): void
    {
        $order = new Order();

        $address = new Address();
        $address->countryCode = $addressData['countryCode'];
        $address->organizationTaxId = $addressData['organizationTaxId'] ?? null;

        $order->setShippingAddress($address);

        $taxRates = [];
        foreach ($taxRateData as $item) {
            $rate = $this->make(TaxRate::class, [
                'getIsEverywhere' => !isset($item['zone']),
                'getTaxZone' => function() use ($item) {
                    if (isset($item['zone'])) {
                        $zone = $this->make(TaxAddressZone::class, []);

                        if (isset($item['zone']['condition'])) {
                            $zone->setCondition($item['zone']['condition']);
                        }
                        return $zone;
                    }

                    return null;
                },
            ]);

            $rate->name = $item['name'];
            $rate->code = $item['code'];
            $rate->rate = $item['rate'];
            $rate->include = $item['include'];
            $rate->removeIncluded = $item['removeIncluded'] ?? false;
            $rate->isVat = $item['isVat'];
            $rate->removeVatIncluded = $item['removeVatIncluded'] ?? false;
            $rate->taxable = $item['taxable'];
            $rate->taxCategoryId = $item['taxCategoryId'];
            $taxRates[] = $rate;
        }

        $lineItems = [];
        foreach ($lineItemData as $item) {
            $lineItem = new LineItem();
            $lineItem->qty = $item['qty'];
            $lineItem->salePrice = $item['salePrice'];
            $lineItem->taxCategoryId = 1;
            $lineItems[] = $lineItem;
        }

        $order->setLineItems($lineItems);

        $taxAdjuster = $this->make(Tax::class, [
            'getTaxRates' => $taxRates,
            'validateVatNumber' => function($vatNum) use ($addressData) {
                return $addressData['_validateVat'] ?? false;
            },
        ]);

        $adjustments = $taxAdjuster->adjust($order);
        $order->setAdjustments($adjustments);

        self::assertCount(count($expected['adjustments']), $adjustments, 'Total number of adjustments');

        foreach ($expected['adjustments'] as $index => $item) {
            self::assertEquals($item['type'], $adjustments[$index]->type, 'Adjustment type');
            self::assertEquals($item['amount'], round($adjustments[$index]->amount, 2), 'Adjustment amount');
            self::assertEquals($item['included'], $adjustments[$index]->included, 'Adjustment included');
            self::assertEquals($item['description'], $adjustments[$index]->description, 'Adjustment description');
        }

        self::assertEquals($expected['orderTotalQty'], $order->getTotalQty(), 'Order total quantity');
        self::assertEquals($expected['orderTotalPrice'], $order->getTotalPrice(), 'Order total price');
        self::assertEquals($expected['orderTotalTax'], round($order->getTotalTax(), 2), 'Order total tax');
        self::assertEquals($expected['orderTotalTaxIncluded'], round($order->getTotalTaxIncluded(), 2), 'Order total included tax');
    }

    /**
     * @return array[]
     */
    public function dataCases(): array
    {
        $uid = StringHelper::UUID();
        return [

            // Example 1) 10% included tax
            'tax-10pct-included' => [
                [ // Address
                    'countryCode' => 'AU',
                ],
                [ // Line Items
                    ['salePrice' => 100, 'qty' => 1], // 100 total price
                ],
                [ // Tax Rates
                    [
                        'name' => 'Australia',
                        'code' => 'GST',
                        'taxCategoryId' => 1,
                        'rate' => 0.1,
                        'include' => true,
                        'isVat' => false,
                        'taxable' => 'order_total_price',
                        'zone' => [
                            'condition' => [
                                'class' => ZoneAddressCondition::class,
                                'config' => '{"elementType":null,"fieldContext":"global"}',
                                'conditionRules' => [
                                    [
                                        'uid' => $uid,
                                        'class' => CountryConditionRule::class,
                                        'type' => Json::encode([
                                            'class' => CountryConditionRule::class,
                                            'uid' => $uid,
                                            'operator' => 'in',
                                            'values' => ['AU'],
                                        ]),
                                        'operator' => 'in',
                                        'values' => [
                                            'AU',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                [
                    'adjustments' => [
                        [
                            'type' => 'tax',
                            'amount' => 9.09,
                            'included' => true,
                            'description' => '10%',
                        ],
                    ],
                    'orderTotalPrice' => 100,
                    'orderTotalQty' => 1,
                    'orderTotalTax' => 0,
                    'orderTotalTaxIncluded' => 9.09,
                ],
            ],

            // Example 2) 10% not included
            'tax-10pct-not-included' => [
                [ // Address
                    'countryCode' => 'AU',
                ],
                [ // Line Items
                    ['salePrice' => 100, 'qty' => 1], // 100 total price
                ],
                [ // Tax Rates
                    [
                        'name' => 'Australia',
                        'code' => 'GST',
                        'taxCategoryId' => 1,
                        'rate' => 0.1,
                        'include' => false,
                        'isVat' => false,
                        'taxable' => 'order_total_price',
                        // zone is everywhere
                    ],
                ],
                [
                    'adjustments' => [
                        [
                            'type' => 'tax',
                            'amount' => 10,
                            'included' => false,
                            'description' => '10%',
                        ],
                    ],
                    'orderTotalPrice' => 110,
                    'orderTotalQty' => 1,
                    'orderTotalTax' => 10,
                    'orderTotalTaxIncluded' => 0,
                ],
            ],

            // Example 3) 10% included, 2 line items, isVat
            'tax-10pct-included-2-line-items' => [
                [ // Address
                    'countryCode' => 'NL',
                ],
                [ // Line Items
                    ['salePrice' => 100, 'qty' => 1], // 100 total price
                    ['salePrice' => 50, 'qty' => 2], // 100 total price
                ],
                [ // Tax Rates
                    [
                        'name' => 'Netherlands',
                        'code' => 'NLVAT',
                        'taxCategoryId' => 1,
                        'rate' => 0.1,
                        'include' => true,
                        'isVat' => true,
                        'taxable' => 'price_shipping',
                        // zone is everywhere
                    ],
                ],
                [
                    'adjustments' => [
                        [
                            'type' => 'tax',
                            'amount' => 9.09,
                            'included' => true,
                            'description' => '10%',
                        ],
                        [
                            'type' => 'tax',
                            'amount' => 9.09,
                            'included' => true,
                            'description' => '10%',
                        ],
                    ],
                    'orderTotalPrice' => 200,
                    'orderTotalQty' => 3,
                    'orderTotalTax' => 0,
                    'orderTotalTaxIncluded' => 18.18,
                ],
            ],

            // Example 4) 10% tax that does not apply due to zone mismatch
            'tax-zone-mismatch-1' => [
                [ // Address
                    'countryCode' => 'AU',
                ],
                [ // Line Items
                    ['salePrice' => 100, 'qty' => 1], // 100 total price
                ],
                [ // Tax Rates
                    [
                        'name' => 'Australia',
                        'code' => 'GST',
                        'taxCategoryId' => 1,
                        'rate' => 0.1,
                        'include' => false,
                        'isVat' => false,
                        'taxable' => 'order_total_price',
                        'zone' => [
                            'condition' => [
                                'class' => ZoneAddressCondition::class,
                                'config' => '{"elementType":null,"fieldContext":"global"}',
                                'conditionRules' => [
                                    [
                                        'uid' => $uid,
                                        'class' => CountryConditionRule::class,
                                        'type' => Json::encode([
                                            'class' => CountryConditionRule::class,
                                            'uid' => $uid,
                                            'operator' => 'in',
                                            'values' => ['NL'],
                                        ]),
                                        'operator' => 'in',
                                        'values' => [
                                            'NL',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                [
                    'adjustments' => [],
                    'orderTotalPrice' => 100,
                    'orderTotalQty' => 1,
                    'orderTotalTax' => 0,
                    'orderTotalTaxIncluded' => 0,
                ],
            ],

            // Example 5) 10% tax that gets removed due to zone mismatch
            'tax-zone-mismatch-2' => [
                [ // Address
                    'countryCode' => 'AU',
                ],
                [ // Line Items
                    ['salePrice' => 100, 'qty' => 1], // 100 total price
                ],
                [ // Tax Rates
                    [
                        'name' => 'Netherlands',
                        'code' => 'NLVAT',
                        'taxCategoryId' => 1,
                        'rate' => 0.1,
                        'include' => true,
                        'removeIncluded' => true,
                        'isVat' => false,
                        'taxable' => 'order_total_price',
                        'zone' => [
                            'condition' => [
                                'class' => ZoneAddressCondition::class,
                                'config' => '{"elementType":null,"fieldContext":"global"}',
                                'conditionRules' => [
                                    [
                                        'uid' => $uid,
                                        'class' => CountryConditionRule::class,
                                        'type' => Json::encode([
                                            'class' => CountryConditionRule::class,
                                            'uid' => $uid,
                                            'operator' => 'in',
                                            'values' => ['NL'],
                                        ]),
                                        'operator' => 'in',
                                        'values' => [
                                            'NL', // Not AU on purpose to create mismatch
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                [
                    'adjustments' => [
                        [
                            'type' => 'discount',
                            'amount' => -9.09,
                            'included' => false,
                            'description' => '10%',
                        ],
                    ],
                    'orderTotalPrice' => 90.91,
                    'orderTotalQty' => 1,
                    'orderTotalTax' => 0,
                    'orderTotalTaxIncluded' => 0,
                ],
            ],

            // Example 6) 10% tax that gets removed due to valid VAT ID
            'tax-valid-vat-1' => [
                [ // Address
                    'countryCode' => 'CZ',
                    'organizationTaxId' => 'CZ25666011',
                    '_validateVat' => true,
                ],
                [ // Line Items
                    ['salePrice' => 100, 'qty' => 1], // 100 total price
                ],
                [ // Tax Rates
                    [
                        'name' => 'CZ Vat',
                        'code' => 'CZVAT',
                        'taxCategoryId' => 1,
                        'rate' => 0.1,
                        'include' => true,
                        'isVat' => true,
                        'removeVatIncluded' => true,
                        'taxable' => 'order_total_price',
                        'zone' => [
                            'condition' => [
                                'class' => ZoneAddressCondition::class,
                                'config' => '{"elementType":null,"fieldContext":"global"}',
                                'conditionRules' => [
                                    [
                                        'uid' => $uid,
                                        'class' => CountryConditionRule::class,
                                        'type' => Json::encode([
                                            'class' => CountryConditionRule::class,
                                            'uid' => $uid,
                                            'operator' => 'in',
                                            'values' => ['CZ'],
                                        ]),
                                        'operator' => 'in',
                                        'values' => [
                                            'CZ',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                [
                    'adjustments' => [
                        [
                            'type' => 'discount',
                            'amount' => -9.09,
                            'included' => false,
                            'description' => '10%',
                        ],
                    ],
                    'orderTotalPrice' => 90.91,
                    'orderTotalQty' => 1,
                    'orderTotalTax' => 0,
                    'orderTotalTaxIncluded' => 0,
                ],
            ],

            // Example 7) 10% included tax that does not apply since it has a valid tax ID, but does not remove
            'tax-valid-vat-2' => [
                [ // Address
                    'countryCode' => 'CZ',
                    'organizationTaxId' => 'CZ25666011',
                    '_validateVat' => true,
                ],
                [ // Line Items
                    ['salePrice' => 100, 'qty' => 1], // 100 total price
                ],
                [ // Tax Rates
                    [
                        'name' => 'CZ Vat',
                        'code' => 'CZVAT',
                        'taxCategoryId' => 1,
                        'rate' => 0.1,
                        'include' => true,
                        'isVat' => true,
                        'removeVatIncluded' => false,
                        'taxable' => 'order_total_price',
                        'zone' => [
                            'condition' => [
                                'class' => ZoneAddressCondition::class,
                                'config' => '{"elementType":null,"fieldContext":"global"}',
                                'conditionRules' => [
                                    [
                                        'uid' => $uid,
                                        'class' => CountryConditionRule::class,
                                        'type' => Json::encode([
                                            'class' => CountryConditionRule::class,
                                            'uid' => $uid,
                                            'operator' => 'in',
                                            'values' => ['CZ'],
                                        ]),
                                        'operator' => 'in',
                                        'values' => [
                                            'CZ',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                [
                    'adjustments' => [],
                    'orderTotalPrice' => 100,
                    'orderTotalQty' => 1,
                    'orderTotalTax' => 0,
                    'orderTotalTaxIncluded' => 0,
                ],
            ],

            // Example 6) 10% tax that does not get removed due to an invalid VAT ID
            'tax-invalid-vat-1' => [
                [ // Address
                    'countryCode' => 'CZ',
                    'organizationTaxId' => 'CZ99999999',
                    '_validateVat' => false,
                ],
                [ // Line Items
                    ['salePrice' => 100, 'qty' => 1], // 100 total price
                ],
                [ // Tax Rates
                    [
                        'name' => 'CZ Vat',
                        'code' => 'CZVAT',
                        'taxCategoryId' => 1,
                        'rate' => 0.1,
                        'include' => true,
                        'isVat' => true,
                        'removeVatIncluded' => true,
                        'taxable' => 'order_total_price',
                        'zone' => [
                            'condition' => [
                                'class' => ZoneAddressCondition::class,
                                'config' => '{"elementType":null,"fieldContext":"global"}',
                                'conditionRules' => [
                                    [
                                        'uid' => $uid,
                                        'class' => CountryConditionRule::class,
                                        'type' => Json::encode([
                                            'class' => CountryConditionRule::class,
                                            'uid' => $uid,
                                            'operator' => 'in',
                                            'values' => ['CZ'],
                                        ]),
                                        'operator' => 'in',
                                        'values' => [
                                            'CZ',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                [
                    'adjustments' => [
                        [
                            'type' => 'tax',
                            'description' => '10%',
                            'included' => true,
                            'amount' => 9.09,
                        ],
                    ],
                    'orderTotalPrice' => 100,
                    'orderTotalQty' => 1,
                    'orderTotalTax' => 0,
                    'orderTotalTaxIncluded' => 9.09,
                ],
            ],

            // Example 7) line item taxable VAT 20% tax
            'tax-20pct-vat-not-included-taxable-line-item-price' => [
                [ // Address
                    'countryCode' => 'UK',
                ],
                [ // Line Items
                    ['salePrice' => 49.17, 'qty' => 1], // 49.17 total price
                ],
                [ // Tax Rates
                    [
                        'name' => 'UK',
                        'code' => 'VAT',
                        'taxCategoryId' => 1,
                        'rate' => 0.2,
                        'include' => false,
                        'isVat' => true,
                        'taxable' => 'price',
                    ],
                ],
                [
                    'adjustments' => [
                        [
                            'type' => 'tax',
                            'amount' => 9.83,
                            'included' => false,
                            'description' => '20%',
                        ],
                    ],
                    'orderTotalPrice' => 59,
                    'orderTotalQty' => 1,
                    'orderTotalTax' => 9.83,
                    'orderTotalTaxIncluded' => 0,
                ],
            ],

            // Example 8) Purchasable taxable VAT 20% tax
            'tax-20pct-vat-not-included-taxable-purchasable-price' => [
                [ // Address
                    'countryCode' => 'UK',
                ],
                [ // Line Items
                    ['salePrice' => 49.17, 'qty' => 1], // 49.17 total price
                ],
                [ // Tax Rates
                    [
                        'name' => 'UK',
                        'code' => 'VAT',
                        'taxCategoryId' => 1,
                        'rate' => 0.2,
                        'include' => false,
                        'isVat' => true,
                        'taxable' => 'purchasable',
                    ],
                ],
                [
                    'adjustments' => [
                        [
                            'type' => 'tax',
                            'amount' => 9.83,
                            'included' => false,
                            'description' => '20%',
                        ],
                    ],
                    'orderTotalPrice' => 59,
                    'orderTotalQty' => 1,
                    'orderTotalTax' => 9.83,
                    'orderTotalTaxIncluded' => 0,
                ],
            ],

            // Example 9) Line Item taxable VAT 20% tax with qty 4
            'tax-20pct-vat-not-included-taxable-line-item-price-qty-4' => [
                [ // Address
                    'countryCode' => 'UK',
                ],
                [ // Line Items
                    ['salePrice' => 49.17, 'qty' => 4], // 49.17 total price
                ],
                [ // Tax Rates
                    [
                        'name' => 'UK',
                        'code' => 'VAT',
                        'taxCategoryId' => 1,
                        'rate' => 0.2,
                        'include' => false,
                        'isVat' => true,
                        'taxable' => 'price',
                    ],
                ],
                [
                    'adjustments' => [
                        [
                            'type' => 'tax',
                            'amount' => 39.34,
                            'included' => false,
                            'description' => '20%',
                        ],
                    ],
                    'orderTotalPrice' => 236.02,
                    'orderTotalQty' => 4,
                    'orderTotalTax' => 39.34,
                    'orderTotalTaxIncluded' => 0,
                ],
            ],

            // Example 10) Purchasable taxable VAT 20% tax with qty 4
            'tax-20pct-vat-not-included-taxable-purchasable-price-qty-4' => [
                [ // Address
                    'countryCode' => 'UK',
                ],
                [ // Line Items
                    ['salePrice' => 49.17, 'qty' => 4], // 49.17 total price
                ],
                [ // Tax Rates
                    [
                        'name' => 'UK',
                        'code' => 'VAT',
                        'taxCategoryId' => 1,
                        'rate' => 0.2,
                        'include' => false,
                        'isVat' => true,
                        'taxable' => 'purchasable',
                    ],
                ],
                [
                    'adjustments' => [
                        [
                            'type' => 'tax',
                            'amount' => 39.32,
                            'included' => false,
                            'description' => '20%',
                        ],
                    ],
                    'orderTotalPrice' => 236,
                    'orderTotalQty' => 4,
                    'orderTotalTax' => 39.32,
                    'orderTotalTaxIncluded' => 0,
                ],
            ],
        ];
    }
}
