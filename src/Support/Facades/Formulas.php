<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Support\Facades;

use Illuminate\Support\Facades\Facade;
use Override;

/**
 * @method static bool validateConditionSyntax(string $condition, array $params)
 * @method static bool validateFormulaSyntax(string $formula, array $params)
 * @method static bool evaluateCondition(string $formula, array $params, string $name = 'Evaluate Condition')
 * @method static mixed evaluateFormula(string $formula, array $params, string|null $setType = null, string|null $name = 'Inline formula')
 *
 * @see \CraftCms\Commerce\Formula\Formulas
 */
class Formulas extends Facade
{
    #[Override]
    protected static function getFacadeAccessor(): string
    {
        return \CraftCms\Commerce\Formula\Formulas::class;
    }
}
