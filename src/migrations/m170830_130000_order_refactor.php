<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\migrations;

use Craft;
use craft\db\Migration;
use craft\db\Query;
use craft\helpers\Json;

/**
 * m170830_130000_order_refactor
 */
class m170830_130000_order_refactor extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        // Add an reference to the the thing being adjusted.
        $this->addColumn('{{%commerce_orderadjustments}}', 'lineItemId', $this->integer());
        $this->renameColumn('{{%commerce_orderadjustments}}', 'optionsJson', 'sourceSnapshot');

        // Grab all orders.
        $allOrders = (new Query())
            ->select('*')
            ->from(['{{%commerce_orders}}']);

        foreach ($allOrders->batch() as $orders) {
            foreach ($orders as $order) {
                // new line items
                $newAdjustments = [];

                // old adjustments we will delete before we create new adjustments
                $adjustmentIdsToDelete = [];

                // Initialise core adjustment by Type
                $adjustmentsToDeleteByType = [];
                $adjustmentsToDeleteByType['Shipping'] = [];
                $adjustmentsToDeleteByType['Tax'] = [];
                $adjustmentsToDeleteByType['TaxIncluded'] = [];
                $adjustmentsToDeleteByType['Discount'] = [];

                // Track any custom adjuster types used by plugins
                $customAdjusterTypes = [];

                // Get current line items and adjustments for the current order
                $lineItems = (new Query())
                    ->select('*')
                    ->from(['{{%commerce_lineitems}}'])
                    ->where(['orderId' => $order['id']])
                    ->all();

                $adjustments = (new Query())
                    ->select('*')
                    ->from(['{{%commerce_orderadjustments}}'])
                    ->where(['orderId' => $order['id']])
                    ->all();

                // Loop over adjustments and save the adjustments into type groupings so we can save the data into the new adjustments sourceSnapshot so that history is kept.
                foreach ($adjustments as $adjustment) {
                    // convert text json to php array
                    $sourceSnapshot = Json::decodeIfJson($adjustment['sourceSnapshot']);
                    $adjustment['sourceSnapshot'] = $sourceSnapshot;

                    $adjustmentIdsToDelete[] = $adjustment['id'];
                    $type = $adjustment['type'];

                    // Add any custom adjusters used to the list
                    if (!isset($adjustmentsToDeleteByType[$type])) {
                        $adjustmentsToDeleteByType[$type] = [];
                        $customAdjusterTypes[] = $type;
                    }

                    $adjustmentsToDeleteByType[$type][] = $adjustment;
                }

                // Log custom adjuster types that could not be archived into sourceSnapshot. (amounts will still be transitioned to adjustments).
                if ($customAdjusterTypes) {
                    Craft::info('Commerce could not migrate history about custom adjuster types: ' . Json::encode($customAdjusterTypes) . ' Affected amounts are still migrated.');
                }

                // remove all the old adjustments, good riddance
                $this->delete('{{%commerce_orderadjustments}}', ['id' => $adjustmentIdsToDelete]);

                // Loop over the line items and create new adjustments for each major adjuster type per line item.
                foreach ($lineItems as $lineItem) {

                    // line item shipping costs
                    if ($lineItem['shippingCost'] != 0) {
                        $newAdjustments[] = [
                            'shipping',
                            'Shipping Costs',
                            $order['id'],
                            $lineItem['id'],
                            'Shipping costs',
                            Json::encode(['OldAdjustments' => $adjustmentsToDeleteByType['Shipping']]),
                            $lineItem['shippingCost'],
                            false
                        ];
                    }

                    // line item tax amount
                    if ($lineItem['tax'] != 0) {
                        $newAdjustments[] = [
                            'tax',
                            'Tax',
                            $order['id'],
                            $lineItem['id'],
                            'Tax',
                            Json::encode(['OldAdjustments' => $adjustmentsToDeleteByType['Tax']]),
                            $lineItem['tax'],
                            false
                        ];
                    }

                    // line item included tax amount
                    if ($lineItem['taxIncluded'] != 0) {
                        $newAdjustments[] = [
                            'taxIncluded',
                            'Tax Included',
                            $order['id'],
                            $lineItem['id'],
                            'Tax Included',
                            Json::encode(['OldAdjustments' => $adjustmentsToDeleteByType['Tax']]),
                            $lineItem['taxIncluded'],
                            true
                        ];
                    }

                    // line item discount amount
                    if ($lineItem['discount'] != 0) {
                        $newAdjustments[] = [
                            'Discount',
                            'Discount',
                            $order['id'],
                            $lineItem['id'],
                            'Discount',
                            Json::encode(['OldAdjustments' => $adjustmentsToDeleteByType['Discount']]),
                            $lineItem['discount'],
                            false
                        ];
                    }
                }

                // Create adjustments for the old base* items. Good riddance again.

                // Order level shipping costs
                if ($order['baseShippingCost'] != 0) {
                    $newAdjustments[] = [
                        'Shipping',
                        'Order shipping Costs',
                        $order['id'],
                        null,
                        'Order shipping costs',
                        Json::encode(['OldAdjustments' => $adjustmentsToDeleteByType['Shipping']]),
                        $order['baseShippingCost'],
                        false
                    ];
                }

                // Order level tax
                if ($order['baseTax'] != 0) {
                    $newAdjustments[] = [
                        'Tax',
                        'Order tax',
                        $order['id'],
                        null,
                        'Order tax',
                        Json::encode(['OldAdjustments' => $adjustmentsToDeleteByType['Tax']]),
                        $order['baseTax'],
                        false
                    ];
                }

                // Order level tax included
                if ($order['baseTaxIncluded'] != 0) {
                    $newAdjustments[] = [
                        'TaxIncluded',
                        'Order tax included',
                        $order['id'],
                        null,
                        'Order tax included',
                        Json::encode(['OldAdjustments' => $adjustmentsToDeleteByType['Tax']]),
                        $order['baseTaxIncluded'],
                        true
                    ];
                }

                // Order level tax included
                if ($order['baseDiscount'] != 0) {
                    $newAdjustments[] = [
                        'Discount',
                        'Order discount',
                        $order['id'],
                        null,
                        'Discount',
                        Json::encode(['OldAdjustments' => $adjustmentsToDeleteByType['Discount']]),
                        $order['baseDiscount'],
                        false
                    ];
                }

                // Insert all the new broken down adjustments
                if ($newAdjustments) {
                    $this->batchInsert('{{%commerce_orderadjustments}}', [
                        'type',
                        'name',
                        'orderId',
                        'lineItemId',
                        'description',
                        'sourceSnapshot',
                        'amount',
                        'included'
                    ], $newAdjustments);
                }
            }
        }

        // Goodbye
        $this->dropColumn('{{%commerce_lineitems}}', 'shippingCost');
        $this->dropColumn('{{%commerce_lineitems}}', 'tax');
        $this->dropColumn('{{%commerce_lineitems}}', 'taxIncluded');
        $this->dropColumn('{{%commerce_lineitems}}', 'discount');

        // Goodbye too
        $this->dropColumn('{{%commerce_orders}}', 'baseShippingCost');
        $this->dropColumn('{{%commerce_orders}}', 'baseTax');
        $this->dropColumn('{{%commerce_orders}}', 'baseTaxIncluded');
        $this->dropColumn('{{%commerce_orders}}', 'baseDiscount');

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m170830_130000_order_refactor cannot be reverted.\n";

        return false;
    }
}
