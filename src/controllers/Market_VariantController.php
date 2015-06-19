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
class Market_VariantController extends Market_BaseController
{
	/**
	 * Create/Edit State
	 *
	 * @param array $variables
	 *
	 * @throws HttpException
	 */
	public function actionEdit(array $variables = [])
	{
		//getting related product
		if (empty($variables['productId'])) {
			throw new HttpException(400);
		}

		$variables['product'] = craft()->market_product->getById($variables['productId']);
		if (!$variables['product']) {
			throw new HttpException(404, craft::t('Product not found'));
		}

		//getting variant model
		if (empty($variables['variant'])) {
			if (!empty($variables['id'])) {
				$variables['variant'] = craft()->market_variant->getById($variables['id']);
				$variables['title'] = Craft::t('Variant for {product}', ['product' => $variables['product']]);
				if (!$variables['variant']) {
					throw new HttpException(404);
				}
			} else {
				$variables['variant'] = new Market_VariantModel();
				$variables['title'] = Craft::t('Create a Variant for {product}', ['product' => $variables['product']]);
			};

		}

		$variables['productType'] = craft()->market_productType->getByHandle($variables['productTypeHandle']);

		$this->prepVariables($variables);

		$this->renderTemplate('market/products/variants/_edit', $variables);
	}

	/**
	 * @throws HttpException
	 */
	public function actionSave()
	{
		$this->requirePostRequest();

		$variant = new Market_VariantModel();

		// Shared attributes
		$params = ['id', 'productId', 'sku', 'price', 'width', 'height', 'length', 'weight', 'stock', 'unlimitedStock', 'minQty', 'isMaster'];
		foreach ($params as $param) {
			$variant->$param = craft()->request->getPost($param);
		}

		$variant->setContentFromPost('fields');

		// Save it
		if (craft()->market_variant->save($variant)) {

			craft()->userSession->setNotice(Craft::t('Variant saved.'));
			$this->redirectToPostedUrl($variant);
		} else {
			craft()->userSession->setError(Craft::t('Couldn’t save variant.'));
		}

		// Send the model back to the template
		craft()->urlManager->setRouteVariables([
			'variant' => $variant
		]);
	}

	/**
	 * @throws HttpException
	 */
	public function actionDelete()
	{
		$this->requirePostRequest();

		$id = craft()->request->getRequiredPost('id');

		craft()->market_variant->deleteById($id);
		$this->redirectToPostedUrl();
	}

	/**
	 * Modifies the variables of the request.
	 *
	 * @param $variables
	 */
	private function prepVariables(&$variables)
	{
		$variables['tabs'] = [];

		foreach ($variables['productType']->asa('variantFieldLayout')->getFieldLayout()->getTabs() as $index => $tab) {
			// Do any of the fields on this tab have errors?
			$hasErrors = false;
			if ($variables['variant']->hasErrors()) {
				foreach ($tab->getFields() as $field) {
					if ($variables['variant']->getErrors($field->getField()->handle)) {
						$hasErrors = true;
						break;
					}
				}
			}

			$variables['tabs'][] = [
				'label' => Craft::t($tab->name),
				'url'   => '#tab' . ($index + 1),
				'class' => ($hasErrors ? 'error' : NULL)
			];
		}
	}
}