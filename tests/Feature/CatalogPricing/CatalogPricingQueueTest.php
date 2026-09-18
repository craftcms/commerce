<?php

declare(strict_types=1);

use CraftCms\Commerce\CatalogPricing\CatalogPricing;
use CraftCms\Commerce\CatalogPricing\Jobs\CatalogPricingJob;
use CraftCms\Commerce\CatalogPricing\Models\CatalogPricingQueue as CatalogPricingQueueRecord;
use CraftCms\Commerce\Tests\Support\StoresFixture;
use Illuminate\Support\Facades\Queue;

/**
 * `createCatalogPricingJob()` dispatches a `CatalogPricingJob` alongside writing the queue row, and
 * the default `sync` queue connection would run it immediately, processing (and deleting) the row
 * before these tests get a chance to inspect it. Faking the queue lets these tests observe the
 * queue table in isolation from job execution, which is covered separately.
 */
beforeEach(function() {
    Queue::fake();
    $this->fixture = StoresFixture::seed();
});

test('createCatalogPricingJob creates a new queue row for a single purchasable ID', function() {
    app(CatalogPricing::class)->createCatalogPricingJob([
        'purchasableIds' => [1],
        'storeId' => $this->fixture->primaryStore->id,
    ]);

    $rows = CatalogPricingQueueRecord::all();
    expect($rows)->toHaveCount(1);

    $row = $rows[0];
    expect($row->type)->toBe(CatalogPricingQueueRecord::TYPE_PURCHASABLE);
    expect($row->storeId)->toBe($this->fixture->primaryStore->id);
    expect($row->ids)->toBe([1]);
    expect($row->reserved)->toBeFalse();

    Queue::assertPushed(CatalogPricingJob::class);
});

test('createCatalogPricingJob creates a new queue row for a single rule ID', function() {
    app(CatalogPricing::class)->createCatalogPricingJob([
        'catalogPricingRuleIds' => [5],
        'storeId' => $this->fixture->primaryStore->id,
    ]);

    $rows = CatalogPricingQueueRecord::all();
    expect($rows)->toHaveCount(1);

    $row = $rows[0];
    expect($row->type)->toBe(CatalogPricingQueueRecord::TYPE_RULE);
    expect($row->storeId)->toBe($this->fixture->primaryStore->id);
    expect($row->ids)->toBe([5]);
    expect($row->reserved)->toBeFalse();
});

test('purchasable and rule types are queued as separate rows', function() {
    app(CatalogPricing::class)->createCatalogPricingJob([
        'purchasableIds' => [1, 2],
        'catalogPricingRuleIds' => [5, 6],
        'storeId' => $this->fixture->primaryStore->id,
    ]);

    $rows = CatalogPricingQueueRecord::orderBy('type')->get();
    expect($rows)->toHaveCount(2);

    expect($rows[0]->type)->toBe(CatalogPricingQueueRecord::TYPE_PURCHASABLE);
    expect($rows[0]->ids)->toBe([1, 2]);

    expect($rows[1]->type)->toBe(CatalogPricingQueueRecord::TYPE_RULE);
    expect($rows[1]->ids)->toBe([5, 6]);
});

test('different stores create separate queue rows for the same type', function() {
    app(CatalogPricing::class)->createCatalogPricingJob([
        'purchasableIds' => [1],
        'storeId' => $this->fixture->primaryStore->id,
    ]);

    app(CatalogPricing::class)->createCatalogPricingJob([
        'purchasableIds' => [1],
        'storeId' => $this->fixture->ukStore->id,
    ]);

    $rows = CatalogPricingQueueRecord::orderBy('storeId')->get();
    expect($rows)->toHaveCount(2);
    expect($rows[0]->storeId)->toBe($this->fixture->primaryStore->id);
    expect($rows[1]->storeId)->toBe($this->fixture->ukStore->id);
});

test('multiple queue calls for the same store and type merge into one row', function() {
    app(CatalogPricing::class)->createCatalogPricingJob([
        'purchasableIds' => [1, 2],
        'storeId' => $this->fixture->primaryStore->id,
    ]);

    app(CatalogPricing::class)->createCatalogPricingJob([
        'purchasableIds' => [3, 4],
        'storeId' => $this->fixture->primaryStore->id,
    ]);

    $rows = CatalogPricingQueueRecord::all();
    expect($rows)->toHaveCount(1);
    expect($rows[0]->ids)->toBe([1, 2, 3, 4]);
});

