<?php
namespace Craft;

/**
 * @author    Make with Morph. <support@makewithmorph.com>
 * @copyright Copyright (c) 2015, Luke Holder.
 * @license   http://makewithmorph.com/market/license Market License Agreement
 * @see       http://makewithmorph.com
 * @package   craft.plugins.market.controllers
 * @since     0.1
 */
class Market_SaleController extends Market_BaseController
{
	/**
	 * @throws HttpException
	 */
	public function actionIndex()
	{
		$sales = craft()->market_sale->getAll(['order' => 'name']);
		$this->renderTemplate('market/sales/index', compact('sales'));
	}

	/**
	 * Create/Edit Sale
	 *
	 * @param array $variables
	 *
	 * @throws HttpException
	 */
	public function actionEdit(array $variables = [])
	{
		if (empty($variables['sale'])) {
			if (!empty($variables['id'])) {
				$id                = $variables['id'];
				$variables['sale'] = craft()->market_sale->getById($id);

				if (!$variables['sale']->id) {
					throw new HttpException(404);
				}
			} else {
				$variables['sale'] = new Market_SaleModel();
			}
		}

		if (!empty($variables['id'])) {
			$variables['title'] = $variables['sale']->name;
		} else {
			$variables['title'] = Craft::t('Create a Sale');
		}

		//getting user groups map
		$groups              = craft()->userGroups->getAllGroups();
		$variables['groups'] = \CHtml::listData($groups, 'id', 'name');

		//getting product types maps
		$types              = craft()->market_productType->getAll();
		$variables['types'] = \CHtml::listData($types, 'id', 'name');

		$this->renderTemplate('market/sales/_edit', $variables);
	}

	/**
	 * @throws HttpException
	 */
	public function actionSave()
	{
		$this->requirePostRequest();

		$sale = new Market_SaleModel();

		// Shared attributes
		$fields = ['id', 'name', 'description', 'dateFrom', 'dateTo', 'discountType', 'discountAmount', 'enabled'];
		foreach ($fields as $field) {
			$sale->$field = craft()->request->getPost($field);
		}

		$products     = craft()->request->getPost('products', []);
		$productTypes = craft()->request->getPost('productTypes', []);
		$groups       = craft()->request->getPost('groups', []);

		// Save it
		if (craft()->market_sale->save($sale, $groups, $productTypes, $products)) {
			craft()->userSession->setNotice(Craft::t('Sale saved.'));
			$this->redirectToPostedUrl($sale);
		} else {
			craft()->userSession->setError(Craft::t('Couldn’t save sale.'));
		}

		// Send the model back to the template
		craft()->urlManager->setRouteVariables(['sale' => $sale]);
	}

	/**
	 * @throws HttpException
	 */
	public function actionDelete()
	{
		$this->requirePostRequest();
		$this->requireAjaxRequest();

		$id = craft()->request->getRequiredPost('id');

		craft()->market_sale->deleteById($id);
		$this->returnJson(['success' => true]);
	}

}