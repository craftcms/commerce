<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers;

use craft\commerce\Plugin;
use CraftCms\Cms\Http\RespondsWithFlash;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

use function CraftCms\Cms\t;

readonly class FormulasController
{
    use RespondsWithFlash;

    public function validateCondition(Request $request): Response
    {
        abort_unless($request->expectsJson(), 400);

        $condition = $request->input('condition');
        $params = $request->input('params');

        if ($condition == '') {
            return $this->asSuccess();
        }

        if (!Plugin::getInstance()->getFormulas()->validateConditionSyntax($condition, $params)) {
            return $this->asFailure(t('Invalid condition syntax', category: 'commerce'));
        }

        return $this->asSuccess();
    }

    public function validateFormula(Request $request): Response
    {
        abort_unless($request->expectsJson(), 400);

        $formula = $request->input('formula');
        $params = $request->input('params');

        if ($formula == '') {
            return $this->asSuccess();
        }

        if (!Plugin::getInstance()->getFormulas()->validateFormulaSyntax($formula, $params)) {
            return $this->asFailure(t('Invalid formula syntax', category: 'commerce'));
        }

        return $this->asSuccess();
    }
}
