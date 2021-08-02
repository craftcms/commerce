<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\models\ShippingRule;
use craft\commerce\models\ShippingRuleCategory;
use craft\commerce\Plugin;
use craft\commerce\records\ShippingRuleCategory as ShippingRuleCategoryRecord;
use craft\errors\ProductTypeNotFoundException;
use craft\helpers\Json;
use craft\helpers\Localization;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use yii\base\Exception;
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
     * @return Response
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

        $variables['newShippingZoneFields'] = $this->getView()->namespaceInputs(
            $this->getView()->renderTemplate('commerce/shipping/shippingzones/_fields', [
                'countries' => $plugin->getCountries()->getAllEnabledCountriesAsList(),
                'states' => $plugin->getStates()->getAllEnabledStatesAsList(),
            ])
        );
        $variables['newShippingZoneJs'] = $this->getView()->clearJsBuffer(false);

        if (!empty($variables['ruleId'])) {
            $variables['title'] = $variables['shippingRule']->name;
        } else {
            $variables['title'] = Craft::t('commerce', 'Create a new shipping rule');
        }

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

        if ($variables['shippingRule'] && $variables['shippingRule'] instanceof ShippingRule) {
            $categoryModels = $variables['shippingRule']->getShippingRuleCategories();
            // Localize numbers
            $localizeAttributes = [
                'minTotal',
                'maxTotal',
                'minWeight',
                'maxWeight',
                'baseRate',
                'perItemRate',
                'weightRate',
                'percentageRate',
                'minRate',
                'maxRate',
            ];

            foreach ($localizeAttributes as $attr) {
                if (isset($variables['shippingRule']->{$attr}) && $variables['shippingRule']->{$attr} !== null) {
                    $variables['shippingRule']->{$attr} = Craft::$app->getFormatter()->asDecimal((float)$variables['shippingRule']->{$attr});
                }

                if (!empty($categoryModels)) {
                    foreach ($categoryModels as &$categoryModel) {
                        if (isset($categoryModel->{$attr}) && $categoryModel->{$attr} !== null) {
                            $categoryModel->{$attr} = Craft::$app->getFormatter()->asDecimal((float)$categoryModel->{$attr});
                        }
                    }
                }
            }

            $variables['shippingRule']->setShippingRuleCategories($categoryModels);
        }

        return $this->renderTemplate('commerce/shipping/shippingrules/_edit', $variables);
    }

    /**
     * Duplicates a shipping rule.
     *
     * @return Response|null
     * @throws InvalidRouteException
     * @since 3.2
     */
    public function actionDuplicate(): ?Response
    {
        return $this->runAction('save', ['duplicate' => true]);
    }

    /**
     * @param bool $duplicate
     * @throws BadRequestHttpException
     * @throws Exception
     */
    public function actionSave(bool $duplicate = false): void
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();

        $shippingRule = new ShippingRule();

        if (!$duplicate) {
            $shippingRule->id = $request->getBodyParam('id');
        }

        $shippingRule->name = $request->getBodyParam('name');
        $shippingRule->description = $request->getBodyParam('description');
        $shippingRule->shippingZoneId = $request->getBodyParam('shippingZoneId');
        $shippingRule->methodId = $request->getBodyParam('methodId');
        $shippingRule->enabled = (bool)$request->getBodyParam('enabled');
        $shippingRule->orderConditionFormula = trim($request->getBodyParam('orderConditionFormula', ''));
        $shippingRule->minQty = $request->getBodyParam('minQty');
        $shippingRule->maxQty = $request->getBodyParam('maxQty');
        $shippingRule->minTotal = Localization::normalizeNumber($request->getBodyParam('minTotal'));
        $shippingRule->maxTotal = Localization::normalizeNumber($request->getBodyParam('maxTotal'));
        $shippingRule->minMaxTotalType = $request->getBodyParam('minMaxTotalType');
        $shippingRule->minWeight = Localization::normalizeNumber($request->getBodyParam('minWeight'));
        $shippingRule->maxWeight = Localization::normalizeNumber($request->getBodyParam('maxWeight'));
        $shippingRule->baseRate = Localization::normalizeNumber($request->getBodyParam('baseRate'));
        $shippingRule->perItemRate = Localization::normalizeNumber($request->getBodyParam('perItemRate'));
        $shippingRule->weightRate = Localization::normalizeNumber($request->getBodyParam('weightRate'));
        $shippingRule->percentageRate = Localization::normalizeNumber($request->getBodyParam('percentageRate'));
        $shippingRule->minRate = Localization::normalizeNumber($request->getBodyParam('minRate'));
        $shippingRule->maxRate = Localization::normalizeNumber($request->getBodyParam('maxRate'));

        $ruleCategories = [];
        $allRulesCategories = Craft::$app->getRequest()->getBodyParam('ruleCategories');
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
     * @return null|Response
     * @throws HttpException
     */
    public function actionReorder(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $ids = Json::decode(Craft::$app->getRequest()->getRequiredBodyParam('ids'));
        $success = Plugin::getInstance()->getShippingRules()->reorderShippingRules($ids);

        return $this->asJson(['success' => $success]);
    }

    /**
     * @throws HttpException
     * @throws ProductTypeNotFoundException
     */
    public function actionDelete(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        if (!$id = Craft::$app->getRequest()->getRequiredBodyParam('id')) {
            throw new BadRequestHttpException('Product Type ID not submitted');
        }


        if (Plugin::getInstance()->getShippingRules()->getShippingRuleById($id)) {
            throw new ProductTypeNotFoundException('Can not find product type to delete');
        }

        $deleted = Plugin::getInstance()->getShippingRules()->deleteShippingRuleById($id);

        if ($deleted) {
            return $this->asJson(['success' => true]);
        }

        return $this->asErrorJson(Craft::t('commerce', 'Could not delete shipping rule'));
    }
}
