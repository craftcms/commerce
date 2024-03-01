<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\helpers\DebugPanel;
use craft\commerce\models\ShippingAddressZone;
use craft\commerce\models\ShippingRule;
use craft\commerce\models\ShippingRuleCategory;
use craft\commerce\Plugin;
use craft\commerce\records\ShippingRuleCategory as ShippingRuleCategoryRecord;
use craft\helpers\Cp;
use craft\helpers\Json;
use craft\helpers\Localization;
use craft\helpers\MoneyHelper;
use Throwable;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\base\InvalidRouteException;
use yii\db\StaleObjectException;
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
    public function actionEdit(?string $storeHandle = null, int $methodId = null, int $ruleId = null, ShippingRule $shippingRule = null): Response
    {
        if ($storeHandle === null || !$store = Plugin::getInstance()->getStores()->getStoreByHandle($storeHandle)) {
            $store = Plugin::getInstance()->getStores()->getPrimaryStore();
        }

        $variables = compact('methodId', 'ruleId', 'shippingRule');

        $plugin = Plugin::getInstance();
        $variables['shippingMethod'] = $plugin->getShippingMethods()->getShippingMethodById($variables['methodId'], $store->id);

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
                $variables['shippingRule']->methodId = $variables['shippingMethod']->id;
                $variables['shippingRule']->storeId = $variables['shippingMethod']->storeId;
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
            $this->getView()->renderTemplate('commerce/store-management/shipping/shippingzones/_fields', ['conditionField' => $conditionField])
        );
        $variables['newShippingZoneJs'] = $this->getView()->clearJsBuffer(false);
        $this->getView()->setNamespace(null);

        if (!empty($variables['ruleId'])) {
            $variables['title'] = $variables['shippingRule']->name;
        } else {
            $variables['title'] = Craft::t('commerce', 'Create a new shipping rule');
        }

        DebugPanel::prependOrAppendModelTab(model: $variables['shippingMethod'], prepend: true);
        DebugPanel::prependOrAppendModelTab(model: $variables['shippingRule'], prepend: true);

        $shippingZones = $plugin->getShippingZones()->getAllShippingZones($store->id)->all();
        $variables['shippingZones'] = [];
        $variables['shippingZones'][] = Craft::t('commerce', 'Anywhere');
        foreach ($shippingZones as $model) {
            $variables['shippingZones'][$model->id] = $model->name;
        }

        $variables['categoryShippingOptions'] = [];
        $variables['categoryShippingOptions'][] = ['label' => Craft::t('commerce', 'Allow'), 'value' => ShippingRuleCategoryRecord::CONDITION_ALLOW];
        $variables['categoryShippingOptions'][] = ['label' => Craft::t('commerce', 'Disallow'), 'value' => ShippingRuleCategoryRecord::CONDITION_DISALLOW];
        $variables['categoryShippingOptions'][] = ['label' => Craft::t('commerce', 'Require'), 'value' => ShippingRuleCategoryRecord::CONDITION_REQUIRE];

        $variables['storeId'] = $store->id;
        $variables['storeHandle'] = $store->handle;

        return $this->renderTemplate('commerce/store-management/shipping/shippingrules/_edit', $variables);
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
        $shippingRule->storeId = $this->request->getBodyParam('storeId');

        $moneyInputs = [
            'baseRate',
            'maxRate',
            'minRate',
            'perItemRate',
            'weightRate',
        ];

        foreach ($moneyInputs as $moneyInput) {
            $input = $this->request->getBodyParam($moneyInput);
            $input += [
                'currency' => $shippingRule->getStore()->getCurrency(),
            ];
            $shippingRule->$moneyInput = (float)MoneyHelper::toDecimal(MoneyHelper::toMoney($input));
        }

        $shippingRule->name = $this->request->getBodyParam('name');
        $shippingRule->description = $this->request->getBodyParam('description');
        $shippingRule->methodId = $this->request->getBodyParam('methodId');
        $shippingRule->enabled = (bool)$this->request->getBodyParam('enabled');
        $shippingRule->orderConditionFormula = trim($this->request->getBodyParam('orderConditionFormula', ''));
        $shippingRule->percentageRate = Localization::normalizeNumber($this->request->getBodyParam('percentageRate'));
        $shippingRule->setOrderCondition($this->request->getBodyParam('orderCondition'));

        $ruleCategories = [];
        $allRulesCategories = $this->request->getBodyParam('ruleCategories');
        foreach ($allRulesCategories as $key => $ruleCategory) {
            $perItemRate = $ruleCategory['perItemRate'];
            $weightRate = $ruleCategory['weightRate'];
            $percentageRate = $ruleCategory['percentageRate'];
            $ruleCategory['perItemRate'] = (!isset($perItemRate) || trim($perItemRate['value']) === '')
                ? null
                : MoneyHelper::toDecimal(MoneyHelper::toMoney(array_merge([
                    'currency' => $shippingRule->getStore()->getCurrency(),
                ], $perItemRate)));
            $ruleCategory['weightRate'] = (!isset($weightRate) || trim($weightRate['value']) === '')
                ? null
                : MoneyHelper::toDecimal(MoneyHelper::toMoney(array_merge([
                'currency' => $shippingRule->getStore()->getCurrency(),
            ], $weightRate)));
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
     * @return Response
     * @throws BadRequestHttpException
     * @throws Exception
     * @throws InvalidConfigException
     * @throws Throwable
     * @throws StaleObjectException
     */
    public function actionDelete(): Response
    {
        $this->requirePostRequest();

        if (Craft::$app->getRequest()->getIsAjax()) {
            $this->requireAcceptsJson();
        }

        if (!$id = $this->request->getRequiredBodyParam('id')) {
            throw new BadRequestHttpException('Shipping rule ID not submitted');
        }

        $rule = Plugin::getInstance()->getShippingRules()->getShippingRuleById($id);
        if (!$rule) {
            throw new Exception('Cannot find shipping rule to delete');
        }

        if (!Plugin::getInstance()->getShippingRules()->deleteShippingRuleById($id)) {
            return $this->asFailure(Craft::t('commerce', 'Could not delete shipping rule'));
        }

        if (Craft::$app->getRequest()->getIsAjax()) {
            return $this->asSuccess();
        }

        return $this->redirectToPostedUrl($rule);
    }
}
