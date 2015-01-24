<?php
namespace Craft;

class Stripey_CountryController extends Stripey_BaseController
{
    /**
     * @throws HttpException
     */
    public function actionIndex()
    {
        $countries = craft()->stripey_country->getAll();
        $this->renderTemplate('stripey/settings/countries/index', compact('countries'));
    }

    /**
     * Create/Edit Country
     *
     * @param array $variables
     * @throws HttpException
     */
    public function actionEdit(array $variables = array())
    {
        if (!empty($variables['id'])) {
            $id = $variables['id'];
            $variables['country'] = craft()->stripey_country->getById($id);

            if (!$variables['country']) {
                throw new HttpException(404);
            }
            $variables['title'] = $variables['country']->name;
        } else {
            if (empty($variables['country'])) {
                $variables['country']         = new Stripey_CountryModel();
            }
            $variables['title'] = Craft::t('Create a Country');
        };

        $this->renderTemplate('stripey/settings/countries/_edit', $variables);
    }

    /**
     * @throws HttpException
     */
    public function actionSave()
    {
        $this->requirePostRequest();

        $country = new Stripey_CountryModel();

        // Shared attributes
        $country->id   = craft()->request->getPost('countryId');
        $country->name = craft()->request->getPost('name');
        $country->iso  = craft()->request->getPost('iso');
        $country->stateRequired  = craft()->request->getPost('stateRequired');

        // Save it
        if (craft()->stripey_country->save($country)) {
            craft()->userSession->setNotice(Craft::t('Country saved.'));
            $this->redirectToPostedUrl($country);
        } else {
            craft()->userSession->setError(Craft::t('Couldn’t save country.'));
        }

        // Send the model back to the template
        craft()->urlManager->setRouteVariables(array(
            'country' => $country
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

        craft()->stripey_country->deleteById($id);
        $this->returnJson(array('success' => true));
    }

}