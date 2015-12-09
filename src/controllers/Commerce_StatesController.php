<?php
namespace Craft;

/**
 * Class Commerce_StatesController
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.controllers
 * @since     1.0
 */
class Commerce_StatesController extends Commerce_BaseAdminController
{
    /**
     * @throws HttpException
     */
    public function actionIndex()
    {
        $states = craft()->commerce_states->getAllStates();
        $this->renderTemplate('commerce/settings/states/index',
            compact('states'));
    }

    /**
     * Create/Edit State
     *
     * @param array $variables
     *
     * @throws HttpException
     */
    public function actionEdit(array $variables = [])
    {
        if (empty($variables['state'])) {
            if (!empty($variables['id'])) {
                $id = $variables['id'];
                $variables['state'] = craft()->commerce_states->getStateById($id);

                if (!$variables['state']) {
                    throw new HttpException(404);
                }
            } else {
                $variables['state'] = new Commerce_StateModel();
            };
        }

        if (!empty($variables['id'])) {
            $variables['title'] = $variables['state']->name;
        } else {
            $variables['title'] = Craft::t('Create a new state');
        }

        $countriesModels = craft()->commerce_countries->getAllCountries();
        $countries = [];
        foreach ($countriesModels as $model) {
            $countries[$model->id] = $model->name;
        }
        $variables['countries'] = $countries;

        $this->renderTemplate('commerce/settings/states/_edit', $variables);
    }

    /**
     * @throws HttpException
     */
    public function actionSave()
    {
        $this->requirePostRequest();

        $state = new Commerce_StateModel();

        // Shared attributes
        $state->id = craft()->request->getPost('stateId');
        $state->name = craft()->request->getPost('name');
        $state->abbreviation = craft()->request->getPost('abbreviation');
        $state->countryId = craft()->request->getPost('countryId');

        // Save it
        if (craft()->commerce_states->saveState($state)) {
            craft()->userSession->setNotice(Craft::t('State saved.'));
            $this->redirectToPostedUrl($state);
        } else {
            craft()->userSession->setError(Craft::t('Couldn’t save state.'));
        }

        // Send the model back to the template
        craft()->urlManager->setRouteVariables([
            'state' => $state
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

        craft()->commerce_states->deleteStateById($id);
        $this->returnJson(['success' => true]);
    }

}
