<?php

namespace craft\commerce\migrations;

use craft\commerce\adjusters\Discount;
use craft\commerce\adjusters\Shipping;
use craft\commerce\adjusters\Tax;
use craft\commerce\db\Table;
use craft\db\Migration;
use craft\db\Query as CraftQuery;

/**
 * m200112_220749_cache_totalDiscount_totalTax_totalShipping migration.
 */
class m200112_220749_cache_totalDiscount_totalTax_totalShipping extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        if (!$this->db->columnExists('{{%commerce_orders}}', 'totalDiscount')) {
            $this->addColumn('{{%commerce_orders}}', 'totalDiscount', $this->decimal(14, 4)->defaultValue(0));
        }

        if (!$this->db->columnExists('{{%commerce_orders}}', 'totalTax')) {
            $this->addColumn('{{%commerce_orders}}', 'totalTax', $this->decimal(14, 4)->defaultValue(0));
        }

        if (!$this->db->columnExists('{{%commerce_orders}}', 'totalTaxIncluded')) {
            $this->addColumn('{{%commerce_orders}}', 'totalTaxIncluded', $this->decimal(14, 4)->defaultValue(0));
        }

        if (!$this->db->columnExists('{{%commerce_orders}}', 'totalShippingCost')) {
            $this->addColumn('{{%commerce_orders}}', 'totalShippingCost', $this->decimal(14, 4)->defaultValue(0));
        }

        $ordersQuery = (new CraftQuery())->select(['id'])->from('{{%commerce_orders}}')->batch(100, $this->getDb());

        $sumTax = "
SUM(CASE
WHEN [[type]] = 'tax' AND [[included]] = false
    THEN amount 
    ELSE 0 
END) as [[taxAmount]]";
        $sumTaxIncluded = "
SUM(CASE
WHEN [[type]] = 'tax' AND [[included]] = true
    THEN amount 
    ELSE 0 
END) as [[taxIncludedAmount]]";
        $sumShipping = "
SUM(CASE
WHEN [[type]] = 'shipping'
    THEN amount 
    ELSE 0 
END) as [[shippingCostAmount]]";

        $sumDiscount = "
SUM(CASE
WHEN [[type]] = 'discount'
    THEN amount 
    ELSE 0 
END) as [[discountAmount]]";

        $amounts = (new CraftQuery())
            ->select([$sumTax, $sumTaxIncluded, $sumShipping, $sumDiscount, '[[orderId]]'])
            ->from('{{%commerce_orderadjustments}}')
            ->indexBy('orderId')
            ->groupBy('[[orderId]]')
            ->all();

        foreach ($ordersQuery as $orders) {
            foreach ($orders as $order) {
                $orderId = $order['id'];
                $data = [
                    'totalTax' => $amounts[$orderId]['taxAmount'] ?? 0,
                    'totalTaxIncluded' => $amounts[$orderId]['taxIncludedAmount'] ?? 0,
                    'totalShippingCost' => $amounts[$orderId]['shippingCostAmount'] ?? 0,
                    'totalDiscount' => $amounts[$orderId]['discountAmount'] ?? 0,
                ];
                $this->update('{{%commerce_orders}}', $data, ['id' => $order['id']]);
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m200112_220749_cache_totalDiscount_totalTax_totalShipping cannot be reverted.\n";
        return false;
    }
}
