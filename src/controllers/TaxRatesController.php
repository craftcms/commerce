<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\models\TaxRate;
use craft\commerce\Plugin;
use craft\commerce\records\TaxRate as TaxRateRecord;
use craft\helpers\ArrayHelper;
use craft\i18n\Locale;
use yii\web\HttpException;
use yii\web\Response;

/**
 * Class Tax Rates Controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class TaxRatesController extends BaseTaxSettingsController
{
    // Public Methods
    // =========================================================================

    /**
     * @return Response
     */
    public function actionIndex(): Response
    {
        $plugin = Plugin::getInstance();
        $taxRates = $plugin->getTaxRates()->getAllTaxRates();

        // Preload all zone and category data for listing.
        $plugin->getTaxZones()->getAllTaxZones();
        $plugin->getTaxCategories()->getAllTaxCategories();

        return $this->renderTemplate('commerce/settings/taxrates/index', [
            'taxRates' => $taxRates
        ]);
    }

    /**
     * @param int|null $id
     * @param TaxRate|null $taxRate
     * @return Response
     * @throws HttpException
     */
    public function actionEdit(int $id = null, TaxRate $taxRate = null): Response
    {
        $variables = [
            'id' => $id,
            'taxRate' => $taxRate
        ];

        $plugin = Plugin::getInstance();

        if (!$variables['taxRate']) {
            if ($variables['id']) {
                $variables['taxRate'] = $plugin->getTaxRates()->getTaxRateById($variables['id']);

                if (!$variables['taxRate']) {
                    throw new HttpException(404);
                }
            } else {
                $variables['taxRate'] = new TaxRate();
            }
        }

        if ($variables['taxRate']->id) {
            $variables['title'] = $variables['taxRate']->name;
        } else {
            $variables['title'] = Craft::t('commerce', 'Create a new tax rate');
        }

        $taxZones = $plugin->getTaxZones()->getAllTaxZones();
        $variables['taxZones'] = [];

        foreach ($taxZones as $model) {
            $variables['taxZones'][$model->id] = $model->name;
        }

        $taxCategories = $plugin->getTaxCategories()->getAllTaxCategories();
        $variables['taxCategories'] = [];

        foreach ($taxCategories as $model) {
            $variables['taxCategories'][$model->id] = $model->name;
        }

        $taxable = [];
        $taxable[TaxRateRecord::TAXABLE_PRICE] = Craft::t('commerce', 'Line item price');
        $taxable[TaxRateRecord::TAXABLE_SHIPPING] = Craft::t('commerce', 'Line item shipping cost');
        $taxable[TaxRateRecord::TAXABLE_PRICE_SHIPPING] = Craft::t('commerce', 'Both (Line item price + Line item shipping costs)');
        $taxable[TaxRateRecord::TAXABLE_ORDER_TOTAL_SHIPPING] = Craft::t('commerce', 'Order total shipping cost');
        $taxable[TaxRateRecord::TAXABLE_ORDER_TOTAL_PRICE] = Craft::t('commerce', 'Order total taxable price (Line item subtotal + Total discounts + Total shipping)');
        $variables['taxables'] = $taxable;

        // Get the HTML and JS for the new tax zone/category modals
        $view = $this->getView();
        $view->setNamespace('new');

        $view->startJsBuffer();
        $countries = $plugin->getCountries()->getAllCountries();
        $states = $plugin->getStates()->getAllStates();
        $variables['newTaxZoneFields'] = $view->namespaceInputs(
            $view->renderTemplate('commerce/settings/taxzones/_fields', [
                'countries' => ArrayHelper::map($countries, 'id', 'name'),
                'states' => ArrayHelper::map($states, 'id', 'name'),
            ])
        );
        $variables['newTaxZoneJs'] = $view->clearJsBuffer(false);

        $view->startJsBuffer();
        $variables['newTaxCategoryFields'] = $view->namespaceInputs(
            $view->renderTemplate('commerce/settings/taxcategories/_fields')
        );
        $variables['newTaxCategoryJs'] = $view->clearJsBuffer(false);

        $view->setNamespace();

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
        $taxRate->id = Craft::$app->getRequest()->getBodyParam('taxRateId');
        $taxRate->name = Craft::$app->getRequest()->getBodyParam('name');
        $taxRate->include = (bool)Craft::$app->getRequest()->getBodyParam('include');
        $taxRate->isVat = (bool)Craft::$app->getRequest()->getBodyParam('isVat');
        $taxRate->taxable = Craft::$app->getRequest()->getBodyParam('taxable');
        $taxRate->taxCategoryId = Craft::$app->getRequest()->getBodyParam('taxCategoryId');
        $taxRate->taxZoneId = Craft::$app->getRequest()->getBodyParam('taxZoneId');

        $percentSign = Craft::$app->getLocale()->getNumberSymbol(Locale::SYMBOL_PERCENT);

        $rate = Craft::$app->getRequest()->getBodyParam('rate');
        if (strpos($rate, $percentSign) || $rate >= 1) {
            $taxRate->rate = (float)$rate / 100;
        } else {
            $taxRate->rate = (float)$rate;
        }

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

        $id = Craft::$app->getRequest()->getRequiredBodyParam('id');

        Plugin::getInstance()->getTaxRates()->deleteTaxRateById($id);
        return $this->asJson(['success' => true]);
    }
}
