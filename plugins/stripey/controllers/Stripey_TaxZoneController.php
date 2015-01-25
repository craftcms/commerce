<?php
namespace Craft;

class Stripey_TaxZoneController extends Stripey_BaseController
{
    /**
     * @throws HttpException
     */
    public function actionIndex()
    {
        $taxZones = craft()->stripey_taxZone->getAll();
        $this->renderTemplate('stripey/settings/taxZones/index', compact('taxZones'));
    }

    /**
     * Create/Edit TaxZone
     *
     * @param array $variables
     * @throws HttpException
     */
    public function actionEdit(array $variables = array())
    {
        if (empty($variables['taxZone'])) {
            if (!empty($variables['id'])) {
                $id = $variables['id'];
                $variables['taxZone'] = craft()->stripey_taxZone->getById($id);

                if (!$variables['taxZone']) {
                    throw new HttpException(404);
                }
            } else {
                $variables['taxZone'] = new Stripey_TaxZoneModel();
            };
        }

        if (!empty($variables['id'])) {
            $variables['title'] = $variables['taxZone']->name;
        } else {
            $variables['title'] = Craft::t('Create a Tax Zone');
        }
        
        $countries = craft()->stripey_country->getAll();
        $states = craft()->stripey_state->getAll();
        
        $variables['countries'] = $variables['states'] = array();
        
        foreach($countries as $country) {
            $variables['countries'][$country->id] = $country->name;
        }
        foreach($states as $state) {
            $variables['states'][$state->id] = $state->formatName();
        }

        $this->renderTemplate('stripey/settings/taxZones/_edit', $variables);
    }

    /**
     * @throws HttpException
     */
    public function actionSave()
    {
        $this->requirePostRequest();
        $taxZone = new Stripey_TaxZoneModel();

        // Shared attributes
        $taxZone->id            = craft()->request->getPost('taxZoneId');
        $taxZone->name          = craft()->request->getPost('name');
        $taxZone->description   = craft()->request->getPost('description');
        $taxZone->countryBased  = craft()->request->getPost('countryBased');
        $countriesIds           = craft()->request->getPost('countries', array());
        $statesIds              = craft()->request->getPost('states', array());

        // Save it
        if (craft()->stripey_taxZone->save($taxZone, $countriesIds, $statesIds)) {
            craft()->userSession->setNotice(Craft::t('Tax Zone saved.'));
            $this->redirectToPostedUrl($taxZone);
        } else {
            craft()->userSession->setError(Craft::t('Couldn’t save tax zone.'));
        }

        // Send the model back to the template
        craft()->urlManager->setRouteVariables(array(
            'taxZone' => $taxZone
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

        craft()->stripey_taxZone->deleteById($id);
        $this->returnJson(array('success' => true));
    }

}