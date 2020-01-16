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

/**
 * Class BaseCp
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.2
 */
class FormulasController extends Controller
{
    /**
     * @return \yii\web\Response
     * @throws \yii\web\BadRequestHttpException
     */
    public function actionValidateCondition()
    {
        $this->requireAcceptsJson();
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();

        $condition = $request->getBodyParam('condition');
        $params = $request->getBodyParam('params');

        if ($condition == '') {
            return $this->asJson(['success' => true]);
        }

        if (!Plugin::getInstance()->getFormulas()->validateConditionSyntax($condition, $params)) {
            return $this->asErrorJson(Plugin::t('Invalid condition syntax'));
        }

        return $this->asJson(['success' => 'true']);
    }

    /**
     * @return \yii\web\Response
     * @throws \yii\web\BadRequestHttpException
     */
    public function actionValidateFormula()
    {
        $this->requireAcceptsJson();
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();

        $formula = $request->getBodyParam('formula');
        $params = $request->getBodyParam('params');

        if ($formula == '') {
            return $this->asJson(['success' => true]);
        }

        if (!Plugin::getInstance()->getFormulas()->validateFormulaSyntax($formula, $params)) {
            return $this->asErrorJson(Plugin::t('Invalid formula syntax'));
        }

        return $this->asJson(['success' => 'true']);
    }
}