test('duplicate IDs across merged rows are deduplicated', function() {
    app(CatalogPricing::class)->createCatalogPricingJob([
        'purchasableIds' => [1, 2, 3],
        'storeId' => $this->fixture->primaryStore->id,
    ]);

    app(CatalogPricing::class)->createCatalogPricingJob([
        'purchasableIds' => [2, 3, 4],
        'storeId' => $this->fixture->primaryStore->id,
    ]);

    $row = CatalogPricingQueueRecord::where('storeId', $this->fixture->primaryStore->id)
        ->where('type', CatalogPricingQueueRecord::TYPE_PURCHASABLE)
        ->first();

    expect($row->ids)->toBe([1, 2, 3, 4]);
});

test('a null storeId represents all stores', function() {
    app(CatalogPricing::class)->createCatalogPricingJob([
        'purchasableIds' => [1],
        'storeId' => null,
    ]);

    $row = CatalogPricingQueueRecord::where('type', CatalogPricingQueueRecord::TYPE_PURCHASABLE)->first();
    expect($row->storeId)->toBeNull();
    expect($row->ids)->toBe([1]);
});

test('merging specific IDs with null IDs expands the scope to null', function() {
    app(CatalogPricing::class)->createCatalogPricingJob([
        'purchasableIds' => [1, 2],
        'storeId' => $this->fixture->primaryStore->id,
    ]);

    app(CatalogPricing::class)->createCatalogPricingJob([
        'purchasableIds' => null,
        'storeId' => $this->fixture->primaryStore->id,
    ]);

    $row = CatalogPricingQueueRecord::where('storeId', $this->fixture->primaryStore->id)
        ->where('type', CatalogPricingQueueRecord::TYPE_PURCHASABLE)
        ->first();

    expect($row->ids)->toBeNull();
});

test('a reserved row is not merged into, and a new row is created instead', function() {
    $record = new CatalogPricingQueueRecord();
    $record->storeId = $this->fixture->primaryStore->id;
    $record->type = CatalogPricingQueueRecord::TYPE_PURCHASABLE;
    $record->ids = [1];
    $record->reserved = true;
    $record->save();

    app(CatalogPricing::class)->createCatalogPricingJob([
        'purchasableIds' => [2],
        'storeId' => $this->fixture->primaryStore->id,
    ]);

    $rows = CatalogPricingQueueRecord::where('storeId', $this->fixture->primaryStore->id)
        ->where('type', CatalogPricingQueueRecord::TYPE_PURCHASABLE)
        ->orderBy('reserved', 'desc')
        ->get();

    expect($rows)->toHaveCount(2);
    expect($rows[0]->ids)->toBe([1]);
    expect($rows[1]->ids)->toBe([2]);
});

test('IDs are sorted numerically', function() {
    app(CatalogPricing::class)->createCatalogPricingJob([
        'purchasableIds' => [100, 5, 50, 1],
        'storeId' => $this->fixture->primaryStore->id,
    ]);

    $row = CatalogPricingQueueRecord::where('storeId', $this->fixture->primaryStore->id)->first();
    expect($row->ids)->toBe([1, 5, 50, 100]);
});

test('zero and negative IDs are filtered out', function() {
    app(CatalogPricing::class)->createCatalogPricingJob([
        'purchasableIds' => [1, 0, -5, 2],
        'storeId' => $this->fixture->primaryStore->id,
    ]);

    $row = CatalogPricingQueueRecord::where('storeId', $this->fixture->primaryStore->id)->first();
    expect($row->ids)->toBe([1, 2]);
});

test('areCatalogPricingJobsRunning reflects whether the queue has pending rows', function() {
    expect(app(CatalogPricing::class)->areCatalogPricingJobsRunning())->toBeFalse();

    app(CatalogPricing::class)->createCatalogPricingJob([
        'purchasableIds' => [1],
        'storeId' => $this->fixture->primaryStore->id,
    ]);

    expect(app(CatalogPricing::class)->areCatalogPricingJobsRunning())->toBeTrue();
});

test('reserveCatalogPricingQueueRow marks a pending row as reserved', function() {
    app(CatalogPricing::class)->createCatalogPricingJob([
        'purchasableIds' => [1],
        'storeId' => $this->fixture->primaryStore->id,
    ]);

    expect(CatalogPricingQueueRecord::where('reserved', true)->count())->toBe(0);

    $reserved = app(CatalogPricing::class)->reserveCatalogPricingQueueRow();

    expect($reserved)->not->toBeNull();
    expect($reserved->reserved)->toBeTrue();
    expect($reserved->ids)->toBe([1]);
    expect(CatalogPricingQueueRecord::find($reserved->id)->reserved)->toBeTrue();
});

