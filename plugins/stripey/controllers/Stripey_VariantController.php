<?php
namespace Craft;

class Stripey_VariantController extends Stripey_BaseController
{
    /**
     * Create/Edit State
     *
     * @param array $variables
     * @throws HttpException
     */
    public function actionEdit(array $variables = array())
    {
        if (empty($variables['variant'])) {
            if (!empty($variables['id'])) {
                $id = $variables['id'];
                $variables['variant'] = craft()->stripey_variant->getVariantById($id);

                if (!$variables['variant']) {
                    throw new HttpException(404);
                }
            } else {
                $variables['variant'] = new Stripey_VariantModel();
            };
        }

        if (!empty($variables['variant']->id)) {
            $variables['title'] = $variables['variant']->product->getContent()->title;
        } else {
            $variables['title'] = Craft::t('Create a Variant');
        }

        $this->renderTemplate('stripey/products/variants/_edit', $variables);
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