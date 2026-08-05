<?php

use CraftCms\Cms\Database\Migration;
use CraftCms\Commerce\Database\Table;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasColumn(Table::ORDERS, 'customerDeleted')) {
            return;
        }

        Schema::table(Table::ORDERS, function (Blueprint $table) {
            $table->boolean('customerDeleted')->default(false)->after('customerId');
        });
    }

    public function down(): void
    {
        $this->output->error('2026_05_05_071943_add_orders_customerDeleted_column cannot be reverted.');
    }
};
