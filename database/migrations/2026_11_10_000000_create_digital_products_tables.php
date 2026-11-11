<?php

declare(strict_types=1);

use CraftCms\Cms\Database\Migration;
use CraftCms\Cms\Database\Table as CraftTable;
use CraftCms\Commerce\Database\Table;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Digital products became a first-party Commerce feature in 6.0. Data from the Digital Products plugin
 * is moved into these tables by the `commerce:digital-products:migrate` command, not by this migration.
 */
return new class() extends Migration {
    public function up(): void
    {
        if (Schema::hasTable(Table::DIGITALPRODUCTTYPES)) {
            return;
        }

        Schema::create(Table::DIGITALPRODUCTTYPES, function(Blueprint $table) {
            $table->integer('id', true);
            $table->integer('fieldLayoutId')->nullable();
            $table->string('name');
            $table->string('handle');
            $table->string('skuFormat')->nullable();
            $table->dateTime('dateCreated');
            $table->dateTime('dateUpdated');
            $table->char('uid', 36)->default('0');
        });

        Schema::create(Table::DIGITALPRODUCTTYPES_SITES, function(Blueprint $table) {
            $table->integer('id', true);
            $table->integer('digitalProductTypeId');
            $table->integer('siteId');
            $table->text('uriFormat')->nullable();
            $table->string('template', 500)->nullable();
            $table->boolean('hasUrls')->default(false);
            $table->dateTime('dateCreated');
            $table->dateTime('dateUpdated');
            $table->char('uid', 36)->default('0');
        });

        Schema::create(Table::DIGITALPRODUCTS, function(Blueprint $table) {
            $table->integer('id');
            $table->integer('typeId');
            $table->dateTime('postDate')->nullable();
            $table->dateTime('expiryDate')->nullable();
            $table->dateTime('dateCreated');
            $table->dateTime('dateUpdated');
            $table->char('uid', 36)->default('0');
            $table->primary('id');
        });

        Schema::create(Table::LICENSES, function(Blueprint $table) {
            $table->integer('id');
            $table->integer('productId')->nullable();
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

        Schema::createIndex(Table::DIGITALPRODUCTS, ['typeId']);
        Schema::createIndex(Table::DIGITALPRODUCTS, ['postDate']);
        Schema::createIndex(Table::DIGITALPRODUCTS, ['expiryDate']);
        Schema::createIndex(Table::DIGITALPRODUCTTYPES, ['handle'], unique: true);
        Schema::createIndex(Table::DIGITALPRODUCTTYPES, ['fieldLayoutId']);
        Schema::createIndex(Table::DIGITALPRODUCTTYPES_SITES, ['digitalProductTypeId', 'siteId'], unique: true);
        Schema::createIndex(Table::DIGITALPRODUCTTYPES_SITES, ['siteId']);
        Schema::createIndex(Table::LICENSES, ['licenseKey'], unique: true);
        Schema::createIndex(Table::LICENSES, ['orderId']);
        Schema::createIndex(Table::LICENSES, ['productId']);
        Schema::createIndex(Table::LICENSES, ['userId']);

        Schema::table(Table::DIGITALPRODUCTS, fn(Blueprint $table) => $table->foreign('id')->references('id')->on(CraftTable::ELEMENTS)->cascadeOnDelete());
        Schema::table(Table::DIGITALPRODUCTS, fn(Blueprint $table) => $table->foreign('typeId')->references('id')->on(Table::DIGITALPRODUCTTYPES)->cascadeOnDelete());
        Schema::table(Table::DIGITALPRODUCTTYPES, fn(Blueprint $table) => $table->foreign('fieldLayoutId')->references('id')->on(CraftTable::FIELDLAYOUTS)->nullOnDelete());
        Schema::table(Table::DIGITALPRODUCTTYPES_SITES, fn(Blueprint $table) => $table->foreign('digitalProductTypeId')->references('id')->on(Table::DIGITALPRODUCTTYPES)->cascadeOnDelete());
        Schema::table(Table::DIGITALPRODUCTTYPES_SITES, fn(Blueprint $table) => $table->foreign('siteId')->references('id')->on(CraftTable::SITES)->cascadeOnDelete()->cascadeOnUpdate());
        Schema::table(Table::LICENSES, fn(Blueprint $table) => $table->foreign('id')->references('id')->on(CraftTable::ELEMENTS)->cascadeOnDelete());
        Schema::table(Table::LICENSES, fn(Blueprint $table) => $table->foreign('productId')->references('id')->on(Table::DIGITALPRODUCTS)->nullOnDelete());
        Schema::table(Table::LICENSES, fn(Blueprint $table) => $table->foreign('orderId')->references('id')->on(Table::ORDERS)->nullOnDelete());
        Schema::table(Table::LICENSES, fn(Blueprint $table) => $table->foreign('userId')->references('id')->on(CraftTable::USERS)->nullOnDelete());
    }
};
