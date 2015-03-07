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
class Market_DiscountController extends Market_BaseController
{
	/**
	 * @throws HttpException
	 */
	public function actionIndex()
	{
		$discounts = craft()->market_discount->getAll(['order' => 'name']);
		$this->renderTemplate('market/settings/discounts/index', compact('discounts'));
	}

	/**
	 * Create/Edit Discount
	 *
	 * @param array $variables
	 *
	 * @throws HttpException
	 */
	public function actionEdit(array $variables = [])
	{
		if (empty($variables['discount'])) {
			if (!empty($variables['id'])) {
				$id                    = $variables['id'];
				$variables['discount'] = craft()->market_discount->getById($id);

				if (!$variables['discount']->id) {
					throw new HttpException(404);
				}
			} else {
				$variables['discount'] = new Market_DiscountModel();
			}
		}

		if (!empty($variables['id'])) {
			$variables['title'] = $variables['discount']->name;
		} else {
			$variables['title'] = Craft::t('Create a Discount');
		}

		//getting user groups map
		$groups              = craft()->userGroups->getAllGroups();
		$variables['groups'] = \CHtml::listData($groups, 'id', 'name');

		//getting product types maps
		$types              = craft()->market_productType->getAll();
		$variables['types'] = \CHtml::listData($types, 'id', 'name');

		$this->renderTemplate('market/settings/discounts/_edit', $variables);
	}

	/**
	 * @throws HttpException
	 */
	public function actionSave()
	{
		$this->requirePostRequest();

		$discount = new Market_DiscountModel();

		// Shared attributes
		$fields = ['id', 'name', 'description', 'dateFrom', 'dateTo', 'enabled', 'purchaseTotal', 'purchaseQty', 'baseDiscount', 'perItemDiscount',
			'percentDiscount', 'freeShipping', 'excludeOnSale', 'code', 'perUserLimit', 'totalUseLimit'];
		foreach ($fields as $field) {
			$discount->$field = craft()->request->getPost($field);
		}

		$products     = craft()->request->getPost('products', []);
		$productTypes = craft()->request->getPost('productTypes', []);
		$groups       = craft()->request->getPost('groups', []);

		// Save it
		if (craft()->market_discount->save($discount, $groups, $productTypes, $products)) {
			craft()->userSession->setNotice(Craft::t('Discount saved.'));
			$this->redirectToPostedUrl($discount);
		} else {
			craft()->userSession->setError(Craft::t('Couldn’t save discount.'));
		}

		// Send the model back to the template
		craft()->urlManager->setRouteVariables(['discount' => $discount]);
	}

	/**
	 * @throws HttpException
	 */
	public function actionDelete()
	{
		$this->requirePostRequest();
		$this->requireAjaxRequest();

		$id = craft()->request->getRequiredPost('id');

		craft()->market_discount->deleteById($id);
		$this->returnJson(['success' => true]);
	}

}