<?php

declare(strict_types=1);

use CraftCms\Cms\Database\Factories\EntryFactory;
use CraftCms\Cms\Database\Factories\FieldFactory;
use CraftCms\Cms\Database\Table as CraftTable;
use CraftCms\Cms\Field\Entries;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\LineItem\LineItems;
use CraftCms\Commerce\Promotion\Data\Discount;
use CraftCms\Commerce\Promotion\Data\Sale;
use CraftCms\Commerce\Promotion\Discounts;
use CraftCms\Commerce\Promotion\Models\Discount as DiscountRecord;
use CraftCms\Commerce\Promotion\Sales;
use CraftCms\Commerce\Tests\Support\OrdersFixture;
use Illuminate\Support\Facades\DB;

beforeEach(function() {
    $this->fixture = OrdersFixture::seed();
    $this->variant = $this->fixture->white;

    $field = FieldFactory::new()->create(['handle' => 'relatedProducts', 'type' => Entries::class]);
    $this->relatedEntry = EntryFactory::new()->createElement();
    $this->unrelatedEntry = EntryFactory::new()->createElement();

    DB::table(CraftTable::RELATIONS)->insert([
        'fieldId' => $field->id,
        'sourceId' => $this->relatedEntry->id,
        'sourceSiteId' => $this->relatedEntry->siteId,
        'targetId' => $this->variant->id,
        'sortOrder' => 1,
        'dateCreated' => now(),
        'dateUpdated' => now(),
        'uid' => fake()->uuid(),
    ]);

    $order = new Order(['orderSiteId' => $this->relatedEntry->siteId]);
    $this->lineItem = app(LineItems::class)->create($order, ['purchasableId' => $this->variant->id, 'qty' => 1]);
    $this->lineItem->setOrder($order);
    $this->lineItem->setIsPromotable(true);
});

test('discounts restricted to entries match purchasables related to those entries', function(string $entry, string $relationshipType, bool $matches) {
    $discount = new Discount([
        'allPurchasables' => true,
        'allEntries' => false,
        'entryRelationshipType' => $relationshipType,
    ]);
    $discount->setEntryIds([$this->$entry->id]);

    expect(app(Discounts::class)->matchLineItem($this->lineItem, $discount))->toBe($matches);
})->with([
    'related entry, either direction' => ['relatedEntry', DiscountRecord::ENTRY_RELATIONSHIP_TYPE_BOTH, true],
    'related entry, entry is the source' => ['relatedEntry', DiscountRecord::ENTRY_RELATIONSHIP_TYPE_TARGET, true],
    'related entry, entry is the target' => ['relatedEntry', DiscountRecord::ENTRY_RELATIONSHIP_TYPE_SOURCE, false],
    'unrelated entry' => ['unrelatedEntry', DiscountRecord::ENTRY_RELATIONSHIP_TYPE_BOTH, false],
]);

test('sales restricted to entries match purchasables related to those entries', function(string $entry, bool $matches) {
    $sale = new Sale([
        'allGroups' => true,
        'allPurchasables' => true,
        'allEntries' => false,
    ]);
    $sale->setEntryIds([$this->$entry->id]);

    expect(app(Sales::class)->matchPurchasableAndSale($this->variant, $sale))->toBe($matches);
})->with([
    'related entry' => ['relatedEntry', true],
    'unrelated entry' => ['unrelatedEntry', false],
]);