test('pending rows are reserved one at a time, in order, until none remain', function() {
    app(CatalogPricing::class)->createCatalogPricingJob([
        'purchasableIds' => [1],
        'storeId' => $this->fixture->primaryStore->id,
    ]);

    app(CatalogPricing::class)->createCatalogPricingJob([
        'purchasableIds' => [2],
        'storeId' => $this->fixture->ukStore->id,
    ]);

    $first = app(CatalogPricing::class)->reserveCatalogPricingQueueRow();
    expect($first->ids)->toBe([1]);

    $second = app(CatalogPricing::class)->reserveCatalogPricingQueueRow();
    expect($second->ids)->toBe([2]);

    expect(app(CatalogPricing::class)->reserveCatalogPricingQueueRow())->toBeNull();
});

test('releaseCatalogPricingQueueRowById marks a reserved row as unreserved', function() {
    app(CatalogPricing::class)->createCatalogPricingJob([
        'purchasableIds' => [1],
        'storeId' => $this->fixture->primaryStore->id,
    ]);

    $reserved = app(CatalogPricing::class)->reserveCatalogPricingQueueRow();
    expect($reserved->reserved)->toBeTrue();

    app(CatalogPricing::class)->releaseCatalogPricingQueueRowById($reserved->id);

    expect(CatalogPricingQueueRecord::find($reserved->id)->reserved)->toBeFalse();
});

test('deleteCatalogPricingQueueRowById removes a row from the queue', function() {
    app(CatalogPricing::class)->createCatalogPricingJob([
        'purchasableIds' => [1],
        'storeId' => $this->fixture->primaryStore->id,
    ]);

    $row = CatalogPricingQueueRecord::where('storeId', $this->fixture->primaryStore->id)->first();
    expect($row)->not->toBeNull();

    app(CatalogPricing::class)->deleteCatalogPricingQueueRowById($row->id);

    expect(CatalogPricingQueueRecord::find($row->id))->toBeNull();
});

test('a complex sequence of queue calls across stores and types produces the expected rows', function(array $queueCalls, array $expectedRows) {
    $storeIdByHandle = fn(?string $handle) => match ($handle) {
        'primary' => $this->fixture->primaryStore->id,
        'ukStore' => $this->fixture->ukStore->id,
        null => null,
    };

    foreach ($queueCalls as $call) {
        $call['storeId'] = $storeIdByHandle($call['storeId']);
        app(CatalogPricing::class)->createCatalogPricingJob($call);
    }

    $rows = CatalogPricingQueueRecord::all();
    expect($rows)->toHaveCount(count($expectedRows));

    foreach ($expectedRows as $index => $expected) {
        $row = $rows[$index];
        expect($row->storeId)->toBe($storeIdByHandle($expected['storeId']));
        expect($row->type)->toBe($expected['type']);
        expect($row->ids)->toBe($expected['ids']);
    }
})->with([
    'a single store queues purchasable and rule work as separate rows' => [
        [
            ['purchasableIds' => [1, 2], 'storeId' => 'primary'],
            ['catalogPricingRuleIds' => [10, 11], 'storeId' => 'primary'],
        ],
        [
            ['storeId' => 'primary', 'type' => CatalogPricingQueueRecord::TYPE_PURCHASABLE, 'ids' => [1, 2]],
            ['storeId' => 'primary', 'type' => CatalogPricingQueueRecord::TYPE_RULE, 'ids' => [10, 11]],
        ],
    ],
    'the same type merges within a store but stays separate across stores' => [
        [
            ['purchasableIds' => [1], 'storeId' => 'primary'],
            ['purchasableIds' => [2], 'storeId' => 'primary'],
            ['purchasableIds' => [1], 'storeId' => 'ukStore'],
        ],
        [
            ['storeId' => 'primary', 'type' => CatalogPricingQueueRecord::TYPE_PURCHASABLE, 'ids' => [1, 2]],
            ['storeId' => 'ukStore', 'type' => CatalogPricingQueueRecord::TYPE_PURCHASABLE, 'ids' => [1]],
        ],
    ],
    'a null storeId (all stores) stays separate from a specific store' => [
        [
            ['purchasableIds' => [1, 2], 'storeId' => 'primary'],
            ['purchasableIds' => [3], 'storeId' => null],
        ],
        [
            ['storeId' => 'primary', 'type' => CatalogPricingQueueRecord::TYPE_PURCHASABLE, 'ids' => [1, 2]],
            ['storeId' => null, 'type' => CatalogPricingQueueRecord::TYPE_PURCHASABLE, 'ids' => [3]],
        ],
    ],
]);
