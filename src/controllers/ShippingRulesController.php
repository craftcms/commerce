<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\errors\ProductTypeNotFoundException;
use craft\commerce\helpers\DebugPanel;
use craft\commerce\models\ShippingAddressZone;
use craft\commerce\models\ShippingRule;
use craft\commerce\models\ShippingRuleCategory;
use craft\commerce\Plugin;
use craft\commerce\records\ShippingRuleCategory as ShippingRuleCategoryRecord;
use craft\helpers\Cp;
use craft\helpers\Json;
use craft\helpers\Localization;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\base\InvalidRouteException;
use yii\web\BadRequestHttpException;
use yii\web\HttpException;
use yii\web\Response;

/**
 * Class Shipping Rules Controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class ShippingRulesController extends BaseShippingSettingsController
{
    /**
     * @param int|null $methodId
     * @param int|null $ruleId
     * @param ShippingRule|null $shippingRule
     * @throws HttpException
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws Exception
     */
    public function actionEdit(int $methodId = null, int $ruleId = null, ShippingRule $shippingRule = null): Response
    {
        $variables = compact('methodId', 'ruleId', 'shippingRule');

        $plugin = Plugin::getInstance();
        $variables['shippingMethod'] = $plugin->getShippingMethods()->getShippingMethodById($variables['methodId']);

        if (!$variables['shippingMethod']) {
            throw new HttpException(404);
        }

        if (!$variables['shippingRule']) {
            if ($variables['ruleId']) {
                $variables['shippingRule'] = $plugin->getShippingRules()->getShippingRuleById($variables['ruleId']);

                if (!$variables['shippingRule']) {
                    throw new HttpException(404);
                }
            } else {
                $variables['shippingRule'] = new ShippingRule();
            }
        }

        $this->getView()->setNamespace('new');

        $this->getView()->startJsBuffer();

        $newZone = new ShippingAddressZone();
        $condition = $newZone->getCondition();
        $condition->mainTag = 'div';
        $condition->name = 'condition';
        $condition->id = 'condition';
        $conditionField = Cp::fieldHtml($condition->getBuilderHtml(), [
            'label' => Craft::t('app', 'Address Condition'),
        ]);

        $variables['newShippingZoneFields'] = $this->getView()->namespaceInputs(
            $this->getView()->renderTemplate('commerce/shipping/shippingzones/_fields', ['conditionField' => $conditionField])
        );
        $variables['newShippingZoneJs'] = $this->getView()->clearJsBuffer(false);

        if (!empty($variables['ruleId'])) {
            $variables['title'] = $variables['shippingRule']->name;
        } else {
            $variables['title'] = Craft::t('commerce', 'Create a new shipping rule');
        }

        DebugPanel::prependOrAppendModelTab(model: $variables['shippingMethod'], prepend: true);
        DebugPanel::prependOrAppendModelTab(model: $variables['shippingRule'], prepend: true);

        $shippingZones = $plugin->getShippingZones()->getAllShippingZones();
        $variables['shippingZones'] = [];
        $variables['shippingZones'][] = Craft::t('commerce', 'Anywhere');
        foreach ($shippingZones as $model) {
            $variables['shippingZones'][$model->id] = $model->name;
        }

        $variables['categoryShippingOptions'] = [];
        $variables['categoryShippingOptions'][] = ['label' => Craft::t('commerce', 'Allow'), 'value' => ShippingRuleCategoryRecord::CONDITION_ALLOW];
        $variables['categoryShippingOptions'][] = ['label' => Craft::t('commerce', 'Disallow'), 'value' => ShippingRuleCategoryRecord::CONDITION_DISALLOW];
        $variables['categoryShippingOptions'][] = ['label' => Craft::t('commerce', 'Require'), 'value' => ShippingRuleCategoryRecord::CONDITION_REQUIRE];

        return $this->renderTemplate('commerce/shipping/shippingrules/_edit', $variables);
    }

    /**
     * Duplicates a shipping rule.
     *
     * @throws InvalidRouteException
     * @since 3.2
     */
    public function actionDuplicate(): ?Response
    {
        return $this->runAction('save', ['duplicate' => true]);
    }

    /**
     * @throws BadRequestHttpException
     * @throws Exception
     */
    public function actionSave(bool $duplicate = false): void
    {
        $this->requirePostRequest();

        $shippingRule = new ShippingRule();

        if (!$duplicate) {
            $shippingRule->id = $this->request->getBodyParam('id');
        }

        $shippingRule->name = $this->request->getBodyParam('name');
        $shippingRule->description = $this->request->getBodyParam('description');
        $shippingRule->shippingZoneId = $this->request->getBodyParam('shippingZoneId');
        $shippingRule->methodId = $this->request->getBodyParam('methodId');
        $shippingRule->enabled = (bool)$this->request->getBodyParam('enabled');
        $shippingRule->orderConditionFormula = trim($this->request->getBodyParam('orderConditionFormula', ''));
        $shippingRule->minQty = $this->request->getBodyParam('minQty');
        $shippingRule->maxQty = $this->request->getBodyParam('maxQty');
        $shippingRule->minTotal = Localization::normalizeNumber($this->request->getBodyParam('minTotal'));
        $shippingRule->maxTotal = Localization::normalizeNumber($this->request->getBodyParam('maxTotal'));
        $shippingRule->minMaxTotalType = $this->request->getBodyParam('minMaxTotalType');
        $shippingRule->minWeight = Localization::normalizeNumber($this->request->getBodyParam('minWeight'));
        $shippingRule->maxWeight = Localization::normalizeNumber($this->request->getBodyParam('maxWeight'));
        $shippingRule->baseRate = Localization::normalizeNumber($this->request->getBodyParam('baseRate'));
        $shippingRule->perItemRate = Localization::normalizeNumber($this->request->getBodyParam('perItemRate'));
        $shippingRule->weightRate = Localization::normalizeNumber($this->request->getBodyParam('weightRate'));
        $shippingRule->percentageRate = Localization::normalizeNumber($this->request->getBodyParam('percentageRate'));
        $shippingRule->minRate = Localization::normalizeNumber($this->request->getBodyParam('minRate'));
        $shippingRule->maxRate = Localization::normalizeNumber($this->request->getBodyParam('maxRate'));

        $ruleCategories = [];
        $allRulesCategories = $this->request->getBodyParam('ruleCategories');
        foreach ($allRulesCategories as $key => $ruleCategory) {
            $perItemRate = $ruleCategory['perItemRate'];
            $weightRate = $ruleCategory['weightRate'];
            $percentageRate = $ruleCategory['percentageRate'];
            $ruleCategory['perItemRate'] = (!isset($perItemRate) || trim($perItemRate) === '') ? null : Localization::normalizeNumber($perItemRate);
            $ruleCategory['weightRate'] = (!isset($weightRate) || trim($weightRate) === '') ? null : Localization::normalizeNumber($weightRate);
            $ruleCategory['percentageRate'] = (!isset($percentageRate) || trim($percentageRate) === '') ? null : Localization::normalizeNumber($percentageRate);

            $ruleCategories[$key] = new ShippingRuleCategory($ruleCategory);
            $ruleCategories[$key]->shippingCategoryId = $key;
        }

        $shippingRule->setShippingRuleCategories($ruleCategories);

        // Save it
        if (Plugin::getInstance()->getShippingRules()->saveShippingRule($shippingRule)) {
            $this->setSuccessFlash(Craft::t('commerce', 'Shipping rule saved.'));
            $this->redirectToPostedUrl($shippingRule);
        } else {
            $this->setFailFlash(Craft::t('commerce', 'Couldn’t save shipping rule.'));
        }

        // Send the model back to the template
        Craft::$app->getUrlManager()->setRouteParams(['shippingRule' => $shippingRule]);
    }

    /**
     * @return Response
     * @throws BadRequestHttpException
     * @throws InvalidConfigException
     * @throws \yii\db\Exception
     */
    public function actionReorder(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $ids = Json::decode($this->request->getRequiredBodyParam('ids'));
        Plugin::getInstance()->getShippingRules()->reorderShippingRules($ids);

        return $this->asSuccess();
    }

    /**
     * @throws HttpException
     * @throws ProductTypeNotFoundException
     */
    public function actionDelete(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        if (!$id = $this->request->getRequiredBodyParam('id')) {
            throw new BadRequestHttpException('Shipping rule ID not submitted');
        }

        if (!Plugin::getInstance()->getShippingRules()->getShippingRuleById($id)) {
            throw new ProductTypeNotFoundException('Can not find shipping rule to delete');
        }

        if (!Plugin::getInstance()->getShippingRules()->deleteShippingRuleById($id)) {
            return $this->asFailure(Craft::t('commerce', 'Could not delete shipping rule'));
        }

        return $this->asSuccess();
    }
}
