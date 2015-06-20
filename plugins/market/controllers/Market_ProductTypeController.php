<?php
namespace Craft;

/**
 *
 *
 * @author    Make with Morph. <support@makewithmorph.com>
 * @copyright Copyright (c) 2015, Luke Holder.
 * @license   http://makewithmorph.com/market/license Market License Agreement
 * @see       http://makewithmorph.com
 * @package   craft.plugins.market.controllers
 * @since     0.1
 */
class Market_ProductTypeController extends Market_BaseController
{
	protected $allowAnonymous = false;

	public function actionIndex()
	{
		$productTypes = craft()->market_productType->getAll();
		$this->renderTemplate('market/settings/producttypes/index', compact('productTypes'));

	}

	public function actionEditProductType(array $variables = [])
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
		$productType->id          = craft()->request->getPost('productTypeId');
		$productType->name        = craft()->request->getPost('name');
		$productType->handle      = craft()->request->getPost('handle');
		$productType->hasUrls     = craft()->request->getPost('hasUrls');
		$productType->hasVariants = craft()->request->getPost('hasVariants');
		$productType->template    = craft()->request->getPost('template');
		$productType->urlFormat   = craft()->request->getPost('urlFormat');

		// Set the field layout
		$fieldLayout       = craft()->fields->assembleLayoutFromPost();
		$fieldLayout->type = 'Market_Product';
		$productType->asa('productFieldLayout')->setFieldLayout($fieldLayout);

		// Set the variant field layout, we need to manually do so since assembleLayout has hardcoded post names
		$postedFieldLayout        = craft()->request->getPost('variantfieldLayout', []);
		$requiredFields           = craft()->request->getPost('variantrequiredFields', []);
		$variantFieldLayout       = craft()->fields->assembleLayout($postedFieldLayout, $requiredFields);
		$variantFieldLayout->type = 'Market_Variant';
		$productType->asa('variantFieldLayout')->setFieldLayout($variantFieldLayout);

		// Save it
		if (craft()->market_productType->save($productType)) {
			craft()->userSession->setNotice(Craft::t('Product type saved.'));
			$this->redirectToPostedUrl($productType);
		} else {
			craft()->userSession->setError(Craft::t('Couldn’t save product type.'));
		}

		// Send the productType back to the template
		craft()->urlManager->setRouteVariables([
			'productType' => $productType
		]);
	}


	public function actionDeleteProductType()
	{
		$this->requirePostRequest();
		$this->requireAjaxRequest();

		$productTypeId = craft()->request->getRequiredPost('id');

		craft()->market_productType->deleteById($productTypeId);
		$this->returnJson(['success' => true]);
	}
} 