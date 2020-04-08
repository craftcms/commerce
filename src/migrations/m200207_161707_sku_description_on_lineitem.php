<?php

namespace craft\commerce\migrations;

use craft\db\Migration;
use craft\db\Query;
use craft\helpers\Json;

/**
 * m200207_161707_sku_description_on_lineitem migration.
 */
class m200207_161707_sku_description_on_lineitem extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        if (!$this->db->columnExists('{{%commerce_lineitems}}', 'sku')) {
            $this->addColumn('{{%commerce_lineitems}}', 'sku', $this->string());
        }
        if (!$this->db->columnExists('{{%commerce_lineitems}}', 'description')) {
            $this->addColumn('{{%commerce_lineitems}}', 'description', $this->string());
        }

        $query = (new Query())
            ->select(['[[l.id]]', '[[l.snapshot]]', '[[p.sku]]', '[[p.description]]', '[[l.purchasableId]]'])
            ->from('{{%commerce_lineitems}} l')
            ->innerJoin('{{%commerce_purchasables}} p', '[[l.purchasableId]] = [[p.id]]')
            ->innerJoin('{{%commerce_orders}} o', '[[l.orderId]] = [[o.id]]')
            ->where(['[[o.isCompleted]]' => true]);

        $count = (new Query())
            ->select(['[[l.id]]', '[[l.snapshot]]', '[[p.sku]]', '[[p.description]]', '[[l.purchasableId]]'])
            ->from('{{%commerce_lineitems}} l')
            ->innerJoin('{{%commerce_purchasables}} p', '[[l.purchasableId]] = [[p.id]]')
            ->innerJoin('{{%commerce_orders}} o', '[[l.orderId]] = [[o.id]]')
            ->where(['[[o.isCompleted]]' => true])
            ->count();

        echo '    > There are ' . $count . ' line items to be updated with description and sku (moved from snapshot to columns).';

        $runningTotal = 0;
        foreach ($query->batch(300) as $results) {
            $purchasableSkuAndDescriptionByPurchasableId = [];
            foreach ($results as $row) {
                $snapshot = Json::decodeIfJson($row['snapshot'], true);
                $purchasableId = $row['purchasableId'];
                $sku = $snapshot['sku'] ?? $row['sku'] ?? '';
                $description = $snapshot['description'] ?? $row['description'] ?? '';
                if (!isset($purchasableSkuAndDescriptionByPurchasableId[$purchasableId])) {
                    $purchasableSkuAndDescriptionByPurchasableId[$purchasableId] = compact('description', 'sku', 'purchasableId');
                }
            }
            foreach ($purchasableSkuAndDescriptionByPurchasableId as $data) {
                $this->update('{{%commerce_lineitems}}', $data, ['purchasableId' => $data['purchasableId']]);
            }
            $runningTotal += count($purchasableSkuAndDescriptionByPurchasableId);
            echo '    > Updated ' . $runningTotal . ' / ' . $count . ' purchasbles on line items with description and sku.';
            unset($purchasableSkuAndDescriptionByPurchasableId);
        }

        echo '    > Updated All line items with description and sku.';
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m200207_161707_sku_description_on_lineitem cannot be reverted.\n";
        return false;
    }
}
