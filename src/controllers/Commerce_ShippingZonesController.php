<?php
namespace Craft;

/**
 * Class Commerce_ShippingZonesController
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.controllers
 * @since     1.0
 */
class Commerce_ShippingZonesController extends Commerce_BaseAdminController
{
    /**
     * @throws HttpException
     */
    public function actionIndex()
    {
        $shippingZones = craft()->commerce_shippingZones->getAllShippingZones();
        $this->renderTemplate('commerce/settings/shippingzones/index',
            compact('shippingZones'));
    }

    /**
     * Create/Edit ShippingZone
     *
     * @param array $variables
     *
     * @throws HttpException
     */
    public function actionEdit(array $variables = [])
    {
        if (empty($variables['shippingZone']))
        {
            if (!empty($variables['id']))
            {
                $id = $variables['id'];
                $variables['shippingZone'] = craft()->commerce_shippingZones->getShippingZoneById($id);

                if (!$variables['shippingZone'])
                {
                    throw new HttpException(404);
                }
            }
            else
            {
                $variables['shippingZone'] = new Commerce_ShippingZoneModel();
            };
        }

        if (!empty($variables['id']))
        {
            $variables['title'] = $variables['shippingZone']->name;
        }
        else
        {
            $variables['title'] = Craft::t('Create a shipping zone');
        }

        $countries = craft()->commerce_countries->getAllCountries();
        $states = craft()->commerce_states->getAllStates();

        $variables['countries'] = \CHtml::listData($countries, 'id', 'name');
        $variables['states'] = \CHtml::listData($states, 'id', 'name');

        $this->renderTemplate('commerce/settings/shippingzones/_edit', $variables);
    }

    /**
     * @throws HttpException
     */
    public function actionSave()
    {
        $this->requirePostRequest();

        $shippingZone = new Commerce_ShippingZoneModel();

        // Shared attributes
        $shippingZone->id = craft()->request->getPost('shippingZoneId');
        $shippingZone->name = craft()->request->getPost('name');
        $shippingZone->description = craft()->request->getPost('description');
        $shippingZone->countryBased = craft()->request->getPost('countryBased');
        $shippingZone->countryBased = craft()->request->getPost('countryBased');
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
        $shippingZone->setCountries($countries);

        $states = [];
        foreach ($stateIds as $id)
        {
            if ($state = craft()->commerce_states->getStateById($id))
            {
                $states[] = $state;
            }
        }
        $shippingZone->setStates($states);

        // Save it
        if (craft()->commerce_shippingZones->saveShippingZone($shippingZone, $shippingZone->getCountryIds(), $shippingZone->getStateIds()))
        {
            if (craft()->request->isAjaxRequest())
            {
                $this->returnJson([
                    'success' => true,
                    'id'      => $shippingZone->id,
                    'name'    => $shippingZone->name,
                ]);
            }
            else
            {
                craft()->userSession->setNotice(Craft::t('Shipping zone saved.'));
                $this->redirectToPostedUrl($shippingZone);
            }
        }
        else
        {
            if (craft()->request->isAjaxRequest())
            {
                $this->returnJson([
                    'errors' => $shippingZone->getErrors()
                ]);
            }
            else
            {
                craft()->userSession->setError(Craft::t('Couldn’t save shipping zone.'));
            }
        }

        // Send the model back to the template
        craft()->urlManager->setRouteVariables(['shippingZone' => $shippingZone]);
    }

    /**
     * @throws HttpException
     */
    public function actionDelete()
    {
        $this->requirePostRequest();
        $this->requireAjaxRequest();

        $id = craft()->request->getRequiredPost('id');

        craft()->commerce_shippingZones->deleteShippingZoneById($id);
        $this->returnJson(['success' => true]);
    }

}
