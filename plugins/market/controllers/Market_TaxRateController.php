<?php
namespace Craft;

/**
 * Class Market_TaxRateController
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://buildwithcraft.com/license Craft License Agreement
 * @see       http://buildwithcraft.com/commerce
 * @package   craft.plugins.commerce.controllers
 * @since     1.0
 */
class Market_TaxRateController extends Market_BaseController
{
    /**
     * @throws HttpException
     */
    public function actionIndex()
    {
        $this->requireAdmin();

        $taxRates = craft()->market_taxRate->getAll([
            'with'  => ['taxZone', 'taxCategory'],
            'order' => 't.name',
        ]);
        $this->renderTemplate('market/settings/taxrates/index',
            compact('taxRates'));
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
        $this->requireAdmin();

        if (empty($variables['taxRate'])) {
            if (!empty($variables['id'])) {
                $id                   = $variables['id'];
                $variables['taxRate'] = craft()->market_taxRate->getById($id);

                if (!$variables['taxRate']) {
                    throw new HttpException(404);
                }
            } else {
                $variables['taxRate'] = new Market_TaxRateModel();
            };
        }

        if (!empty($variables['id'])) {
            $variables['title'] = $variables['taxRate']->name;
        } else {
            $variables['title'] = Craft::t('Create a Tax Rate');
        }

        $taxZones              = craft()->market_taxZone->getAll(false);
        $variables['taxZones'] = [];
        foreach ($taxZones as $model) {
            $variables['taxZones'][$model->id] = $model->name;
        }

        $taxCategories              = craft()->market_taxCategory->getAll();
        $variables['taxCategories'] = [];
        foreach ($taxCategories as $model) {
            $variables['taxCategories'][$model->id] = $model->name;
        }

        $this->renderTemplate('market/settings/taxrates/_edit', $variables);
    }

    /**
     * @throws HttpException
     */
    public function actionSave()
    {
        $this->requireAdmin();
        $this->requirePostRequest();

        $taxRate = new Market_TaxRateModel();

        // Shared attributes
        $taxRate->id            = craft()->request->getPost('taxRateId');
        $taxRate->name          = craft()->request->getPost('name');
        $taxRate->rate          = craft()->request->getPost('rate');
        $taxRate->include       = craft()->request->getPost('include');
        $taxRate->showInLabel   = craft()->request->getPost('showInLabel');
        $taxRate->taxCategoryId = craft()->request->getPost('taxCategoryId');
        $taxRate->taxZoneId     = craft()->request->getPost('taxZoneId');

        // Save it
        if (craft()->market_taxRate->save($taxRate)) {
            craft()->userSession->setNotice(Craft::t('Tax Rate saved.'));
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
        $this->requireAdmin();
        $this->requirePostRequest();
        $this->requireAjaxRequest();

        $id = craft()->request->getRequiredPost('id');

        craft()->market_taxRate->deleteById($id);
        $this->returnJson(['success' => true]);
    }

}