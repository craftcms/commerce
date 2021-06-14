<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\adjusters;

use Codeception\Stub;
use Codeception\Test\Unit;
use craft\commerce\adjusters\Tax;
use craft\commerce\elements\Order;
use craft\commerce\models\Address;
use craft\commerce\models\LineItem;
use craft\commerce\models\TaxRate;
use craft\commerce\Plugin;
use craft\fields\Entries;
use yii\base\BaseObject;

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
    public function _setUp()
    {
        parent::_setUp();

        $this->_order = $this->_createOrder();
    }

    /**
     *
     */
    public function testAdjust()
    {

//        $address = $this->_getAddress(false);
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
        self::assertEquals(20, $adjustments[0]->amount);
        self::assertFalse($adjustments[0]->included);
    }

    private function _getTaxRates(): array
    {
        $rates = [];
        $netherlandsRate = new TaxRate();
        $netherlandsRate->name = "Netherlands";
        $netherlandsRate->code = "NVAT";
        $netherlandsRate->rate = 0.1; // 10%
        $netherlandsRate->taxable = 'order_total_price'; // 10%

        $rates[] = $netherlandsRate;

        return $rates;
    }

    private function _getAddress($withValidVatId)
    {
        $address = new Address();
        $address->businessTaxId = $withValidVatId ? 'CZ25666011' : 'xxx';

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