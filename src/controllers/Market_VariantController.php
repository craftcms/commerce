<?php
namespace Craft;

class Market_VariantController extends Market_BaseController
{
	/**
	 * Create/Edit State
	 *
	 * @param array $variables
	 *
	 * @throws HttpException
	 */
	public function actionEdit(array $variables = array())
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

				if (!$variables['variant']) {
					throw new HttpException(404);
				}
			} else {
				$variables['variant']         = new Market_VariantModel();
				$variables['variant']->price  = $variables['product']->masterVariant->price;
				$variables['variant']->width  = $variables['product']->masterVariant->width;
				$variables['variant']->height = $variables['product']->masterVariant->height;
				$variables['variant']->length = $variables['product']->masterVariant->length;
				$variables['variant']->weight = $variables['product']->masterVariant->weight;
				$variables['variant']->stock  = $variables['product']->masterVariant->stock;
				$variables['variant']->unlimitedStock = $variables['product']->masterVariant->unlimitedStock;
			};
		}

		if (!empty($variables['variant']->id)) {
			$variables['title'] = Craft::t('Variant for {product}', array('product' => $variables['product']));
		} else {
			$variables['title'] = Craft::t('Create a Variant for {product}', array('product' => $variables['product']));
		}

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
		$params = array('id', 'productId', 'sku', 'price', 'width', 'height', 'length', 'weight', 'stock', 'unlimitedStock');
		foreach ($params as $param) {
			$variant->$param = craft()->request->getPost($param);
		}

		$optionValues = craft()->request->getPost('optionValues', array());

		// Save it
		if (craft()->market_variant->save($variant)) {
			$optionValuesFiltered = array_filter($optionValues);
			if ($optionValuesFiltered) {
				craft()->market_variant->setOptionValues($variant->id, $optionValuesFiltered);
			}

			craft()->userSession->setNotice(Craft::t('Variant saved.'));
			$this->redirectToPostedUrl($variant);
		} else {
			craft()->userSession->setError(Craft::t('Couldn’t save variant.'));
		}

		// Send the model back to the template
		craft()->urlManager->setRouteVariables(array(
			'variant'      => $variant,
			'optionValues' => $optionValues,
		));
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

}