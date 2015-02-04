<?php
namespace Craft;

class Market_ProductTypeController extends Market_BaseController
{
	protected $allowAnonymous = false;

	public function actionIndex()
	{
		$productTypes = craft()->market_productType->getAll();
		$this->renderTemplate('market/settings/producttypes/index', compact('productTypes'));

	}

	public function actionEditProductType(array $variables = array())
	{
		$variables['brandNewProductType'] = false;

		if (empty($variables['productType'])) {
			if (!empty($variables['productTypeId'])) {
				$productTypeId            = $variables['productTypeId'];
				$variables['productType'] = craft()->market_productType->getById($productTypeId);

				if (!$variables['productType']) {
					throw new HttpException(404);
				}
			} else {
				$variables['productType']         = new Market_ProductTypeModel();
				$variables['brandNewProductType'] = true;
			};
		}

		if (!empty($variables['productTypeId'])) {
			$variables['title'] = $variables['productType']->name;
		} else {
			$variables['title'] = Craft::t('Create a Product Type');
		}

		$this->renderTemplate('market/settings/producttypes/_edit', $variables);
	}

	public function actionSaveProductType()
	{
		$this->requirePostRequest();

		$productType = new Market_ProductTypeModel();

		// Shared attributes
		$productType->id     = craft()->request->getPost('productTypeId');
		$productType->name   = craft()->request->getPost('name');
		$productType->handle = craft()->request->getPost('handle');

		// Set the field layout
		$fieldLayout       = craft()->fields->assembleLayoutFromPost();
		$fieldLayout->type = 'Market_Product';
		$productType->setFieldLayout($fieldLayout);

		// Save it
		if (craft()->market_productType->save($productType)) {
			craft()->userSession->setNotice(Craft::t('Product type saved.'));
			$this->redirectToPostedUrl($productType);
		} else {
			craft()->userSession->setError(Craft::t('Couldn’t save product type.'));
		}

		// Send the calendar back to the template
		craft()->urlManager->setRouteVariables(array(
			'productType' => $productType
		));
	}


	public function actionDeleteProductType()
	{
		$this->requirePostRequest();
		$this->requireAjaxRequest();

		$productTypeId = craft()->request->getRequiredPost('id');

		craft()->market_productType->deleteById($productTypeId);
		$this->returnJson(array('success' => true));
	}

} 