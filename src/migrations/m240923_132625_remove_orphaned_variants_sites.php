<?php

namespace craft\commerce\migrations;

use craft\db\Migration;
use craft\db\Query;

/**
 * m240923_132625_remove_orphaned_variants_sites migration.
 */
class m240923_132625_remove_orphaned_variants_sites extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        // Find all existing combinations of product and site IDs
        $allProductsSites = (new Query())
            ->select(['elementId', 'siteId'])
            ->from('{{%elements_sites}}' . ' es')
            ->innerJoin('{{%commerce_products}}' . ' p', '[[es.elementId]] = [[p.id]]')
            ->collect();

        // Group them by product ID
        $siteIdsByProductId = $allProductsSites->groupBy('elementId')->map(function($row) {
            return collect($row)->pluck('siteId')->toArray();
        }
        );

        // Find all existing combinations of variant and site IDs
        $allVariantsSites = (new Query())
            ->select(['es.id', 'elementId', 'siteId', 'primaryOwnerId'])
            ->from('{{%elements_sites}}' . ' es')
            ->innerJoin('{{%commerce_variants}}' . ' v', '[[es.elementId]] = [[v.id]]')
            ->collect();

        // Find all variants that are not associated with any of their product's sites
        $orphanedVariantsSites = array_values($allVariantsSites->filter(function($row) use ($siteIdsByProductId) {
            return !in_array($row['siteId'], $siteIdsByProductId[$row['primaryOwnerId']]);
        })->map(fn($row) => $row['id'])->toArray());

        if (empty($orphanedVariantsSites)) {
            return true;
        }

        // Bulk delete the orphaned variants' site rows (if any) 1000 at a time
        foreach (array_chunk($orphanedVariantsSites, 1000) as $chunk) {
            $this->delete('{{%elements_sites}}', ['id' => $chunk]);
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m240923_132625_remove_orphaned_variants_sites cannot be reverted.\n";
        return false;
    }
}
