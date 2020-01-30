<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\migrations;

use Craft;
use craft\base\Element;
use craft\base\ElementInterface;
use craft\commerce\base\PurchasableInterface;
use craft\commerce\elements\Product;
use craft\commerce\elements\Variant;
use craft\db\Migration;
use craft\db\Query;
use craft\db\Table;
use craft\helpers\MigrationHelper;
use ReflectionClass;
use ReflectionException;

/**
 * m180417_161904_fix_purchasables migration.
 */
class m180417_161904_fix_purchasables extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        // Delete any variant element records for variants that do not exist due to incorrect deletion
        $variantIds = (new Query())
            ->select(['id'])
            ->from(['{{%commerce_variants}}'])
            ->column();

        $elementIds = (new Query())
            ->select(['id'])
            ->from(['{{%elements}}'])
            ->where([
                'and',
                ['type' => Variant::class],
                ['not', ['id' => $variantIds]]
            ])
            ->column();


        $this->delete('{{%elements}}', ['id' => $elementIds]);

        // Delete any product element records for product that do not exist
        $productIds = (new Query())
            ->select(['id'])
            ->from(['{{%commerce_products}}'])
            ->column();

        $elementIds = (new Query())
            ->select(['id'])
            ->from(['{{%elements}}'])
            ->where([
                'and',
                ['type' => Product::class],
                ['not', ['id' => $productIds]]
            ])
            ->column();

        $this->delete('{{%elements}}', ['id' => $elementIds]);

        MigrationHelper::dropAllForeignKeysOnTable('{{%commerce_variants}}');

        if ($this->db->getIsPgsql()) {
            // Manually construct the SQL for Postgres
            // (see https://github.com/yiisoft/yii2/issues/12077)
            $this->execute('alter table {{%commerce_variants}} alter column [[productId]] DROP NOT NULL');
        } else {
            $this->alterColumn('{{%commerce_variants}}', 'productId', $this->integer());
        }

        $this->addForeignKey(null, '{{%commerce_variants}}', ['id'], '{{%elements}}', ['id'], 'CASCADE');
        $this->addForeignKey(null, '{{%commerce_variants}}', ['productId'], '{{%commerce_products}}', ['id'], 'SET NULL');

        // Delete Everything in Purchasable table
        $purchasableIds = (new Query())
            ->select(['id'])
            ->from(['{{%commerce_purchasables}}'])
            ->column();

        $this->delete('{{%commerce_purchasables}}', ['id' => $purchasableIds]);

        // Need to recreate all purchasable rows, so need to get all elements unfortunately
        $types = (new Query())
            ->select(['type'])
            ->from([Table::ELEMENTS])
            ->distinct()
            ->all();

        foreach ($types as $row) {
            $type = $row['type'];

            if (class_exists($type) && is_subclass_of($type, PurchasableInterface::class)) {
                /** @var string|Element $type */
                foreach ($type::find()->anyStatus()->batch() as $batch) {
                    $newPurchasables = [];
                    foreach ($batch as $purchasable) {
                        $newPurchasables[] = [$purchasable->getId(), $purchasable->getSku(), $purchasable->getPrice()];
                    }

                    $this->batchInsert('{{%commerce_purchasables}}', ['id', 'sku', 'price'], $newPurchasables);
                }
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m180417_161904_fix_purchasables cannot be reverted.\n";
        return false;
    }
}
