<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Helpers;

use Illuminate\Support\Facades\DB;

/**
 * Raw-SQL fragments that differ per database driver, for use in `DB::statement()`/`DB::raw()`
 * calls that can't go through the query builder.
 */
class Sql
{
    /**
     * Returns a raw SQL expression that generates a random UUID for the current connection's
     * database driver.
     */
    public static function uuidSql(): string
    {
        return match (DB::connection()->getDriverName()) {
            'pgsql' => 'gen_random_uuid()',
            // SQLite (test suite only) has no UUID builtin; assemble a v4-shaped one from random blobs.
            'sqlite' => "(lower(hex(randomblob(4))) || '-' || lower(hex(randomblob(2))) || '-4' || substr(lower(hex(randomblob(2))), 2) || '-' || substr('89ab', abs(random()) % 4 + 1, 1) || substr(lower(hex(randomblob(2))), 2) || '-' || lower(hex(randomblob(6))))",
            default => 'UUID()',
        };
    }

    /**
     * Returns a raw SQL expression for the current timestamp, for the current connection's
     * database driver.
     */
    public static function nowSql(): string
    {
        return DB::connection()->getDriverName() === 'sqlite' ? "datetime('now')" : 'NOW()';
    }
}
