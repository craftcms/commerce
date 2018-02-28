<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\migrations;

use craft\base\ElementInterface;
use craft\commerce\base\PurchasableInterface;
use craft\commerce\Plugin;
use craft\db\Migration;
use craft\elements\db\ElementQuery;

/**
 * m180215_13000_rebuild_purchasable_table migration.
 */
class m180215_130000_rebuild_purchasable_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {

        // delete all purchasables
        $this->delete('{{%commerce_purchasables}}');

        // grab the purchasable element types
        $elementTypes = Plugin::getInstance()->getPurchasables()->getAllPurchasableElementTypes();

        foreach ($elementTypes as $elementType) {
            /** @var ElementQuery $query */
            /** @var ElementInterface $elementType */
            $query = $elementType::find()->limit(null);
            foreach ($query->batch() as $rows) {
                $data = [];
                foreach ($rows as $row) {
                    $newRow = [];
                    /** @var PurchasableInterface $row */
                    $newRow[] = $row->getPurchasableId();
                    $newRow[] = $row->getPrice();
                    $newRow[] = $row->getSku();

                    $data[] = $newRow;
                }
                $columns = ['id', 'price', 'sku'];
                // insert 100 at a time.
                $this->batchInsert('{{%commerce_purchasables}}', $columns, $data);
            }
        }
        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m180215_13000_rebuild_purchasable_table cannot be reverted.\n";
        return false;
    }
}
