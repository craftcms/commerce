<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\Plugin;
use craft\web\Controller;
use yii\web\BadRequestHttpException;
use yii\web\Response;

/**
 * Class BaseCp
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.2
 */
class FormulasController extends Controller
{
    /**
     * @throws BadRequestHttpException
     */
    public function actionValidateCondition(): Response
    {
        $this->requireAcceptsJson();
        $this->requirePostRequest();

        $condition = $this->request->getBodyParam('condition');
        $params = $this->request->getBodyParam('params');

        if ($condition == '') {
            return $this->asSuccess();
        }

        if (!Plugin::getInstance()->getFormulas()->validateConditionSyntax($condition, $params)) {
            return $this->asFailure(Craft::t('commerce', 'Invalid condition syntax'));
        }

        return $this->asSuccess();
    }

    /**
     * @throws BadRequestHttpException
     */
    public function actionValidateFormula(): Response
    {
        $this->requireAcceptsJson();
        $this->requirePostRequest();

        $formula = $this->request->getBodyParam('formula');
        $params = $this->request->getBodyParam('params');

        if ($formula == '') {
            return $this->asSuccess();
        }

        if (!Plugin::getInstance()->getFormulas()->validateFormulaSyntax($formula, $params)) {
            return $this->asFailure(Craft::t('commerce', 'Invalid formula syntax'));
        }

        return $this->asSuccess();
    }
}
