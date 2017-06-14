<?php

namespace craft\commerce\controllers;

use craft\commerce\models\TaxRate;
use craft\commerce\Plugin;
use craft\commerce\records\TaxRate as TaxRateRecord;
use craft\helpers\ArrayHelper;
use Craft;
use yii\web\HttpException;
use yii\web\Response;

/**
 * Class Tax Rates Controller
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.controllers
 * @since     1.0
 */
class TaxRatesController extends BaseAdminController
{
    /**
     * @return Response
     */
    public function actionIndex(): Response
    {
        $taxRates = Plugin::getInstance()->getTaxRates()->getAllTaxRatesWithZoneAndCategories();
        return $this->renderTemplate('commerce/settings/taxrates/index', compact('taxRates'));
    }

    /**
     * @param int|null     $id
     * @param TaxRate|null $taxRate
     *
     * @return Response
     * @throws HttpException
     */
    public function actionEdit(int $id = null, TaxRate $taxRate = null): Response
    {
        $variables = [
            'id' => $id,
            'taxRate' => $taxRate
        ];

        if (!$variables['taxRate']) {
            if ($variables['id']) {
                $variables['taxRate'] = Plugin::getInstance()->getTaxRates()->getTaxRateById($variables['id']);

                if (!$variables['taxRate']) {
                    throw new HttpException(404);
                }
            } else {
                $variables['taxRate'] = new TaxRate();
            };
        }

        if ($variables['taxRate']->id) {
            $variables['title'] = $variables['taxRate']->name;
        } else {
            $variables['title'] = Craft::t('commerce', 'Create a new tax rate');
        }

        $taxZones = Plugin::getInstance()->getTaxZones()->getAllTaxZones(false);
        $variables['taxZones'] = [];
        foreach ($taxZones as $model) {
            $variables['taxZones'][$model->id] = $model->name;
        }

        $taxCategories = Plugin::getInstance()->getTaxCategories()->getAllTaxCategories();
        $variables['taxCategories'] = [];
        foreach ($taxCategories as $model) {
            $variables['taxCategories'][$model->id] = $model->name;
        }

        $taxable = [];
        $taxable[TaxRateRecord::TAXABLE_PRICE] = Craft::t('commerce', 'Item cost');
        $taxable[TaxRateRecord::TAXABLE_SHIPPING] = Craft::t('commerce', 'Shipping cost');
        $taxable[TaxRateRecord::TAXABLE_PRICE_SHIPPING] = Craft::t('commerce', 'Both (item + shipping costs)');
        $variables['taxables'] = $taxable;

        // Get the HTML and JS for the new tax zone/category modals
        Craft::$app->getView()->setNamespace('new');

        Craft::$app->getView()->startJsBuffer();
        $countries = Plugin::getInstance()->getCountries()->getAllCountries();
        $states = Plugin::getInstance()->getStates()->getAllStates();
        $variables['newTaxZoneFields'] = Craft::$app->getView()->namespaceInputs(
            Craft::$app->getView()->render('commerce/settings/taxzones/_fields', [
                'countries' => ArrayHelper::map($countries, 'id', 'name'),
                'states' => ArrayHelper::map($states, 'id', 'name'),
            ])
        );
        $variables['newTaxZoneJs'] = Craft::$app->getView()->clearJsBuffer(false);

        Craft::$app->getView()->startJsBuffer();
        $variables['newTaxCategoryFields'] = Craft::$app->getView()->namespaceInputs(
            Craft::$app->getView()->render('commerce/settings/taxcategories/_fields')
        );
        $variables['newTaxCategoryJs'] = Craft::$app->getView()->clearJsBuffer(false);

        Craft::$app->getView()->setNamespace(null);

        return $this->renderTemplate('commerce/settings/taxrates/_edit', $variables);
    }

    /**
     * @throws HttpException
     */
    public function actionSave()
    {
        $this->requirePostRequest();

        $taxRate = new TaxRate();

        // Shared attributes
        $taxRate->id = Craft::$app->getRequest()->getParam('taxRateId');
        $taxRate->name = Craft::$app->getRequest()->getParam('name');
        $taxRate->include = Craft::$app->getRequest()->getParam('include');
        $taxRate->isVat = Craft::$app->getRequest()->getParam('isVat');
        $taxRate->taxable = Craft::$app->getRequest()->getParam('taxable');
        $taxRate->taxCategoryId = Craft::$app->getRequest()->getParam('taxCategoryId');
        $taxRate->taxZoneId = Craft::$app->getRequest()->getParam('taxZoneId');

        $localeData = Craft::$app->getI18n()->getLocaleData();
        $percentSign = $localeData->getNumberSymbol('percentSign');
        $rate = Craft::$app->getRequest()->getParam('rate');
        if (strpos($rate, $percentSign) or $rate >= 1) {
            $taxRate->rate = floatval($rate) / 100;
        } else {
            $taxRate->rate = floatval($rate);
        };

        // Save it
        if (Plugin::getInstance()->getTaxRates()->saveTaxRate($taxRate)) {
            Craft::$app->getSession()->setNotice(Craft::t('commerce', 'Tax rate saved.'));
            $this->redirectToPostedUrl($taxRate);
        } else {
            Craft::$app->getSession()->setError(Craft::t('commerce', 'Couldn’t save tax rate.'));
        }


        // Send the model back to the template
        Craft::$app->getUrlManager()->setRouteParams([
            'taxRate' => $taxRate
        ]);
    }

    /**
     * @throws HttpException
     */
    public function actionDelete()
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $id = Craft::$app->getRequest()->getRequiredParam('id');

        Plugin::getInstance()->getTaxRates()->deleteTaxRateById($id);
        $this->asJson(['success' => true]);
    }

}
