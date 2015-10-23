<?php
namespace Craft;

/**
 * Class Commerce_CountriesController
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://craftcommerce.com/license Craft Commerce License Agreement
 * @see       http://craftcommerce.com
 * @package   craft.plugins.commerce.controllers
 * @since     1.0
 */
class Commerce_CountriesController extends Commerce_BaseAdminController
{
    /**
     * @throws HttpException
     */
    public function actionIndex()
    {
        $countries = craft()->commerce_countries->getAll();
        $this->renderTemplate('commerce/settings/countries/index',
            compact('countries'));
    }

    /**
     * Create/Edit Country
     *
     * @param array $variables
     *
     * @throws HttpException
     */
    public function actionEdit(array $variables = [])
    {
        if (empty($variables['country'])) {
            if (!empty($variables['id'])) {
                $id = $variables['id'];
                $variables['country'] = craft()->commerce_countries->getById($id);

                if (!$variables['country']->id) {
                    throw new HttpException(404);
                }
            } else {
                $variables['country'] = new Commerce_CountryModel();
            }
        }

        if (!empty($variables['id'])) {
            $variables['title'] = $variables['country']->name;
        } else {
            $variables['title'] = Craft::t('Create a new country');
        }

        $this->renderTemplate('commerce/settings/countries/_edit', $variables);
    }

    /**
     * @throws HttpException
     */
    public function actionSave()
    {
        $this->requirePostRequest();

        $country = new Commerce_CountryModel();

        // Shared attributes
        $country->id = craft()->request->getPost('countryId');
        $country->name = craft()->request->getPost('name');
        $country->iso = craft()->request->getPost('iso');
        $country->stateRequired = craft()->request->getPost('stateRequired');

        // Save it
        if (craft()->commerce_countries->save($country)) {
            craft()->userSession->setNotice(Craft::t('Country saved.'));
            $this->redirectToPostedUrl($country);
        } else {
            craft()->userSession->setError(Craft::t('Couldn’t save country.'));
        }

        // Send the model back to the template
        craft()->urlManager->setRouteVariables(['country' => $country]);
    }

    /**
     * @throws HttpException
     */
    public function actionDelete()
    {
        $this->requirePostRequest();
        $this->requireAjaxRequest();

        $id = craft()->request->getRequiredPost('id');

        try {
            craft()->commerce_countries->deleteById($id);
            $this->returnJson(['success' => true]);
        } catch (\Exception $e) {
            $this->returnErrorJson($e->getMessage());
        }
    }

}