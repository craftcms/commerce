<?php
namespace Craft;

/**
 * Class Commerce_TaxZonesController
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.controllers
 * @since     1.0
 */
class Commerce_TaxZonesController extends Commerce_BaseAdminController
{
    /**
     * @throws HttpException
     */
    public function actionIndex()
    {
        $taxZones = craft()->commerce_taxZones->getAllTaxZones();
        $this->renderTemplate('commerce/settings/taxzones/index',
            compact('taxZones'));
    }

    /**
     * Create/Edit TaxZone
     *
     * @param array $variables
     *
     * @throws HttpException
     */
    public function actionEdit(array $variables = [])
    {
        if (empty($variables['taxZone'])) {
            if (!empty($variables['id'])) {
                $id = $variables['id'];
                $variables['taxZone'] = craft()->commerce_taxZones->getTaxZoneById($id);

                if (!$variables['taxZone']) {
                    throw new HttpException(404);
                }
            } else {
                $variables['taxZone'] = new Commerce_TaxZoneModel();
            };
        }

        if (!empty($variables['id'])) {
            $variables['title'] = $variables['taxZone']->name;
        } else {
            $variables['title'] = Craft::t('Create a tax zone');
        }

        $countries = craft()->commerce_countries->getAllCountries();
        $states = craft()->commerce_states->getAllStates();

        $variables['countries'] = \CHtml::listData($countries, 'id', 'name');
        $variables['states'] = \CHtml::listData($states, 'id', 'name');

        $this->renderTemplate('commerce/settings/taxzones/_edit', $variables);
    }

    /**
     * @throws HttpException
     */
    public function actionSave()
    {
        $this->requirePostRequest();

        $taxZone = new Commerce_TaxZoneModel();

        // Shared attributes
        $taxZone->id = craft()->request->getPost('taxZoneId');
        $taxZone->name = craft()->request->getPost('name');
        $taxZone->description = craft()->request->getPost('description');
        $taxZone->countryBased = craft()->request->getPost('countryBased');
        $taxZone->default = craft()->request->getPost('default');
        $countryIds = craft()->request->getPost('countries') ? craft()->request->getPost('countries') : [];
        $stateIds = craft()->request->getPost('states') ? craft()->request->getPost('states') : [];

        $countries = [];
        foreach ($countryIds as $id)
        {
            if ($country = craft()->commerce_countries->getCountryById($id))
            {
               $countries[] = $country;
            }
        }
        $taxZone->setCountries($countries);

        $states = [];
        foreach ($stateIds as $id)
        {
            if ($state = craft()->commerce_states->getStateById($id))
            {
                $states[] = $state;
            }
        }
        $taxZone->setStates($states);

        // TODO: refactor to remove ids params which are not needed.
        if (craft()->commerce_taxZones->saveTaxZone($taxZone, $taxZone->getCountryIds(), $taxZone->getStateIds())) {
            if (craft()->request->isAjaxRequest()) {
                $this->returnJson([
                    'success' => true,
                    'id' => $taxZone->id,
                    'name' => $taxZone->name,
                ]);
            } else {
                craft()->userSession->setNotice(Craft::t('Tax zone saved.'));
                $this->redirectToPostedUrl($taxZone);
            }
        } else {
            if (craft()->request->isAjaxRequest()) {
                $this->returnJson([
                    'errors' => $taxZone->getErrors()
                ]);
            } else {
                craft()->userSession->setError(Craft::t('Couldn’t save tax zone.'));
            }
        }

        // Send the model back to the template
        craft()->urlManager->setRouteVariables(['taxZone' => $taxZone]);
    }

    /**
     * @throws HttpException
     */
    public function actionDelete()
    {
        $this->requirePostRequest();
        $this->requireAjaxRequest();

        $id = craft()->request->getRequiredPost('id');

        craft()->commerce_taxZones->deleteTaxZoneById($id);
        $this->returnJson(['success' => true]);
    }

}
