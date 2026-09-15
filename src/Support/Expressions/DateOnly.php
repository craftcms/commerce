<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Support\Expressions;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Grammar;
use Tpetry\QueryExpressions\Concerns\StringizeExpression;

/**
 * Truncates a timestamp expression down to its date part. The `date(...)` function syntax is
 * identical across MySQL/MariaDB, PostgreSQL, and SQLite, so no per-driver branching is needed.
 */
class DateOnly implements Expression
{
    use StringizeExpression;

    public function __construct(
        private readonly string|Expression $column,
    ) {
    }

    public function getValue(Grammar $grammar): string
    {
        return 'date(' . $this->stringize($grammar, $this->column) . ')';
    }
}
