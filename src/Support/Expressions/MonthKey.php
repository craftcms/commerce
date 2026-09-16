<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Support\Expressions;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Grammar;
use Tpetry\QueryExpressions\Concerns\IdentifiesDriver;
use Tpetry\QueryExpressions\Concerns\StringizeExpression;

/**
 * Produces a `{year}-{month}` grouping key (unpadded month, matching PHP's `Y-n` date format)
 * for a timestamp expression. Mirrors {@see \Tpetry\QueryExpressions\Function\Time\ExtractDatePart},
 * which only supports extracting the year, not the month.
 *
 * Uses each driver's `concat()`-style function rather than the `||` operator: PostgreSQL's `||`
 * requires matching/castable operand types, and `extract(month from ...)` returns `numeric`, which
 * `||` can't implicitly concatenate with a string literal.
 */
class MonthKey implements Expression
{
    use IdentifiesDriver;
    use StringizeExpression;

    public function __construct(
        private readonly string|Expression $column,
    ) {
    }

    public function getValue(Grammar $grammar): string
    {
        $column = $this->stringize($grammar, $this->column);

        return match ($this->identify($grammar)) {
            'mariadb', 'mysql', 'sqlsrv' => "concat(year({$column}), '-', month({$column}))",
            'pgsql' => "concat(extract(year from {$column}), '-', extract(month from {$column}))",
            // strftime('%m', ...) is zero-padded; cast to int so the SQL-generated key matches
            // PHP's `Y-n` dateKeyFormat (unpadded month) used to merge results.
            'sqlite' => "(strftime('%Y', {$column}) || '-' || cast(strftime('%m', {$column}) as integer))",
        };
    }
}
