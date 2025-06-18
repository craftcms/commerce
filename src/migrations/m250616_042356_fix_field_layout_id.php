<?php

namespace craft\commerce\migrations;

use Craft;
use craft\commerce\db\Table;
use craft\commerce\elements\Variant;
use craft\db\Migration;
use craft\db\Query;
use craft\db\Table as CraftTable;

/**
 * m250616_042356_fix_field_layout_id migration.
 */
class m250616_042356_fix_field_layout_id extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $variantsToFix = (new Query())
            ->select([
                'v.id as variantId',
                'v.primaryOwnerId as productId',
                'p.typeId as productTypeId',
            ])
            ->from(['v' => Table::VARIANTS])
            ->innerJoin(['e' => CraftTable::ELEMENTS], '[[v.id]] = [[e.id]]')
            ->innerJoin(['p' => Table::PRODUCTS], '[[v.primaryOwnerId]] = [[p.id]]')
            ->where(['e.type' => Variant::class])
            ->andWhere(['e.fieldLayoutId' => null])
            ->all();

        if (empty($variantsToFix)) {
            return true;
        }

        // Group variants by product type
        $variantsByProductType = [];
        foreach ($variantsToFix as $variant) {
            $variantsByProductType[$variant['productTypeId']][] = $variant['variantId'];
        }

        // Get valid field layout IDs for each product type
        $productTypes = (new Query())
            ->select(['pt.id', 'pt.variantFieldLayoutId'])
            ->from(['pt' => Table::PRODUCTTYPES])
            ->innerJoin(['fl' => CraftTable::FIELDLAYOUTS], '[[pt.variantFieldLayoutId]] = [[fl.id]]') // Ensure field layout exists
            ->where(['pt.id' => array_keys($variantsByProductType)])
            ->andWhere(['not', ['pt.variantFieldLayoutId' => null]])
            ->indexBy('id')
            ->all();

        $db = Craft::$app->getDb();
        $transaction = $db->beginTransaction();

        try {
            // Update variants with the correct field layout ID
            foreach ($variantsByProductType as $productTypeId => $variantIds) {
                // Check if we have a valid field layout for this product type
                if (!isset($productTypes[$productTypeId])) {
                    continue;
                }

                $fieldLayoutId = $productTypes[$productTypeId]['variantFieldLayoutId'];

                // Update in batches
                foreach (array_chunk($variantIds, 500) as $chunk) {
                    $db->createCommand()
                        ->update(
                            CraftTable::ELEMENTS,
                            ['fieldLayoutId' => $fieldLayoutId],
                            ['id' => $chunk]
                        )
                        ->execute();
                }
            }

            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m250616_042356_fix_field_layout_id cannot be reverted.\n";
        return false;
    }
}
