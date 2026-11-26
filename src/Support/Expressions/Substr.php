<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Support\Expressions;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Grammar;
use Tpetry\QueryExpressions\Concerns\StringizeExpression;

/**
 * The `substr(string, start, length)` function syntax is identical across MySQL/MariaDB, PostgreSQL,
 * and SQLite, so no per-driver branching is needed.
 */
class Substr implements Expression
{
    use StringizeExpression;

    public function __construct(
        private readonly string|Expression $expression,
        private readonly int $start,
        private readonly int $length,
    ) {
    }

    public function getValue(Grammar $grammar): string
    {
        return 'substr(' . $this->stringize($grammar, $this->expression) . ", {$this->start}, {$this->length})";
    }
}
