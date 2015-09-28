<?php
namespace Craft;

/**
 * Class Commerce_SaleController
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://craftcommerce.com/license Craft Commerce License Agreement
 * @see       http://craftcommerce.com
 * @package   craft.plugins.commerce.controllers
 * @since     1.0
 */
class Commerce_SaleController extends Commerce_BaseAdminController
{
	/**
	 * @throws HttpException
	 */
	public function actionIndex ()
	{
		$sales = craft()->commerce_sale->getAll(['order' => 'name']);
		$this->renderTemplate('commerce/promotions/sales/index',
			compact('sales'));
	}

	/**
	 * Create/Edit Sale
	 *
	 * @param array $variables
	 *
	 * @throws HttpException
	 */
	public function actionEdit (array $variables = [])
	{
		if (empty($variables['sale']))
		{
			if (!empty($variables['id']))
			{
				$id = $variables['id'];
				$variables['sale'] = craft()->commerce_sale->getById($id);

				if (!$variables['sale']->id)
				{
					throw new HttpException(404);
				}
			}
			else
			{
				$variables['sale'] = new Commerce_SaleModel();
			}
		}

		if (!empty($variables['id']))
		{
			$variables['title'] = $variables['sale']->name;
		}
		else
		{
			$variables['title'] = Craft::t('Create a Sale');
		}

		//getting user groups map
		$groups = craft()->userGroups->getAllGroups();
		$variables['groups'] = \CHtml::listData($groups, 'id', 'name');

		//getting product types maps
		$types = craft()->commerce_productType->getAll();
		$variables['types'] = \CHtml::listData($types, 'id', 'name');


		$variables['products'] = null;
		$products = $productIds = [];
		if (empty($variables['id']))
		{
			$productIds = explode('|', craft()->request->getParam('productIds'));
		}
		else
		{
			$productIds = $variables['sale']->getProductsIds();
		}
		foreach ($productIds as $productId)
		{
			$products[] = craft()->commerce_product->getById($productId);
		}
		$variables['products'] = $products;

		$this->renderTemplate('commerce/promotions/sales/_edit', $variables);
	}

	/**
	 * @throws HttpException
	 */
	public function actionSave ()
	{
		$this->requirePostRequest();

		$sale = new Commerce_SaleModel();

		// Shared attributes
		$fields = [
			'id',
			'name',
			'description',
			'dateFrom',
			'dateTo',
			'discountType',
			'enabled'
		];
		foreach ($fields as $field)
		{
			$sale->$field = craft()->request->getPost($field);
		}

		$discountAmount = craft()->request->getPost('discountAmount');
		if($sale->discountType == 'percent'){
			$localeData = craft()->i18n->getLocaleData();
			$percentSign = $localeData->getNumberSymbol('percentSign');
			if(strpos($discountAmount,$percentSign) or floatval($discountAmount) >= 1){
				$sale->discountAmount = floatval($discountAmount) / -100;
			}else{
				$sale->discountAmount = floatval($discountAmount);
			};
		}else{
			$sale->discountAmount = -floatval($discountAmount);
		}

		$products = craft()->request->getPost('products', []);
		if(!$products){
			$products = [];
		}
		$productTypes = craft()->request->getPost('productTypes', []);
		$groups = craft()->request->getPost('groups', []);

		// Save it
		if (craft()->commerce_sale->save($sale, $groups, $productTypes,$products))
		{
			craft()->userSession->setNotice(Craft::t('Sale saved.'));
			$this->redirectToPostedUrl($sale);
		}
		else
		{
			craft()->userSession->setError(Craft::t('Couldn’t save sale.'));
		}

		// Send the model back to the template
		craft()->urlManager->setRouteVariables(['sale' => $sale]);
	}

	/**
	 * @throws HttpException
	 */
	public function actionDelete ()
	{
		$this->requirePostRequest();
		$this->requireAjaxRequest();

		$id = craft()->request->getRequiredPost('id');

		craft()->commerce_sale->deleteById($id);
		$this->returnJson(['success' => true]);
	}

}