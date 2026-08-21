<?php

use CraftCms\Cms\Database\Migration;
use CraftCms\Commerce\Database\Table;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn(Table::ORDERNOTICES, 'noticeType')) {
            Schema::table(Table::ORDERNOTICES, function (Blueprint $table) {
                $table->string('noticeType')->default('customer');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn(Table::ORDERNOTICES, 'noticeType')) {
            Schema::table(Table::ORDERNOTICES, function (Blueprint $table) {
                $table->dropColumn('noticeType');
            });
        }
    }
};
