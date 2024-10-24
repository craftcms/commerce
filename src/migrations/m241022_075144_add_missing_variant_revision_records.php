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
use craft\helpers\StringHelper;
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
                'e.id',
                'e.canonicalId',
                'e.revisionId',
                'es.siteId',
            ])
            ->from('{{%elements}}' . ' e')
            ->innerJoin('{{%elements_sites}}' . ' es', '[[e.id]] = [[es.elementId]]')
            ->where(['type' => Variant::class])
            ->andWhere(['not', ['revisionId' => null]])
            ->collect();

        $sitesStores = (new Query())
            ->select(['siteId', 'storeId'])
            ->from('{{%commerce_site_stores}}')
            ->collect();

        /** @var Collection $variantsWithRevisions */
        $canonicalVariantIds = $variantsWithRevisions->pluck('canonicalId')->unique()->all();
        $revisionVariantElementIds = $variantsWithRevisions->pluck('id')->unique()->all();
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

            // As we are looping over variants across sites we need to ensure we only insert a purchasable once
            if (!($purchasableInserts[$v['id']] ?? null)) {
                $purchasableInserts[$v['id']] = [
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
                    'uid' => StringHelper::UUID(),
                ];
            }

            $storeId = $sitesStores->where('siteId', $v['siteId'])->pluck('storeId')->first();

            $canonicalPurchasableStoreRecord = $canonicalVariantPurchasableStoreRecords[$v['canonicalId'] . '-' . $storeId] ?? null;
            $nonCanonicalPurchaseStoreRecord = $nonCanonicalPurchasableStoreRecords[$v['id'] . '-' . $storeId] ?? null;

            // Skip if we can't find the canonical record or if a record exists for this variant ID and Store ID
            if (!$canonicalPurchasableStoreRecord || $nonCanonicalPurchaseStoreRecord) {
                continue;
            }

            if (!($purchasableStoresInserts[$v['id'] . '-' . $storeId] ?? null)) {
                $purchasableStoresInserts[$v['id'] . '-' . $storeId] = [
                    'purchasableId' => $v['id'],
                    'storeId' => $storeId,
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
                    'uid' => StringHelper::UUID(),
                    'dateCreated' => $date,
                    'dateUpdated' => $date,
                ];
            }
        }

        if (!empty($purchasableInserts)) {
            foreach (array_chunk($purchasableInserts, 1000) as $purchasableInsertsChunk) {
                Craft::$app->getDb()->createCommand()
                    ->batchInsert('{{%commerce_purchasables}}', array_keys($purchasableInsertsChunk[0]), $purchasableInsertsChunk)
                    ->execute();
            }
        }

        if (!empty($purchasableStoresInserts)) {
            foreach (array_chunk($purchasableStoresInserts, 1000) as $purchasableStoresInsertsChunk) {
                Craft::$app->getDb()->createCommand()
                    ->batchInsert('{{%commerce_purchasables_stores}}', array_keys($purchasableStoresInsertsChunk[0]), $purchasableStoresInsertsChunk)
                    ->execute();
            }
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
