<?php
namespace Craft;

class Stripey_TaxCategoryController extends Stripey_BaseController
{
	/**
	 * @throws HttpException
	 */
	public function actionIndex()
	{
		$taxCategories = craft()->stripey_taxCategory->getAll();
		$this->renderTemplate('stripey/settings/taxcategories/index', compact('taxCategories'));
	}

	/**
	 * Create/Edit Tax Category
	 *
	 * @param array $variables
	 *
	 * @throws HttpException
	 */
	public function actionEdit(array $variables = array())
	{
		if (empty($variables['taxCategory'])) {
			if (!empty($variables['id'])) {
				$id                       = $variables['id'];
				$variables['taxCategory'] = craft()->stripey_taxCategory->getById($id);

				if (!$variables['taxCategory']) {
					throw new HttpException(404);
				}
			} else {
				$variables['taxCategory'] = new Stripey_TaxCategoryModel();
			};
		}

		if (!empty($variables['id'])) {
			$variables['title'] = $variables['taxCategory']->name;
		} else {
			$variables['title'] = Craft::t('Create a Tax Category');
		}

		$this->renderTemplate('stripey/settings/taxcategories/_edit', $variables);
	}

	/**
	 * @throws HttpException
	 */
	public function actionSave()
	{
		$this->requirePostRequest();

		$taxCategory = new Stripey_TaxCategoryModel();

		// Shared attributes
		$taxCategory->id          = craft()->request->getPost('taxCategoryId');
		$taxCategory->name        = craft()->request->getPost('name');
		$taxCategory->code        = craft()->request->getPost('code');
		$taxCategory->description = craft()->request->getPost('description');
		$taxCategory->default     = craft()->request->getPost('default');

		// Save it
		if (craft()->stripey_taxCategory->save($taxCategory)) {
			craft()->userSession->setNotice(Craft::t('Tax category saved.'));
			$this->redirectToPostedUrl($taxCategory);
		} else {
			craft()->userSession->setError(Craft::t('Couldn’t save tax category.'));
		}

		// Send the tax category back to the template
		craft()->urlManager->setRouteVariables(array(
			'taxCategory' => $taxCategory
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

		craft()->stripey_taxCategory->deleteById($id);
		$this->returnJson(array('success' => true));
	}

}