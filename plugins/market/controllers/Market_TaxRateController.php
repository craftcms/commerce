<?php
namespace Craft;

/**
 *
 *
 * @author    Make with Morph. <support@makewithmorph.com>
 * @copyright Copyright (c) 2015, Luke Holder.
 * @license   http://makewithmorph.com/market/license Market License Agreement
 * @see       http://makewithmorph.com
 * @package   craft.plugins.market.controllers
 * @since     0.1
 */
class Market_TaxRateController extends Market_BaseController
{
    /**
     * @throws HttpException
     */
    public function actionIndex()
    {
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
        $this->requirePostRequest();
        $this->requireAjaxRequest();

        $id = craft()->request->getRequiredPost('id');

        craft()->market_taxRate->deleteById($id);
        $this->returnJson(['success' => true]);
    }

}