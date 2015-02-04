<?php
namespace Craft;

class Market_StateController extends Market_BaseController
{
	/**
	 * @throws HttpException
	 */
	public function actionIndex()
	{
		$states = craft()->market_state->getAll();
		$this->renderTemplate('market/settings/states/index', compact('states'));
	}

	/**
	 * Create/Edit State
	 *
	 * @param array $variables
	 *
	 * @throws HttpException
	 */
	public function actionEdit(array $variables = array())
	{
		if (empty($variables['state'])) {
			if (!empty($variables['id'])) {
				$id                 = $variables['id'];
				$variables['state'] = craft()->market_state->getById($id);

				if (!$variables['state']) {
					throw new HttpException(404);
				}
			} else {
				$variables['state'] = new Market_StateModel();
			};
		}

		if (!empty($variables['id'])) {
			$variables['title'] = $variables['state']->name;
		} else {
			$variables['title'] = Craft::t('Create a State');
		}

		$countriesModels = craft()->market_country->getAll();
		$countries       = array();
		foreach ($countriesModels as $model) {
			$countries[$model->id] = $model->name;
		}
		$variables['countries'] = $countries;

		$this->renderTemplate('market/settings/states/_edit', $variables);
	}

	/**
	 * @throws HttpException
	 */
	public function actionSave()
	{
		$this->requirePostRequest();

		$state = new Market_StateModel();

		// Shared attributes
		$state->id           = craft()->request->getPost('stateId');
		$state->name         = craft()->request->getPost('name');
		$state->abbreviation = craft()->request->getPost('abbreviation');
		$state->countryId    = craft()->request->getPost('countryId');

		// Save it
		if (craft()->market_state->save($state)) {
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

		craft()->market_state->deleteById($id);
		$this->returnJson(array('success' => true));
	}

}