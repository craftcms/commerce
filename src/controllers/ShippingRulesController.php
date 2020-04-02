<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\models\ShippingRule;
use craft\commerce\Plugin;
use craft\commerce\records\ShippingRuleCategory;
use craft\helpers\ArrayHelper;
use craft\helpers\Json;
use craft\helpers\Localization;
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

        // TODO: check if the following two lines can be removed
        // $variables['countries'] = ['' => ''] + $plugin->getCountries()->getAllCountriesAsList();
        // $variables['states'] = $plugin->getStates()->getAllStatesAsList();

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
            $variables['title'] = Plugin::t('Create a new shipping rule');
        }

        $shippingZones = $plugin->getShippingZones()->getAllShippingZones();
        $variables['shippingZones'] = [];
        $variables['shippingZones'][] = 'Anywhere';
        foreach ($shippingZones as $model) {
            $variables['shippingZones'][$model->id] = $model->name;
        }

        $variables['categoryShippingOptions'] = [];
        $variables['categoryShippingOptions'][] = ['label' => Plugin::t('Allow'), 'value' => ShippingRuleCategory::CONDITION_ALLOW];
        $variables['categoryShippingOptions'][] = ['label' => Plugin::t('Disallow'), 'value' => ShippingRuleCategory::CONDITION_DISALLOW];
        $variables['categoryShippingOptions'][] = ['label' => Plugin::t('Require'), 'value' => ShippingRuleCategory::CONDITION_REQUIRE];

        if ($variables['shippingRule'] && $variables['shippingRule'] instanceof ShippingRule) {
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
            }
        }

        return $this->renderTemplate('commerce/shipping/shippingrules/_edit', $variables);
    }

    /**
     * @throws HttpException
     */
    public function actionSave()
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();

        $shippingRule = new ShippingRule();

        $shippingRule->id = $request->getBodyParam('id');
        $shippingRule->name = $request->getBodyParam('name');
        $shippingRule->description = $request->getBodyParam('description');
        $shippingRule->shippingZoneId = $request->getBodyParam('shippingZoneId');
        $shippingRule->methodId = $request->getBodyParam('methodId');
        $shippingRule->enabled = (bool)$request->getBodyParam('enabled');
        $shippingRule->minQty = $request->getBodyParam('minQty');
        $shippingRule->maxQty = $request->getBodyParam('maxQty');
        $shippingRule->minTotal = Localization::normalizeNumber($request->getBodyParam('minTotal'));
        $shippingRule->maxTotal = Localization::normalizeNumber($request->getBodyParam('maxTotal'));
        $shippingRule->minWeight = Localization::normalizeNumber($request->getBodyParam('minWeight'));
        $shippingRule->maxWeight = Localization::normalizeNumber($request->getBodyParam('maxWeight'));
        $shippingRule->baseRate = Localization::normalizeNumber($request->getBodyParam('baseRate'));
        $shippingRule->perItemRate = Localization::normalizeNumber($request->getBodyParam('perItemRate'));
        $shippingRule->weightRate = Localization::normalizeNumber($request->getBodyParam('weightRate'));
        $shippingRule->percentageRate = Localization::normalizeNumber($request->getBodyParam('percentageRate'));
        $shippingRule->minRate = Localization::normalizeNumber( $request->getBodyParam('minRate'));
        $shippingRule->maxRate = Localization::normalizeNumber($request->getBodyParam('maxRate'));

        $ruleCategories = [];
        $allRulesCategories = Craft::$app->getRequest()->getBodyParam('ruleCategories');
        foreach ($allRulesCategories as $key => $ruleCategory) {
            $ruleCategories[$key] = new ShippingRuleCategory($ruleCategory);
            $ruleCategories[$key]->shippingCategoryId = $key;
        }

        $shippingRule->setShippingRuleCategories($ruleCategories);

        // Save it
        if (Plugin::getInstance()->getShippingRules()->saveShippingRule($shippingRule)) {
            Craft::$app->getSession()->setNotice(Plugin::t('Shipping rule saved.'));
            $this->redirectToPostedUrl($shippingRule);
        } else {
            Craft::$app->getSession()->setError(Plugin::t('Couldn’t save shipping rule.'));
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
     */
    public function actionDelete(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $id = Craft::$app->getRequest()->getRequiredBodyParam('id');

        if (Plugin::getInstance()->getShippingRules()->deleteShippingRuleById($id)) {
            return $this->asJson(['success' => true]);
        }

        return $this->asErrorJson(Plugin::t('Could not delete shipping rule'));
    }
}
