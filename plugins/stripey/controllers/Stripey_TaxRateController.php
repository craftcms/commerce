<?php
namespace Craft;

class Stripey_TaxRateController extends Stripey_BaseController
{
    /**
     * @throws HttpException
     */
    public function actionIndex()
    {
        $taxRates = craft()->stripey_taxRate->getAll();
        $this->renderTemplate('stripey/settings/taxrates/index', compact('taxRates'));
    }

    /**
     * Create/Edit TaxRate
     *
     * @param array $variables
     * @throws HttpException
     */
    public function actionEdit(array $variables = array())
    {
        if (empty($variables['taxRate'])) {
            if (!empty($variables['id'])) {
                $id = $variables['id'];
                $variables['taxRate'] = craft()->stripey_taxRate->getById($id);

                if (!$variables['taxRate']) {
                    throw new HttpException(404);
                }
            } else {
                $variables['taxRate'] = new Stripey_TaxRateModel();
            };
        }

        if (!empty($variables['id'])) {
            $variables['title'] = $variables['taxRate']->name;
        } else {
            $variables['title'] = Craft::t('Create a Tax Rate');
        }

        $taxZones = craft()->stripey_taxZone->getAll(false);
        $variables['taxZones'] = array();
        foreach($taxZones as $model) {
            $variables['taxZones'][$model->id] = $model->name;
        }

        $taxCategories = craft()->stripey_taxCategory->getAll();
        $variables['taxCategories'] = array();
        foreach($taxCategories as $model) {
            $variables['taxCategories'][$model->id] = $model->name;
        }

        $this->renderTemplate('stripey/settings/taxrates/_edit', $variables);
    }

    /**
     * @throws HttpException
     */
    public function actionSave()
    {
        $this->requirePostRequest();

        $taxRate = new Stripey_TaxRateModel();

        // Shared attributes
        $taxRate->id                = craft()->request->getPost('taxRateId');
        $taxRate->name              = craft()->request->getPost('name');
        $taxRate->rate              = craft()->request->getPost('rate');
        $taxRate->include           = craft()->request->getPost('include');
        $taxRate->showInLabel       = craft()->request->getPost('showInLabel');
        $taxRate->taxCategoryId     = craft()->request->getPost('taxCategoryId');
        $taxRate->taxZoneId         = craft()->request->getPost('taxZoneId');

        // Save it
        if (craft()->stripey_taxRate->save($taxRate)) {
            craft()->userSession->setNotice(Craft::t('Tax Rate saved.'));
            $this->redirectToPostedUrl($taxRate);
        } else {
            craft()->userSession->setError(Craft::t('Couldn’t save tax rate.'));
        }

        // Send the model back to the template
        craft()->urlManager->setRouteVariables(array(
            'taxRate' => $taxRate
        ));
    }

    /**
     * @throws HttpException
     */
    public function actionDelete()
    {
        $this->requirePostRequest();
        $this->requireAjaxRequest();

        $id = craft()->request->getRequiredPost('id');

        craft()->stripey_taxRate->deleteById($id);
        $this->returnJson(array('success' => true));
    }

}