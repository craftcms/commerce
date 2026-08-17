<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace unit\services;

use Codeception\Test\Unit;
use craft\commerce\db\Table;
use craft\commerce\Plugin;
use CraftCms\Commerce\CatalogPricing\Records\CatalogPricingQueue as CatalogPricingQueueRecord;
use craftcommercetests\fixtures\StoreFixture;
use UnitTester;

/**
 * CatalogPricingQueueTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.7.0
 */
class CatalogPricingQueueTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected UnitTester $tester;

    private int $_storeId;

    /**
     * @return array
     */
    public function _fixtures(): array
    {
        return [
            'stores' => [
                'class' => StoreFixture::class,
            ],
        ];
    }

    protected function _before(): void
    {
        parent::_before();
        // Clear the catalog pricing queue table before each test
        CatalogPricingQueueRecord::query()->delete();
        $this->_storeId = Plugin::getInstance()->getStores()->getPrimaryStore()->id;
    }

    protected function _after(): void
    {
        parent::_after();
        // Clean up the queue table after each test
        CatalogPricingQueueRecord::query()->delete();
    }

    /**
     * Test that a single purchasable ID creates a new queue row.
     *
     * @see https://github.com/craftcms/commerce/issues/4277
     */
    public function testCreateQueueRowForSinglePurchasableId(): void
    {
        Plugin::getInstance()->getCatalogPricing()->createCatalogPricingJob([
            'purchasableIds' => [1],
            'storeId' => $this->_storeId,
        ]);

        $rows = CatalogPricingQueueRecord::all();
        self::assertCount(1, $rows);

        /** @var CatalogPricingQueueRecord $row */
        $row = $rows[0];
        self::assertEquals(CatalogPricingQueueRecord::TYPE_PURCHASABLE, $row->type);
        self::assertEquals($this->_storeId, $row->storeId);
        self::assertEquals([1], $row->ids);
        self::assertFalse((bool)$row->reserved);
    }

    /**
     * Test that a single rule ID creates a new queue row.
     */
    public function testCreateQueueRowForSingleRuleId(): void
    {
        Plugin::getInstance()->getCatalogPricing()->createCatalogPricingJob([
            'catalogPricingRuleIds' => [5],
            'storeId' => $this->_storeId,
        ]);

        $rows = CatalogPricingQueueRecord::all();
        self::assertCount(1, $rows);

        /** @var CatalogPricingQueueRecord $row */
        $row = $rows[0];
        self::assertEquals(CatalogPricingQueueRecord::TYPE_RULE, $row->type);
        self::assertEquals($this->_storeId, $row->storeId);
        self::assertEquals([5], $row->ids);
        self::assertFalse((bool)$row->reserved);
    }

    /**
     * Test that purchasable and rule IDs create separate queue rows.
     */
    public function testPurchasableAndRuleTypesAreSeparated(): void
    {
        Plugin::getInstance()->getCatalogPricing()->createCatalogPricingJob([
            'purchasableIds' => [1, 2],
            'catalogPricingRuleIds' => [5, 6],
            'storeId' => $this->_storeId,
        ]);

        /** @var CatalogPricingQueueRecord[] $rows */
        $rows = CatalogPricingQueueRecord::orderBy('type')->get();
        self::assertCount(2, $rows);

        // First row should be purchasable type
        $purchasableRow = $rows[0];
        self::assertEquals(CatalogPricingQueueRecord::TYPE_PURCHASABLE, $purchasableRow->type);
        self::assertEquals([1, 2], $purchasableRow->ids);

        // Second row should be rule type
        $ruleRow = $rows[1];
        self::assertEquals(CatalogPricingQueueRecord::TYPE_RULE, $ruleRow->type);
        self::assertEquals([5, 6], $ruleRow->ids);
    }

    /**
     * Test that different stores create separate queue rows for the same type.
     */
    public function testDifferentStoresCreateSeparateRows(): void
    {
        $primaryStore = Plugin::getInstance()->getStores()->getPrimaryStore();
        $ukStore = Plugin::getInstance()->getStores()->getStoreByHandle('ukStore');

        Plugin::getInstance()->getCatalogPricing()->createCatalogPricingJob([
            'purchasableIds' => [1],
            'storeId' => $primaryStore->id,
        ]);

        Plugin::getInstance()->getCatalogPricing()->createCatalogPricingJob([
            'purchasableIds' => [1],
            'storeId' => $ukStore->id,
        ]);

        /** @var CatalogPricingQueueRecord[] $rows */
        $rows = CatalogPricingQueueRecord::orderBy('storeId')->get();
        self::assertCount(2, $rows);

        self::assertEquals($primaryStore->id, $rows[0]->storeId);
        self::assertEquals($ukStore->id, $rows[1]->storeId);
    }

    /**
     * Test that multiple calls to queue IDs for the same store/type merge into one row.
     */
    public function testMultipleQueuesForSameStoreAndTypeMerge(): void
    {
        Plugin::getInstance()->getCatalogPricing()->createCatalogPricingJob([
            'purchasableIds' => [1, 2],
            'storeId' => $this->_storeId,
        ]);

        Plugin::getInstance()->getCatalogPricing()->createCatalogPricingJob([
            'purchasableIds' => [3, 4],
            'storeId' => $this->_storeId,
        ]);

        $rows = CatalogPricingQueueRecord::all();
        self::assertCount(1, $rows, 'Multiple queue calls should merge into a single row');

        $row = $rows[0];
        self::assertEquals([1, 2, 3, 4], $row->ids, 'IDs should be merged and sorted');
    }

    /**
     * Test that duplicate IDs in merged rows are deduplicated.
     */
    public function testDuplicateIdsAreDeduplicated(): void
    {
        Plugin::getInstance()->getCatalogPricing()->createCatalogPricingJob([
            'purchasableIds' => [1, 2, 3],
            'storeId' => $this->_storeId,
        ]);

        Plugin::getInstance()->getCatalogPricing()->createCatalogPricingJob([
            'purchasableIds' => [2, 3, 4],
            'storeId' => $this->_storeId,
        ]);

        $row = CatalogPricingQueueRecord::where(['storeId' => $this->_storeId, 'type' => CatalogPricingQueueRecord::TYPE_PURCHASABLE])->first();
        self::assertEquals([1, 2, 3, 4], $row->ids, 'Duplicate IDs should be removed and sorted');
    }

    /**
     * Test that null storeId (meaning all stores) is properly handled.
     */
    public function testNullStoreIdRepresentsAllStores(): void
    {
        Plugin::getInstance()->getCatalogPricing()->createCatalogPricingJob([
            'purchasableIds' => [1],
            'storeId' => null,
        ]);

        $row = CatalogPricingQueueRecord::where(['type' => CatalogPricingQueueRecord::TYPE_PURCHASABLE])->first();
        self::assertNull($row->storeId, 'storeId should be null to represent all stores');
        self::assertEquals([1], $row->ids);
    }

    /**
     * Test that merging IDs where one side is null expands scope to null (all stores/ids).
     */
    public function testMergingWithNullIdsExpandsScope(): void
    {
        // First queue specific IDs
        Plugin::getInstance()->getCatalogPricing()->createCatalogPricingJob([
            'purchasableIds' => [1, 2],
            'storeId' => $this->_storeId,
        ]);

        // Then queue with null (meaning all), should merge and expand scope
        Plugin::getInstance()->getCatalogPricing()->createCatalogPricingJob([
            'purchasableIds' => null,
            'storeId' => $this->_storeId,
        ]);

        $row = CatalogPricingQueueRecord::where(['storeId' => $this->_storeId, 'type' => CatalogPricingQueueRecord::TYPE_PURCHASABLE])->first();
        self::assertNull($row->ids, 'IDs should be null (broader scope) when merging specific IDs with null');
    }

    /**
     * Test that reserved rows are not merged into.
     */
    public function testReservedRowsAreNotMergedInto(): void
    {
        // Create and reserve a row
        $record = new CatalogPricingQueueRecord();
        $record->storeId = $this->_storeId;
        $record->type = CatalogPricingQueueRecord::TYPE_PURCHASABLE;
        $record->ids = [1];
        $record->reserved = true;
        $record->save();

        // Try to queue more IDs for the same store/type
        Plugin::getInstance()->getCatalogPricing()->createCatalogPricingJob([
            'purchasableIds' => [2],
            'storeId' => $this->_storeId,
        ]);

        /** @var CatalogPricingQueueRecord[] $rows */
        $rows = CatalogPricingQueueRecord::where(['storeId' => $this->_storeId, 'type' => CatalogPricingQueueRecord::TYPE_PURCHASABLE])
            ->orderBy('reserved', 'desc')
            ->get();

        self::assertCount(2, $rows, 'A new row should be created instead of merging into the reserved row');

        // One should be reserved with ID 1
        $reservedRow = $rows[0];
        self::assertNotNull($reservedRow);
        self::assertEquals([1], $reservedRow->ids);

        // One should be unreserved with ID 2
        $unreservedRow = $rows[1];
        self::assertNotNull($unreservedRow);
        self::assertEquals([2], $unreservedRow->ids);
    }

    /**
     * Test that IDs are sorted numerically in the queue row.
     */
    public function testIdsAreSortedNumerically(): void
    {
        Plugin::getInstance()->getCatalogPricing()->createCatalogPricingJob([
            'purchasableIds' => [100, 5, 50, 1],
            'storeId' => $this->_storeId,
        ]);

        $row = CatalogPricingQueueRecord::where(['storeId' => $this->_storeId])->first();
        self::assertEquals([1, 5, 50, 100], $row->ids, 'IDs should be sorted numerically');
    }

    /**
     * Test that zero and negative IDs are filtered out.
     */
    public function testZeroAndNegativeIdsAreFiltered(): void
    {
        Plugin::getInstance()->getCatalogPricing()->createCatalogPricingJob([
            'purchasableIds' => [1, 0, -5, 2],
            'storeId' => $this->_storeId,
        ]);

        $row = CatalogPricingQueueRecord::where(['storeId' => $this->_storeId])->first();
        self::assertEquals([1, 2], $row->ids, 'Zero and negative IDs should be filtered out');
    }

    /**
     * Test that `areCatalogPricingJobsRunning()` returns true when queue has pending rows.
     */
    public function testAreCatalogPricingJobsRunningReturnsTrueWhenPending(): void
    {
        self::assertFalse(Plugin::getInstance()->getCatalogPricing()->areCatalogPricingJobsRunning(), 'Should be false when queue is empty');

        Plugin::getInstance()->getCatalogPricing()->createCatalogPricingJob([
            'purchasableIds' => [1],
            'storeId' => $this->_storeId,
        ]);

        self::assertTrue(Plugin::getInstance()->getCatalogPricing()->areCatalogPricingJobsRunning(), 'Should be true when queue has pending rows');
    }

    /**
     * Test that `reserveCatalogPricingQueueRow()` marks a row as reserved.
     */
    public function testReserveCatalogPricingQueueRowMarksAsReserved(): void
    {
        Plugin::getInstance()->getCatalogPricing()->createCatalogPricingJob([
            'purchasableIds' => [1],
            'storeId' => $this->_storeId,
        ]);

        // All rows should be unreserved initially
        self::assertCount(0, CatalogPricingQueueRecord::where(['reserved' => true])->get());

        $reserved = Plugin::getInstance()->getCatalogPricing()->reserveCatalogPricingQueueRow();

        self::assertNotNull($reserved, 'Should return a reserved row');
        self::assertTrue((bool)$reserved->reserved);
        self::assertEquals([1], $reserved->ids);

        // Verify in database
        $dbRow = CatalogPricingQueueRecord::find($reserved->id);
        self::assertTrue((bool)$dbRow->reserved);
    }

    /**
     * Test that multiple pending rows can be reserved one at a time.
     */
    public function testMultiplePendingRowsCanBeReservedInOrder(): void
    {
        Plugin::getInstance()->getCatalogPricing()->createCatalogPricingJob([
            'purchasableIds' => [1],
            'storeId' => $this->_storeId,
        ]);

        Plugin::getInstance()->getCatalogPricing()->createCatalogPricingJob([
            'purchasableIds' => [2],
            'storeId' => Plugin::getInstance()->getStores()->getStoreByHandle('ukStore')->id,
        ]);

        $first = Plugin::getInstance()->getCatalogPricing()->reserveCatalogPricingQueueRow();
        self::assertNotNull($first);
        self::assertEquals([1], $first->ids);

        $second = Plugin::getInstance()->getCatalogPricing()->reserveCatalogPricingQueueRow();
        self::assertNotNull($second);
        self::assertEquals([2], $second->ids);

        $third = Plugin::getInstance()->getCatalogPricing()->reserveCatalogPricingQueueRow();
        self::assertNull($third, 'Should return null when no pending rows remain');
    }

    /**
     * Test that `releaseCatalogPricingQueueRowById()` marks a reserved row as unreserved.
     */
    public function testReleaseCatalogPricingQueueByIdMarksAsUnreserved(): void
    {
        Plugin::getInstance()->getCatalogPricing()->createCatalogPricingJob([
            'purchasableIds' => [1],
            'storeId' => $this->_storeId,
        ]);

        $reserved = Plugin::getInstance()->getCatalogPricing()->reserveCatalogPricingQueueRow();
        self::assertTrue((bool)$reserved->reserved);

        Plugin::getInstance()->getCatalogPricing()->releaseCatalogPricingQueueRowById($reserved->id);

        $released = CatalogPricingQueueRecord::find($reserved->id);
        self::assertFalse((bool)$released->reserved);
    }

    /**
     * Test that `deleteCatalogPricingQueueRowById()` removes a row from the queue.
     */
    public function testDeleteCatalogPricingQueueByIdRemovesRow(): void
    {
        Plugin::getInstance()->getCatalogPricing()->createCatalogPricingJob([
            'purchasableIds' => [1],
            'storeId' => $this->_storeId,
        ]);

        $row = CatalogPricingQueueRecord::where(['storeId' => $this->_storeId])->first();
        self::assertNotNull($row);

        Plugin::getInstance()->getCatalogPricing()->deleteCatalogPricingQueueRowById($row->id);

        $deleted = CatalogPricingQueueRecord::find($row->id);
        self::assertNull($deleted);
    }

    /**
     * Test complex scenario: multiple stores and types with merging and reservation.
     *
     * @dataProvider complexQueueScenarioDataProvider
     */
    public function testComplexQueueScenario(array $queueCalls, array $expectedRows): void
    {
        $stores = Plugin::getInstance()->getStores()->getAllStores();
        // Execute all queue calls
        foreach ($queueCalls as $call) {
            $call['storeId'] = $stores->firstWhere('handle', $call['storeId'])?->id ?? null;
            Plugin::getInstance()->getCatalogPricing()->createCatalogPricingJob($call);
        }

        // Verify the state of all rows
        /** @var CatalogPricingQueueRecord[] $allRows */
        $allRows = CatalogPricingQueueRecord::all();
        self::assertCount(count($expectedRows), $allRows, 'Should have expected number of rows');

        foreach ($expectedRows as $index => $expected) {
            $expected['storeId'] = $stores->firstWhere('handle', $expected['storeId'])?->id ?? null;
            $row = $allRows[$index] ?? null;
            self::assertNotNull($row, "Row at index $index should exist");
            self::assertEquals($expected['storeId'] ?? null, $row->storeId, "Row $index storeId mismatch");
            self::assertEquals($expected['type'], $row->type, "Row $index type mismatch");
            self::assertEquals($expected['ids'], $row->ids, "Row $index IDs mismatch");
        }
    }

    public function complexQueueScenarioDataProvider(): array
    {
        $primaryStoreHandle = 'primary';
        $ukStoreHandle = 'ukStore';

        return [
            'single-store-multiple-types' => [
                [
                    ['purchasableIds' => [1, 2], 'storeId' => $primaryStoreHandle],
                    ['catalogPricingRuleIds' => [10, 11], 'storeId' => $primaryStoreHandle],
                ],
                [
                    ['storeId' => $primaryStoreHandle, 'type' => CatalogPricingQueueRecord::TYPE_PURCHASABLE, 'ids' => [1, 2]],
                    ['storeId' => $primaryStoreHandle, 'type' => CatalogPricingQueueRecord::TYPE_RULE, 'ids' => [10, 11]],
                ],
            ],
            'multi-store-same-type-merging' => [
                [
                    ['purchasableIds' => [1], 'storeId' => $primaryStoreHandle],
                    ['purchasableIds' => [2], 'storeId' => $primaryStoreHandle],
                    ['purchasableIds' => [1], 'storeId' => $ukStoreHandle],
                ],
                [
                    ['storeId' => $primaryStoreHandle, 'type' => CatalogPricingQueueRecord::TYPE_PURCHASABLE, 'ids' => [1, 2]],
                    ['storeId' => $ukStoreHandle, 'type' => CatalogPricingQueueRecord::TYPE_PURCHASABLE, 'ids' => [1]],
                ],
            ],
            'null-store-with-specific-stores' => [
                [
                    ['purchasableIds' => [1, 2], 'storeId' => $primaryStoreHandle],
                    ['purchasableIds' => [3], 'storeId' => null],
                ],
                [
                    ['storeId' => $primaryStoreHandle, 'type' => CatalogPricingQueueRecord::TYPE_PURCHASABLE, 'ids' => [1, 2]],
                    ['storeId' => null, 'type' => CatalogPricingQueueRecord::TYPE_PURCHASABLE, 'ids' => [3]],
                ],
            ],
        ];
    }
}
