<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Support\Expressions;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Grammar;
use Tpetry\QueryExpressions\Concerns\StringizeExpression;

/**
 * The `round(number, decimals)` function syntax is identical across MySQL/MariaDB, PostgreSQL,
 * and SQLite, so no per-driver branching is needed.
 */
class Round implements Expression
{
    use StringizeExpression;

    public function __construct(
        private readonly string|Expression $expression,
        private readonly int $decimals = 0,
    ) {
    }

    public function getValue(Grammar $grammar): string
    {
        return 'round(' . $this->stringize($grammar, $this->expression) . ", {$this->decimals})";
    }
}
