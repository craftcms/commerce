<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Support\Expressions;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Grammar;
use Tpetry\QueryExpressions\Concerns\IdentifiesDriver;

/**
 * Generates a random UUID per row. {@see \Tpetry\QueryExpressions\Function\String\Uuid4} isn't used
 * because it relies on `random_bytes()` on MySQL/MariaDB, which MariaDB only added in 10.10.
 */
class Uuid implements Expression
{
    use IdentifiesDriver;

    public function getValue(Grammar $grammar): string
    {
        return match ($this->identify($grammar)) {
            'pgsql' => 'gen_random_uuid()',
            // SQLite (test suite only) has no UUID builtin; assemble a v4-shaped one from random blobs.
            'sqlite' => "(lower(hex(randomblob(4))) || '-' || lower(hex(randomblob(2))) || '-4' || substr(lower(hex(randomblob(2))), 2) || '-' || substr('89ab', abs(random()) % 4 + 1, 1) || substr(lower(hex(randomblob(2))), 2) || '-' || lower(hex(randomblob(6))))",
            default => 'UUID()',
        };
    }
}
