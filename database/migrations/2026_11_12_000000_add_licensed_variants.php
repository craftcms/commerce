<?php

declare(strict_types=1);

use CraftCms\Cms\Database\Migration;
use CraftCms\Cms\Database\Table as CraftTable;
use CraftCms\Commerce\Database\Table;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds licenses, which variants issue on purchase when their product type allows it.
 */
return new class() extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn(Table::PRODUCTTYPES, 'hasLicenses')) {
            Schema::table(Table::PRODUCTTYPES, function(Blueprint $table) {
                $table->boolean('hasLicenses')->default(false)->after('hasDimensions');
                $table->char('licenseDownloadFieldUid', 36)->nullable()->after('hasLicenses');
            });
        }

        if (!Schema::hasColumn(Table::VARIANTS, 'issuesLicense')) {
            Schema::table(Table::VARIANTS, function(Blueprint $table) {
                $table->boolean('issuesLicense')->default(false)->after('isDefault');
            });
        }

        if (!Schema::hasTable(Table::LICENSES)) {
            Schema::create(Table::LICENSES, function(Blueprint $table) {
                $table->integer('id');
                $table->integer('variantId')->nullable();
                $table->integer('orderId')->nullable();
                $table->string('licenseKey');
                $table->string('ownerName')->nullable();
                $table->string('ownerEmail')->nullable();
                $table->integer('userId')->nullable();
                $table->dateTime('dateCreated');
                $table->dateTime('dateUpdated');
                $table->char('uid', 36)->default('0');
                $table->primary('id');
            });

            Schema::createIndex(Table::LICENSES, ['licenseKey'], unique: true);
            Schema::createIndex(Table::LICENSES, ['orderId']);
            Schema::createIndex(Table::LICENSES, ['variantId']);
            Schema::createIndex(Table::LICENSES, ['userId']);

            Schema::table(Table::LICENSES, fn(Blueprint $table) => $table->foreign('id')->references('id')->on(CraftTable::ELEMENTS)->cascadeOnDelete());
            Schema::table(Table::LICENSES, fn(Blueprint $table) => $table->foreign('variantId')->references('id')->on(Table::VARIANTS)->nullOnDelete());
            Schema::table(Table::LICENSES, fn(Blueprint $table) => $table->foreign('orderId')->references('id')->on(Table::ORDERS)->nullOnDelete());
            Schema::table(Table::LICENSES, fn(Blueprint $table) => $table->foreign('userId')->references('id')->on(CraftTable::USERS)->nullOnDelete());
        }
    }

    public function down(): void
    {
        Schema::dropIfExists(Table::LICENSES);
    }
};
