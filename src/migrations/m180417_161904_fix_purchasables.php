<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\migrations;

use Craft;
use craft\commerce\base\PurchasableInterface;
use craft\commerce\elements\Product;
use craft\commerce\elements\Variant;
use craft\db\Migration;
use craft\db\Query;
use craft\helpers\MigrationHelper;
use ReflectionClass;

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
        $variantIds = (new Query())->select('id')->from('{{%commerce_variants}}')->limit(null)->column();
        $elementIds = (new Query())->select('id')->from('{{%elements}}')->where([
            'and',
            ['type' => Variant::class],
            ['not in', 'id', $variantIds]
        ])->limit(null)->column();

        foreach ($elementIds as $id) {
            $this->delete('{{%elements}}', ['id' => $id]);
        }

        // Delete any product element records for product that do not exist
        $productIds = (new Query())->select('id')->from('{{%commerce_products}}')->limit(null)->column();
        $elementIds = (new Query())->select('id')->from('{{%elements}}')->where([
            'and',
            ['type' => Product::class],
            ['not in', 'id', $productIds]
        ])->limit(null)->column();

        foreach ($elementIds as $id) {
            $this->delete('{{%elements}}', ['id' => $id]);
        }

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
        $purchasableIds = (new Query())->select('id')->from('{{%commerce_purchasables}}')->limit(null)->column();
        foreach ($purchasableIds as $id) {
            $this->delete('{{%commerce_purchasables}}', ['id' => $id]);
        }

        // Need to recreate all purchasable rows, so need to get all elements unfortunately
        $elementsRows = (new Query())->select('id, type')->from('{{%elements}}')->limit(null)->all();

        // Cache the reflection classes we need.
        $reflectionClassesByType = [];
        foreach ($elementsRows as $elementsRow) {
            $type = $elementsRow['type'];

            if (!isset($reflectionClassesByType[$type])) {
                try {
                    $reflectionClassesByType[$type] = new ReflectionClass($type);
                } catch (\ReflectionException $e) {
                    Craft::warning('Class: ' . $type . ' does not exist. Can not re-create purchasable records for elements of that type.');
                }
            }
        }

        // Create the purchasable records.
        foreach ($elementsRows as $elementsRow) {
            if (isset($reflectionClassesByType[$elementsRow['type']])) {
                $class = $reflectionClassesByType[$elementsRow['type']];
                if ($class && $class->implementsInterface(PurchasableInterface::class)) {
                    /** @var PurchasableInterface $element */
                    if ($element = Craft::$app->getElements()->getElementById($elementsRow['id'])) {
                        $row = [];
                        $row['id'] = $element->getId();
                        $row['price'] = $element->getPrice();
                        $row['sku'] = $element->getSku();
                        $this->insert('{{%commerce_purchasables}}', $row);
                    }
                }
            }
        }

        return true;
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
