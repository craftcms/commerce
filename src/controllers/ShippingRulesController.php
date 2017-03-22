<?php
namespace craft\commerce\controllers;

use craft\commerce\models\ShippingRule;
use craft\commerce\Plugin;
use craft\commerce\records\ShippingRuleCategory;
use craft\helpers\ArrayHelper;

/**
 * Class Shipping Rules Controller
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.controllers
 * @since     1.0
 */
class ShippingRulesController extends BaseAdminController
{
    /**
     * @throws HttpException
     */
    public function actionIndex()
    {
        if (!Craft::$app->getUser()->getUser()->can('manageCommerce')) {
            throw new HttpException(403, Craft::t('commerce', 'This action is not allowed for the current user.'));
        }

        $methodsExist = Plugin::getInstance()->getShippingMethods()->ShippingMethodExists();
        $shippingRules = Plugin::getInstance()->getShippingRules()->getAllShippingRules([
            'order' => 't.methodId, t.name',
            'with' => ['method', 'country', 'state'],
        ]);
        $this->renderTemplate('commerce/settings/shippingrules/index', compact('shippingRules', 'methodsExist'));
    }

    /**
     * Create/Edit Shipping Rule
     *
     * @param array $variables
     *
     * @throws HttpException
     */
    public function actionEdit(array $variables = [])
    {
        if (!Craft::$app->getUser()->getUser()->can('manageCommerce')) {
            throw new HttpException(403, Craft::t('commerce', 'This action is not allowed for the current user.'));
        }

        if (empty($variables['methodId'])) {
            throw new HttpException(404);
        }

        $variables['shippingMethod'] = Plugin::getInstance()->getShippingMethods()->getShippingMethodById($variables['methodId']);

        if (empty($variables['shippingMethod'])) {
            throw new HttpException(404);
        }

        if (empty($variables['shippingRule'])) {
            if (!empty($variables['ruleId'])) {
                $id = $variables['ruleId'];
                $variables['shippingRule'] = Plugin::getInstance()->getShippingRules()->getShippingRuleById($id);

                if (!$variables['shippingRule']) {
                    throw new HttpException(404);
                }
            } else {
                $variables['shippingRule'] = new ShippingRule();
            }
        }

        $variables['countries'] = ['' => ''] + Plugin::getInstance()->getCountries()->getAllCountriesListData();
        $variables['states'] = Plugin::getInstance()->getStates()->getStatesGroupedByCountries();

        Craft::$app->getView()->setNamespace('new');

        Craft::$app->getView()->startJsBuffer();
        $countries = Plugin::getInstance()->getCountries()->getAllCountries();
        $states = Plugin::getInstance()->getStates()->getAllStates();
        $variables['newShippingZoneFields'] = Craft::$app->getView()->namespaceInputs(
            Craft::$app->getView()->render('commerce/settings/shippingzones/_fields', [
                'countries' => ArrayHelper::map($countries, 'id', 'name'),
                'states' => ArrayHelper::map($states, 'id', 'name'),
            ])
        );
        $variables['newShippingZoneJs'] = Craft::$app->getView()->clearJsBuffer(false);

        if (!empty($variables['ruleId'])) {
            $variables['title'] = $variables['shippingRule']->name;
        } else {
            $variables['title'] = Craft::t('commerce', 'Create a new shipping rule');
        }

        $shippingZones = Plugin::getInstance()->getShippingZones()->getAllShippingZones(false);
        $variables['shippingZones'] = [];
        $variables['shippingZones'][] = "Anywhere";
        foreach ($shippingZones as $model) {
            $variables['shippingZones'][$model->id] = $model->name;
        }

        $variables['categoryShippingOptions'] = [];
        $variables['categoryShippingOptions'][] = ['label' => Craft::t('commerce', 'Allow'), 'value' => ShippingRuleCategory::CONDITION_ALLOW];
        $variables['categoryShippingOptions'][] = ['label' => Craft::t('commerce', 'Disallow'), 'value' => ShippingRuleCategory::CONDITION_DISALLOW];
        $variables['categoryShippingOptions'][] = ['label' => Craft::t('commerce', 'Require'), 'value' => ShippingRuleCategory::CONDITION_REQUIRE];


        $this->renderTemplate('commerce/settings/shippingrules/_edit', $variables);
    }

    /**
     * @throws HttpException
     */
    public function actionSave()
    {
        if (!Craft::$app->getUser()->getUser()->can('manageCommerce')) {
            throw new HttpException(403, Craft::t('commerce', 'This action is not allowed for the current user.'));
        }

        $this->requirePostRequest();

        $shippingRule = new ShippingRule();

        // Shared attributes
        $fields = [
            'id', 'name', 'description', 'shippingZoneId', 'methodId', 'enabled', 'minQty', 'maxQty', 'minTotal', 'maxTotal',
            'minWeight', 'maxWeight', 'baseRate', 'perItemRate', 'weightRate', 'percentageRate', 'minRate', 'maxRate'
        ];
        foreach ($fields as $field) {
            $shippingRule->$field = Craft::$app->getRequest()->getParam($field);
        }

        $ruleCategories = [];
        foreach (Craft::$app->getRequest()->getParam('ruleCategories') as $key => $ruleCategory) {
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
     * @return null
     * @throws HttpException
     */
    public function actionReorder()
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $ids = JsonHelper::decode(Craft::$app->getRequest()->getRequiredParam('ids'));
        $success = Plugin::getInstance()->getShippingRules()->reorderShippingRules($ids);

        return $this->asJson(['success' => $success]);
    }

    /**
     * @throws HttpException
     */
    public function actionDelete()
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $id = Craft::$app->getRequest()->getRequiredParam('id');

        Plugin::getInstance()->getShippingRules()->deleteShippingRuleById($id);
        $this->asJson(['success' => true]);
    }

}
