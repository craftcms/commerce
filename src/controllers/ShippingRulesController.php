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
    // Public Methods
    // =========================================================================

    /**
     * @return Response
     */
    public function actionIndex(): Response
    {
        $methodsExist = Plugin::getInstance()->getShippingMethods()->ShippingMethodExists();
        $shippingRules = Plugin::getInstance()->getShippingRules()->getAllShippingRules();
        return $this->renderTemplate('commerce/settings/shippingrules/index', compact('shippingRules', 'methodsExist'));
    }

    /**
     * @param int|null $methodId
     * @param int|null $ruleId
     * @param ShippingRule|null $shippingRule
     * @return Response
     * @throws HttpException
     */
    public function actionEdit(int $methodId = null, int $ruleId = null, ShippingRule $shippingRule = null): Response
    {
        $variables = [
            'methodId' => $methodId,
            'ruleId' => $ruleId,
            'shippingRule' => $shippingRule,
        ];

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

        $variables['countries'] = ['' => ''] + $plugin->getCountries()->getAllCountriesAsList();
        $variables['states'] = $plugin->getStates()->getAllStatesAsList();

        $this->getView()->setNamespace('new');

        $this->getView()->startJsBuffer();
        $countries = $plugin->getCountries()->getAllCountries();
        $states = $plugin->getStates()->getAllStates();
        $variables['newShippingZoneFields'] = $this->getView()->namespaceInputs(
            $this->getView()->renderTemplate('commerce/settings/shippingzones/_fields', [
                'countries' => ArrayHelper::map($countries, 'id', 'name'),
                'states' => ArrayHelper::map($states, 'id', 'name'),
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
        $variables['shippingZones'][] = 'Anywhere';
        foreach ($shippingZones as $model) {
            $variables['shippingZones'][$model->id] = $model->name;
        }

        $variables['categoryShippingOptions'] = [];
        $variables['categoryShippingOptions'][] = ['label' => Craft::t('commerce', 'Allow'), 'value' => ShippingRuleCategory::CONDITION_ALLOW];
        $variables['categoryShippingOptions'][] = ['label' => Craft::t('commerce', 'Disallow'), 'value' => ShippingRuleCategory::CONDITION_DISALLOW];
        $variables['categoryShippingOptions'][] = ['label' => Craft::t('commerce', 'Require'), 'value' => ShippingRuleCategory::CONDITION_REQUIRE];

        return $this->renderTemplate('commerce/settings/shippingrules/_edit', $variables);
    }

    /**
     * @throws HttpException
     */
    public function actionSave()
    {
        $this->requirePostRequest();

        $shippingRule = new ShippingRule();

        // Shared attributes
        $fields = [
            'id', 'name', 'description', 'shippingZoneId', 'methodId', 'enabled', 'minQty', 'maxQty', 'minTotal', 'maxTotal',
            'minWeight', 'maxWeight', 'baseRate', 'perItemRate', 'weightRate', 'percentageRate', 'minRate', 'maxRate'
        ];
        foreach ($fields as $field) {
            $shippingRule->$field = Craft::$app->getRequest()->getBodyParam($field);
        }

        $ruleCategories = [];
        $allRulesCategories = Craft::$app->getRequest()->getBodyParam('ruleCategories');
        foreach ($allRulesCategories as $key => $ruleCategory) {
            $ruleCategories[$key] = new ShippingRuleCategory($ruleCategory);
            $ruleCategories[$key]->shippingCategoryId = $key;
        }

        $shippingRule->setShippingRuleCategories($ruleCategories);

        // Save it
        if (Plugin::getInstance()->getShippingRules()->saveShippingRule($shippingRule)) {
            Craft::$app->getSession()->setNotice(Craft::t('commerce', 'Shipping rule saved.'));
            $this->redirectToPostedUrl($shippingRule);
        } else {
            Craft::$app->getSession()->setError(Craft::t('commerce', 'Couldn’t save shipping rule.'));
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

        return $this->asErrorJson(Craft::t('commerce', 'Could not delete shipping rule'));
    }
}
