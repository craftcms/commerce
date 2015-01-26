<?php
namespace Craft;

class Stripey_StateController extends Stripey_BaseController
{
    /**
     * @throws HttpException
     */
    public function actionIndex()
    {
        $states = craft()->stripey_state->getAll();
        $this->renderTemplate('stripey/settings/states/index', compact('states'));
    }

    /**
     * Create/Edit State
     *
     * @param array $variables
     * @throws HttpException
     */
    public function actionEdit(array $variables = array())
    {
        if (empty($variables['state'])) {
            if (!empty($variables['id'])) {
                $id = $variables['id'];
                $variables['state'] = craft()->stripey_state->getById($id);

                if (!$variables['state']) {
                    throw new HttpException(404);
                }
            } else {
                $variables['state'] = new Stripey_StateModel();
            };
        }

        if (!empty($variables['id'])) {
            $variables['title'] = $variables['state']->name;
        } else {
            $variables['title'] = Craft::t('Create a State');
        }

        $countriesModels = craft()->stripey_country->getAll();
        $countries = array();
        foreach($countriesModels as $model) {
            $countries[$model->id] = $model->name;
        }
        $variables['countries'] = $countries;

        $this->renderTemplate('stripey/settings/states/_edit', $variables);
    }

    /**
     * @throws HttpException
     */
    public function actionSave()
    {
        $this->requirePostRequest();

        $state = new Stripey_StateModel();

        // Shared attributes
        $state->id   = craft()->request->getPost('stateId');
        $state->name = craft()->request->getPost('name');
        $state->abbreviation  = craft()->request->getPost('abbreviation');
        $state->countryId  = craft()->request->getPost('countryId');

        // Save it
        if (craft()->stripey_state->save($state)) {
            craft()->userSession->setNotice(Craft::t('State saved.'));
            $this->redirectToPostedUrl($state);
        } else {
            craft()->userSession->setError(Craft::t('Couldn’t save state.'));
        }

        // Send the model back to the template
        craft()->urlManager->setRouteVariables(array(
            'state' => $state
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

        craft()->stripey_state->deleteById($id);
        $this->returnJson(array('success' => true));
    }

}