<?php

use CraftCms\Cms\Database\Migration;
use CraftCms\Commerce\Database\Table;
use craft\commerce\records\CatalogPricingQueue;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable(Table::CATALOG_PRICING_QUEUE)) {
            Schema::create(Table::CATALOG_PRICING_QUEUE, function (Blueprint $table) {
                $table->id();
                $table->integer('storeId')->nullable();
                $table->enum('type', [CatalogPricingQueue::TYPE_PURCHASABLE, CatalogPricingQueue::TYPE_RULE]);
                $table->mediumText('ids')->nullable();
                $table->boolean('reserved')->default(false);
                $table->dateTime('dateCreated');
                $table->dateTime('dateUpdated');
                $table->char('uid', 36)->default('0');
            });
        }

        Schema::table(Table::CATALOG_PRICING_QUEUE, function (Blueprint $table) {
            $table->index('reserved');
            $table->index(['storeId', 'type', 'reserved']);
            $table->foreign('storeId')->references('id')->on(Table::STORES)->cascadeOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        $this->output->error('2026_04_07_000000_add_catalog_pricing_queue_table cannot be reverted.');
    }
};
