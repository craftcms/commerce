<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Support\Expressions;

use craft\helpers\Db;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Grammar;
use Illuminate\Support\Facades\Log;
use Tpetry\QueryExpressions\Concerns\IdentifiesDriver;
use Tpetry\QueryExpressions\Concerns\StringizeExpression;
use Tpetry\QueryExpressions\Value\Value;

/**
 * Converts a UTC timestamp column/expression to a given (PHP `DateTimeZone`-style) timezone,
 * for use in date grouping/reporting. Mirrors {@see \Tpetry\QueryExpressions\Function\Time\Now}'s
 * per-driver approach, since the package itself has no timezone-conversion expression.
 */
class LocalTimestamp implements Expression
{
    use IdentifiesDriver;
    use StringizeExpression;

    public function __construct(
        private readonly string|Expression $column,
        private readonly string $timezone,
    ) {
    }

    public function getValue(Grammar $grammar): string
    {
        $column = $this->stringize($grammar, $this->column);
        $timezone = $this->stringize($grammar, new Value($this->timezone));

        return match ($this->identify($grammar)) {
            'mariadb', 'mysql' => $this->mysqlLocal($column, $timezone),
            'pgsql' => "(({$column}) at time zone 'UTC' at time zone {$timezone})",
            // SQLite has no named-timezone support (used only by the test suite, which always
            // runs in UTC); MSSQL isn't a supported driver for Commerce. Operate on the value directly.
            'sqlite', 'sqlsrv' => (string)$column,
        };
    }

    private function mysqlLocal(string $column, string $timezone): string
    {
        // The fallback if timezone conversion can't happen in SQL is simply to extract the
        // information from the UTC date stored in the column.
        if (!Db::supportsTimeZones()) {
            Log::warning('For accurate Commerce statistics it is recommend to make sure you have the timezones table populated. https://craftcms.com/knowledge-base/populating-mysql-mariadb-timezone-tables', ['category' => 'commerce']);

            return $column;
        }

        return "convert_tz({$column}, 'UTC', {$timezone})";
    }
}
