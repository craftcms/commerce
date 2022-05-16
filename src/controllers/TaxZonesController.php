<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\helpers\DebugPanel;
use craft\commerce\models\TaxAddressZone;
use craft\commerce\Plugin;
use craft\helpers\Cp;
use Twig\Error\LoaderError;
use Twig\Error\SyntaxError;
use yii\base\Exception;
use yii\web\BadRequestHttpException;
use yii\web\HttpException;
use yii\web\Response;

/**
 * Class Tax Zone Controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class TaxZonesController extends BaseTaxSettingsController
{
    public function actionIndex(): Response
    {
        $taxZones = Plugin::getInstance()->getTaxZones()->getAllTaxZones();
        return $this->renderTemplate('commerce/tax/taxzones/index', compact('taxZones'));
    }

    /**
     * @param int|null $id
     * @param TaxAddressZone|null $taxZone
     * @throws HttpException
     */
    public function actionEdit(int $id = null, TaxAddressZone $taxZone = null): Response
    {
        $variables = compact('id', 'taxZone');

        if (!$variables['taxZone']) {
            if ($variables['id']) {
                $variables['taxZone'] = Plugin::getInstance()->getTaxZones()->getTaxZoneById($variables['id']);

                if (!$variables['taxZone']) {
                    throw new HttpException(404);
                }
            } else {
                $variables['taxZone'] = new TaxAddressZone();
            }
        }

        if ($variables['taxZone']->id) {
            $variables['title'] = $variables['taxZone']->name;
        } else {
            $variables['title'] = Craft::t('commerce', 'Create a tax zone');
        }

        $condition = $variables['taxZone']->getCondition();
        $condition->mainTag = 'div';
        $condition->name = 'condition';
        $condition->id = 'condition';
        $variables['conditionField'] = Cp::fieldHtml($condition->getBuilderHtml(), [
            'label' => Craft::t('app', 'Address Condition'),
        ]);

        DebugPanel::prependOrAppendModelTab(model: $variables['taxZone'], prepend: true);

        return $this->renderTemplate('commerce/tax/taxzones/_edit', $variables);
    }

    /**
     * @throws Exception
     * @throws BadRequestHttpException
     */
    public function actionSave(): ?Response
    {
        $this->requirePostRequest();

        $taxZone = new TaxAddressZone();

        $taxZone->id = $this->request->getBodyParam('taxZoneId');
        $taxZone->name = $this->request->getBodyParam('name');
        $taxZone->description = $this->request->getBodyParam('description');
        $taxZone->default = (bool)$this->request->getBodyParam('default');
        $taxZone->setCondition($this->request->getBodyParam('condition'));

        if ($taxZone->validate() && Plugin::getInstance()->getTaxZones()->saveTaxZone($taxZone)) {
            return $this->asModelSuccess(
                $taxZone,
                Craft::t('commerce', 'Tax zone saved.'),
                'taxZone',
                data: [
                    'id' => $taxZone->id,
                    'name' => $taxZone->name,
                ]
            );
        }

        return $this->asModelFailure(
            $taxZone,
            Craft::t('commerce', 'Couldn’t save tax zone.'),
            'taxZone'
        );
    }

    /**
     * @throws HttpException
     */
    public function actionDelete(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $id = $this->request->getRequiredBodyParam('id');

        Plugin::getInstance()->getTaxZones()->deleteTaxZoneById($id);
        return $this->asSuccess();
    }

    /**
     * @throws BadRequestHttpException
     * @throws LoaderError
     * @throws SyntaxError
     * @since 2.2
     */
    public function actionTestZip(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $zipCodeFormula = (string)$this->request->getRequiredBodyParam('zipCodeConditionFormula');
        $testZipCode = (string)$this->request->getRequiredBodyParam('testZipCode');

        $params = ['zipCode' => $testZipCode];
        if (!Plugin::getInstance()->getFormulas()->evaluateCondition($zipCodeFormula, $params)) {
            return $this->asFailure('failed');
        }

        return $this->asSuccess();
    }
}
