<?php
namespace Craft;

/**
 * Class Commerce_TaxRatesController
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.controllers
 * @since     1.0
 */
class Commerce_TaxRatesController extends Commerce_BaseAdminController
{
    /**
     * @throws HttpException
     */
    public function actionIndex()
    {
        $taxRates = craft()->commerce_taxRates->getAllTaxRates([
            'with' => ['taxZone', 'taxCategory'],
            'order' => 't.name',
        ]);
        $this->renderTemplate('commerce/settings/taxrates/index', compact('taxRates', 'zonesExist'));
    }

    /**
     * Create/Edit TaxRate
     *
     * @param array $variables
     *
     * @throws HttpException
     */
    public function actionEdit(array $variables = [])
    {
        if (empty($variables['taxRate'])) {
            if (!empty($variables['id'])) {
                $id = $variables['id'];
                $variables['taxRate'] = craft()->commerce_taxRates->getTaxRateById($id);

                if (!$variables['taxRate']) {
                    throw new HttpException(404);
                }
            } else {
                $variables['taxRate'] = new Commerce_TaxRateModel();
            };
        }

        if (!empty($variables['id'])) {
            $variables['title'] = $variables['taxRate']->name;
        } else {
            $variables['title'] = Craft::t('Create a new tax rate');
        }

        $taxZones = craft()->commerce_taxZones->getAllTaxZones(false);
        $variables['taxZones'] = [];
        foreach ($taxZones as $model) {
            $variables['taxZones'][$model->id] = $model->name;
        }

        $taxCategories = craft()->commerce_taxCategories->getAllTaxCategories();
        $variables['taxCategories'] = [];
        foreach ($taxCategories as $model) {
            $variables['taxCategories'][$model->id] = $model->name;
        }

        $taxable = [];
        $taxable[Commerce_TaxRateRecord::TAXABLE_PRICE] = Craft::t('Line item price');
        $taxable[Commerce_TaxRateRecord::TAXABLE_SHIPPING] = Craft::t('Line item shipping cost');
        $taxable[Commerce_TaxRateRecord::TAXABLE_PRICE_SHIPPING] = Craft::t('Both (Line item price + Line item shipping costs)');
        $taxable[Commerce_TaxRateRecord::TAXABLE_ORDER_TOTAL_SHIPPING] = Craft::t('Order total shipping cost');
        $taxable[Commerce_TaxRateRecord::TAXABLE_ORDER_TOTAL_PRICE] = Craft::t('Order total taxable price (Line item subtotal + Total discounts + Total shipping');
        $variables['taxables'] = $taxable;

        // Get the HTML and JS for the new tax zone/category modals
        craft()->templates->setNamespace('new');

        craft()->templates->startJsBuffer();
        $countries = craft()->commerce_countries->getAllCountries();
        $states = craft()->commerce_states->getAllStates();
        $variables['newTaxZoneFields'] = craft()->templates->namespaceInputs(
                craft()->templates->render('commerce/settings/taxzones/_fields', [
                'countries' => \CHtml::listData($countries, 'id', 'name'),
                'states' => \CHtml::listData($states, 'id', 'name'),
            ])
        );
        $variables['newTaxZoneJs'] = craft()->templates->clearJsBuffer(false);

        craft()->templates->startJsBuffer();
        $variables['newTaxCategoryFields'] = craft()->templates->namespaceInputs(
            craft()->templates->render('commerce/settings/taxcategories/_fields')
        );
        $variables['newTaxCategoryJs'] = craft()->templates->clearJsBuffer(false);

        craft()->templates->setNamespace(null);

        $this->renderTemplate('commerce/settings/taxrates/_edit', $variables);
    }

    /**
     * @throws HttpException
     */
    public function actionSave()
    {
        $this->requirePostRequest();

        $taxRate = new Commerce_TaxRateModel();

        // Shared attributes
        $taxRate->id = craft()->request->getPost('taxRateId');
        $taxRate->name = craft()->request->getPost('name');
        $taxRate->include = craft()->request->getPost('include');
        $taxRate->isVat = craft()->request->getPost('isVat');
        $taxRate->taxable = craft()->request->getPost('taxable');
        $taxRate->taxCategoryId = craft()->request->getPost('taxCategoryId');
        $taxRate->taxZoneId = craft()->request->getPost('taxZoneId');

        $localeData = craft()->i18n->getLocaleData();
        $percentSign = $localeData->getNumberSymbol('percentSign');
        $rate = craft()->request->getPost('rate');
        if (strpos($rate, $percentSign) or $rate >= 1) {
            $taxRate->rate = (float) $rate / 100;
        } else {
            $taxRate->rate = (float) $rate;
        };

        // Save it
        if (craft()->commerce_taxRates->saveTaxRate($taxRate)) {
            craft()->userSession->setNotice(Craft::t('Tax rate saved.'));
            $this->redirectToPostedUrl($taxRate);
        } else {
            craft()->userSession->setError(Craft::t('Couldn’t save tax rate.'));
        }


        // Send the model back to the template
        craft()->urlManager->setRouteVariables([
            'taxRate' => $taxRate
        ]);
    }

    /**
     * @throws HttpException
     */
    public function actionDelete()
    {
        $this->requirePostRequest();
        $this->requireAjaxRequest();

        $id = craft()->request->getRequiredPost('id');

        craft()->commerce_taxRates->deleteTaxRateById($id);
        $this->returnJson(['success' => true]);
    }

}
