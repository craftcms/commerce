<?php

declare(strict_types=1);

use CraftCms\Cms\Database\Migration;
use CraftCms\Commerce\Database\Table;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds license expiry: product type and variant durations, and the expiry date licenses are issued with.
 */
return new class() extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn(Table::PRODUCTTYPES, 'hasLicenseExpiry')) {
            Schema::table(Table::PRODUCTTYPES, function(Blueprint $table) {
                $table->boolean('hasLicenseExpiry')->default(false)->after('licenseDownloadFieldUid');
                $table->string('licenseExpiryDuration')->nullable()->after('hasLicenseExpiry');
                $table->boolean('allowLicenseExpiryOverride')->default(false)->after('licenseExpiryDuration');
            });
        }

        if (!Schema::hasColumn(Table::VARIANTS, 'licenseExpires')) {
            Schema::table(Table::VARIANTS, function(Blueprint $table) {
                $table->boolean('licenseExpires')->default(true)->after('issuesLicense');
                $table->string('licenseExpiryDuration')->nullable()->after('licenseExpires');
            });
        }

        if (!Schema::hasColumn(Table::LICENSES, 'expiryDate')) {
            Schema::table(Table::LICENSES, function(Blueprint $table) {
                $table->dateTime('expiryDate')->nullable()->after('userId');
            });

            Schema::createIndex(Table::LICENSES, ['expiryDate']);
        }
    }

    public function down(): void
    {
        Schema::table(Table::LICENSES, fn(Blueprint $table) => $table->dropColumn('expiryDate'));
        Schema::table(Table::VARIANTS, fn(Blueprint $table) => $table->dropColumn(['licenseExpires', 'licenseExpiryDuration']));
        Schema::table(Table::PRODUCTTYPES, fn(Blueprint $table) => $table->dropColumn(['hasLicenseExpiry', 'licenseExpiryDuration', 'allowLicenseExpiryOverride']));
    }
};
