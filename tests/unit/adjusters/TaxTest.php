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
     * @var Order
     */
    private $_order;

    /**
     *
     */
    protected function _before()
    {
        parent::_before();

        $this->pluginInstance = Plugin::getInstance();
        $this->originalEdition = $this->pluginInstance->edition;
        $this->pluginInstance->edition = Plugin::EDITION_PRO;
        $this->_order = $this->_createOrder();
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
     *
     */
    public function testAdjustIncluded()
    {
        $taxAdjuster = $this->make(Tax::class, [
            'getTaxRates' => function() {
                return $this->_getIncludedTaxRate();
            }
        ]);

        $adjustments = $taxAdjuster->adjust($this->_order);

        self::assertEquals(15, $this->_order->getTotalQty());
        self::assertEquals(200, $this->_order->getTotalPrice());
        self::assertCount(1, $adjustments);
        self::assertEquals('10% inc', $adjustments[0]->description);
        self::assertEquals(18.18, round($adjustments[0]->amount, 2));
        self::assertTrue($adjustments[0]->included);
    }

    /**
     *
     */
    public function testAdjustNonIncluded()
    {
        $taxAdjuster = $this->make(Tax::class, [
            'getTaxRates' => function() {
                return $this->_getTaxrates();
            }
        ]);

        $adjustments = $taxAdjuster->adjust($this->_order);

        self::assertEquals(15, $this->_order->getTotalQty());
        self::assertEquals(200, $this->_order->getTotalPrice());

        self::assertCount(1, $adjustments);

        self::assertEquals('10%', $adjustments[0]->description);
        self::assertEquals(20, round($adjustments[0]->amount, 2));
        self::assertFalse($adjustments[0]->included);
    }

    /**
     *
     */
    public function testAdjustIncludedAndNonIncluded()
    {
        $taxAdjuster = $this->make(Tax::class, [
            'getTaxRates' => function() {
                return $this->_getIncludedAndNonIncludedTaxRate();
            },
            '_address' => function() {
                return $this->_getAddress();
            }
        ]);

        $adjustments = $taxAdjuster->adjust($this->_order);
        $this->_order->setAdjustments($adjustments);

        self::assertEquals(15, $this->_order->getTotalQty());
        self::assertEquals(220, $this->_order->getTotalPrice());

        self::assertCount(2, $this->_order->getAdjustments());

        self::assertEquals('10% inc', $adjustments[0]->description);
        self::assertEquals(18.18, round($adjustments[0]->amount, 2));
        self::assertTrue($adjustments[0]->included);

        self::assertEquals('10%', $adjustments[1]->description);
        self::assertEquals(20, $adjustments[1]->amount);
        self::assertTrue($adjustments[0]->included);
    }

    /**
     * @return array
     */
    private function _getIncludedAndNonIncludedTaxRate(): array
    {
        $rates = [];
        $rates = array_merge($rates, $this->_getIncludedTaxRate());
        $rates = array_merge($rates, $this->_getTaxRates());

        return $rates;
    }

    private function _getTaxRates(): array
    {
        $rates = [];
        $austriaRate = new TaxRate();
        $austriaRate->name = "Austria";
        $austriaRate->code = "AUVAT";
        $austriaRate->rate = 0.1; // 10%
        $austriaRate->include = false; // 10%
        $austriaRate->taxable = 'order_total_price'; // 10%

        $rates[] = $austriaRate;

        return $rates;
    }

    private function _getIncludedTaxRate(): array
    {
        $rates = [];
        $netherlandsRate = new TaxRate();
        $netherlandsRate->name = "Netherlands";
        $netherlandsRate->code = "NVAT";
        $netherlandsRate->rate = 0.1; // 10%
        $netherlandsRate->include = true; // 10%
        $netherlandsRate->isVat = true; // 10%
        $netherlandsRate->taxable = 'order_total_price'; // 10%

        $rates[] = $netherlandsRate;

        return $rates;
    }

    private function _getAddress($withValidVatId = false)
    {
        $address = new Address();
        $address->businessTaxId = $withValidVatId ? 'CZ25666011' : 'xxx';
        $address->countryId = 1;

        return $address;
    }

    /**
     * @return Order
     */
    private function _createOrder(): Order
    {
        $order = new Order();

        $lineItem1 = new LineItem();
        $lineItem1->qty = 10;
        $lineItem1->salePrice = 10;

        $lineItem2 = new LineItem();
        $lineItem2->qty = 5;
        $lineItem2->salePrice = 20;

        $order->setLineItems([$lineItem1, $lineItem2]);

        return $order;
    }
}