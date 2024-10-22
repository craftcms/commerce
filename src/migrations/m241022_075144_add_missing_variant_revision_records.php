<?php

namespace craft\commerce\migrations;

use Craft;
use craft\commerce\elements\Variant;
use craft\commerce\records\Purchasable;
use craft\commerce\records\PurchasableStore;
use craft\commerce\records\Store;
use craft\db\Migration;
use craft\db\Query;
use craft\helpers\Db;
use Illuminate\Support\Collection;

/**
 * m241022_075144_add_missing_variant_revision_records migration.
 */
class m241022_075144_add_missing_variant_revision_records extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $variantsWithRevisions = (new Query())
            ->select([
                'id',
                'canonicalId',
                'revisionId',
            ])
            ->from('{{%elements}}' . ' e')
            ->where(['type' => Variant::class])
            ->andWhere(['not', ['revisionId' => null]])
            ->collect();

        /** @var Collection $variantsWithRevisions */
        $canonicalVariantIds = $variantsWithRevisions->map(fn (array $v) => $v['canonicalId'])->unique()->all();
        $revisionVariantElementIds = $variantsWithRevisions->map(fn (array $v) => $v['id'])->unique()->all();
        $nonCanonicalPurchasableRecords = [];
        $nonCanonicalPurchasableStoreRecords = [];

        foreach (array_chunk($revisionVariantElementIds, 1000) as $chunk) {
            $nonCanonicalPurchasableRecords += Purchasable::find()->where(['element.id' => $chunk])->indexBy('element.id')->all();
            $nonCanonicalPurchasableStoreRecords += PurchasableStore::find()->where(['purchasableId' => $chunk])->indexBy(fn(PurchasableStore $row) => $row['purchasableId'] . '-' . $row['storeId'])->all();
        }

        $canonicalVariantPurchasableRecords = [];
        $canonicalVariantPurchasableStoreRecords = [];

        foreach (array_chunk($canonicalVariantIds, 1000) as $chunk) {
            $canonicalVariantPurchasableRecords += Purchasable::find()->where(['element.id' => $chunk])->indexBy('element.id')->all();
            $canonicalVariantPurchasableStoreRecords += PurchasableStore::find()->where(['purchasableId' => $chunk])->indexBy(fn(PurchasableStore $row) => $row['purchasableId'] . '-' . $row['storeId'])->all();
        }

        $stores = Store::find()->all();
        $purchasableInserts = [];
        $purchasableStoresInserts = [];
        $date = Db::prepareDateForDb(new \DateTime());

        foreach ($variantsWithRevisions as $v) {
            $canonicalPurchasableRecord = $canonicalVariantPurchasableRecords[$v['canonicalId']] ?? null;
            $nonCanonicalPurchasableRecord = $nonCanonicalPurchasableRecords[$v['id']] ?? null;

            // Skip if we can't find the canonical record or if a record exists for this variant ID
            if (!$canonicalPurchasableRecord || $nonCanonicalPurchasableRecord) {
                continue;
            }

            $purchasableInserts[] = [
                'id' => $v['id'],
                'description' => $canonicalPurchasableRecord['description'],
                'sku' => $canonicalPurchasableRecord['sku'],
                'width' => $canonicalPurchasableRecord['width'],
                'height' => $canonicalPurchasableRecord['height'],
                'length' => $canonicalPurchasableRecord['length'],
                'weight' => $canonicalPurchasableRecord['weight'],
                'dateCreated' => $date,
                'dateUpdated' => $date,
                'taxCategoryId' => $canonicalPurchasableRecord['taxCategoryId'],
            ];

            foreach ($stores as $store) {
                $canonicalPurchasableStoreRecord = $canonicalVariantPurchasableStoreRecords[$v['canonicalId'] . '-' . $store['id']] ?? null;
                $nonCanonicalPurchaseStoreRecord = $nonCanonicalPurchasableStoreRecords[$v['id'] . '-' . $store['id']] ?? null;

                // Skip if we can't find the canonical record or if a record exists for this variant ID and Store ID
                if (!$canonicalPurchasableStoreRecord || $nonCanonicalPurchaseStoreRecord) {
                    continue;
                }

                $purchasableStoresInserts[] = [
                    'purchasableId' => $v['id'],
                    'storeId' => $store['id'],
                    'basePrice' => $canonicalPurchasableStoreRecord['basePrice'],
                    'basePromotionalPrice' => $canonicalPurchasableStoreRecord['basePromotionalPrice'],
                    'promotable' => $canonicalPurchasableStoreRecord['promotable'],
                    'availableForPurchase' => $canonicalPurchasableStoreRecord['availableForPurchase'],
                    'freeShipping' => $canonicalPurchasableStoreRecord['freeShipping'],
                    'stock' => $canonicalPurchasableStoreRecord['stock'],
                    'inventoryTracked' => $canonicalPurchasableStoreRecord['inventoryTracked'],
                    'minQty' => $canonicalPurchasableStoreRecord['minQty'],
                    'maxQty' => $canonicalPurchasableStoreRecord['maxQty'],
                    'shippingCategoryId' => $canonicalPurchasableStoreRecord['shippingCategoryId'],
                    'dateCreated' => $date,
                    'dateUpdated' => $date,
                ];
            }
        }

        if (!empty($purchasableInserts)) {
            Craft::$app->getDb()->createCommand()
                ->batchInsert('{{%commerce_purchasables}}', array_keys($purchasableInserts[0]), $purchasableInserts)
                ->execute();
        }

        if (!empty($purchasableStoresInserts)) {
            Craft::$app->getDb()->createCommand()
                ->batchInsert('{{%commerce_purchasables_stores}}', array_keys($purchasableStoresInserts[0]), $purchasableStoresInserts)
                ->execute();
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m241022_075144_add_missing_variant_revision_records cannot be reverted.\n";
        return false;
    }
}
