<?php

use CraftCms\Cms\Database\Migration;
use CraftCms\Cms\Database\Table;
use Illuminate\Support\Facades\DB;

/**
 * Between Commerce 5.2 and 5.5.2, the NestedElementManager for variants used 'allVariants'
 * as the attribute name instead of 'variants'. This caused 'allVariants' to be written into
 * the {{%changedattributes}} table. After upgrading, Craft's mergeCanonicalChanges() would
 * try to access $product->allVariants (which no longer exists), throwing an UnknownPropertyException
 * when opening a product with a provisional draft.
 * This migration renames any lingering 'allVariants' entries to 'variants' for Product elements.
 */
return new class extends Migration {
    public function up(): void
    {
        // Insert 'variants' rows for Products that have 'allVariants' but no existing 'variants' entry
        DB::table(Table::CHANGEDATTRIBUTES)->insertUsing(
            ['elementId', 'siteId', 'attribute', 'dateUpdated', 'propagated', 'userId'],
            function ($query) {
                $query->from(Table::CHANGEDATTRIBUTES . ' as ca')
                    ->select(['ca.elementId', 'ca.siteId', DB::raw("'variants'"), 'ca.dateUpdated', 'ca.propagated', 'ca.userId'])
                    ->where('ca.attribute', 'allVariants')
                    ->whereIn('ca.elementId', $this->productIdsQuery())
                    ->whereNotExists(function ($subQuery) {
                        $subQuery->select(DB::raw(1))
                            ->from(Table::CHANGEDATTRIBUTES . ' as ca2')
                            ->whereColumn('ca2.elementId', 'ca.elementId')
                            ->whereColumn('ca2.siteId', 'ca.siteId')
                            ->where('ca2.attribute', 'variants');
                    });
            }
        );

        // Delete all 'allVariants' rows for Products
        DB::table(Table::CHANGEDATTRIBUTES)
            ->where('attribute', 'allVariants')
            ->whereIn('elementId', $this->productIdsQuery())
            ->delete();
    }

    public function down(): void
    {
        $this->output->error('2026_06_16_000000_rename_allVariants_changedattributes cannot be reverted.');
    }

    private function productIdsQuery(): \Illuminate\Database\Query\Builder
    {
        return DB::table(Table::ELEMENTS)
            ->where('type', 'craft\commerce\elements\Product')
            ->select('id');
    }
};
