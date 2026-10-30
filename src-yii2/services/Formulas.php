<?php

namespace craft\commerce\services;

use Twig\Error\LoaderError;
use Twig\Error\SyntaxError;
use yii\base\Component;

/**
 * @deprecated 6.0.0 use `app(\CraftCms\Commerce\Formula\Formulas::class)` instead.
 */
class Formulas extends Component
{
    public function validateConditionSyntax(string $condition, array $params): bool
    {
        return app(\CraftCms\Commerce\Formula\Formulas::class)->validateConditionSyntax($condition, $params);
    }

    public function validateFormulaSyntax(string $formula, array $params): bool
    {
        return app(\CraftCms\Commerce\Formula\Formulas::class)->validateFormulaSyntax($formula, $params);
    }

    /**
     * @throws SyntaxError
     * @throws LoaderError
     */
    public function evaluateCondition(string $formula, array $params, string $name = 'Evaluate Condition'): bool
    {
        return app(\CraftCms\Commerce\Formula\Formulas::class)->evaluateCondition($formula, $params, $name);
    }

    /**
     * @throws SyntaxError
     * @throws LoaderError
     */
    public function evaluateFormula(string $formula, array $params, ?string $setType = null, ?string $name = 'Inline formula'): mixed
    {
        return app(\CraftCms\Commerce\Formula\Formulas::class)->evaluateFormula($formula, $params, $setType, $name);
    }
}
