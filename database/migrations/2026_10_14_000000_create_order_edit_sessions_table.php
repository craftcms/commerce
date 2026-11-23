<?php

declare(strict_types=1);

use CraftCms\Cms\Database\Migration;
use CraftCms\Cms\Database\Table as CraftTable;
use CraftCms\Commerce\Database\Table;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable(Table::ORDER_EDIT_SESSIONS)) {
            return;
        }

        Schema::create(Table::ORDER_EDIT_SESSIONS, function(Blueprint $table) {
            $table->integer('id', true);
            $table->integer('orderId');
            $table->integer('userId')->nullable();
            $table->json('payload');
            $table->dateTime('dateCreated');
            $table->dateTime('dateUpdated');
            $table->char('uid', 36)->default('0');
        });

        Schema::createIndex(Table::ORDER_EDIT_SESSIONS, ['orderId'], unique: true);
        Schema::createIndex(Table::ORDER_EDIT_SESSIONS, ['userId']);

        Schema::table(Table::ORDER_EDIT_SESSIONS, function(Blueprint $table) {
            $table->foreign('orderId')->references('id')->on(Table::ORDERS)->cascadeOnDelete();
            $table->foreign('userId')->references('id')->on(CraftTable::USERS)->nullOnDelete();
        });
    }
};
