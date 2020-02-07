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
        $this->addColumn('{{%commerce_lineitems}}', 'sku', $this->string());
        $this->addColumn('{{%commerce_lineitems}}', 'description', $this->string());

        $query = (new Query())
            ->select(['l.id', 'l.snapshot', 'p.sku', 'p.description'])
            ->from('{{%commerce_lineitems}} l')
            ->leftJoin('{{%commerce_purchasables}} p', 'l.purchasableId = p.id');

        foreach ($query->batch(300) as $results) {
            foreach ($results as $row) {
                $options = Json::decodeIfJson($row['snapshot'], true);
                $sku = $options['sku'] ?? $row['sku'] ?? '';
                $description = $options['description'] ?? $row['description'] ?? '';
                $this->update('{{%commerce_lineitems}}', compact('sku', 'description'), ['id' => $row['id']]);
            }
        }
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
