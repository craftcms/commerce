<?php

use craft\helpers\Db as DbHelper;
use CraftCms\Cms\Database\Migration;
use CraftCms\Cms\Database\Table as CraftTable;
use CraftCms\Commerce\Database\Table;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Drop the existing RESTRICT FK and replace with CASCADE so subscriptions
        // are hard-deleted when their user is hard-deleted.
        DbHelper::dropForeignKeyIfExists(Table::SUBSCRIPTIONS, ['userId']);

        Schema::table(Table::SUBSCRIPTIONS, function (Blueprint $table) {
            $table->foreign('userId')->references('id')->on(CraftTable::USERS)->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        $this->output->error('2026_05_07_000000_subscriptions_nullable_userId cannot be reverted.');
    }
};
