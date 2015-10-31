<?php
namespace Craft;

/**
 * Class Commerce_TaxRatesController
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://craftcommerce.com/license Craft Commerce License Agreement
 * @see       http://craftcommerce.com
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
        $taxZones = craft()->commerce_taxZones->getAll();
        $zonesExist = (bool)count($taxZones);

        $taxRates = craft()->commerce_taxRates->getAll([
            'with' => ['taxZone', 'taxCategory'],
            'order' => 't.name',
        ]);
        $this->renderTemplate('commerce/settings/taxrates/index',
            compact('taxRates', 'zonesExist'));
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
        $taxZones = craft()->commerce_taxZones->getAll();
        $zonesExist = (bool)count($taxZones);

        if (!$zonesExist) {
            craft()->userSession->setError(Craft::t('You must create a tax zone before creating a tax rate.'));
            craft()->request->redirect('/admin/commerce/settings/taxrates');
        }

        if (empty($variables['taxRate'])) {
            if (!empty($variables['id'])) {
                $id = $variables['id'];
                $variables['taxRate'] = craft()->commerce_taxRates->getById($id);

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

        $taxZones = craft()->commerce_taxZones->getAll(false);
        $variables['taxZones'] = [];
        foreach ($taxZones as $model) {
            $variables['taxZones'][$model->id] = $model->name;
        }

        $taxCategories = craft()->commerce_taxCategories->getAll();
        $variables['taxCategories'] = [];
        foreach ($taxCategories as $model) {
            $variables['taxCategories'][$model->id] = $model->name;
        }

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
        $taxRate->showInLabel = craft()->request->getPost('showInLabel');
        $taxRate->taxCategoryId = craft()->request->getPost('taxCategoryId');
        $taxRate->taxZoneId = craft()->request->getPost('taxZoneId');

        $localeData = craft()->i18n->getLocaleData();
        $percentSign = $localeData->getNumberSymbol('percentSign');
        $rate = craft()->request->getPost('rate');
        if (strpos($rate, $percentSign) or $rate >= 1) {
            $taxRate->rate = floatval($rate) / 100;
        } else {
            $taxRate->rate = floatval($rate);
        };

        // Save it
        if (craft()->commerce_taxRates->save($taxRate)) {
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

        craft()->commerce_taxRates->deleteById($id);
        $this->returnJson(['success' => true]);
    }

}