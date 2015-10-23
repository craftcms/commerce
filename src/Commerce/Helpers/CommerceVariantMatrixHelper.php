<?php
namespace Commerce\Helpers;

use Craft\JsonHelper as JsonHelper;
use Craft\Commerce_ProductModel as Product;
use Craft\Commerce_VariantModel as Variant;

/**
 * Class CommerceVariantMatrixHelper
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://craftcommerce.com/license Craft Commerce License Agreement
 * @see       http://craftcommerce.com
 * @package   Commerce\Helpers
 * @since     1.0
 */
class CommerceVariantMatrixHelper
{
	// Public Methods
	// =========================================================================

	/**
	 * Returns the HTML for a given product’s variant matrix.
	 *
	 * @param Product $product The product model
	 * @param string  $name    The input name (sans namespace). Default is 'variants'.
	 *
	 * @return string The variant matrix HTML
	 */
	public function getVariantMatrixHtml(Product $product, $name = 'variants')
	{
		$id = \Craft\craft()->templates->formatInputId($name);

		$html = \Craft\craft()->templates->render('commerce/products/_variant_matrix', array(
			'id' => $id,
			'name' => $name,
			'variants' => $product->getVariants()
		));

		// Get the field HTML
		list($fieldBodyHtml, $fieldFootHtml) = self::_getVariantFieldHtml($product, $name);

		\Craft\craft()->templates->includeJsResource('commerce/js/VariantMatrix.js');

		\Craft\craft()->templates->includeJs('new Craft.Commerce.VariantMatrix(' .
			'"'.\Craft\craft()->templates->namespaceInputId($id).'", ' .
			JsonHelper::encode($fieldBodyHtml).', ' .
			JsonHelper::encode($fieldFootHtml).', ' .
			'"'.\Craft\craft()->templates->namespaceInputName($name).'"' .
		');');

		\Craft\craft()->templates->includeTranslations('Disabled', 'Actions', 'Collapse', 'Expand', 'Disable', 'Enable', 'Add variant above', 'Add a block', 'Are you sure you want to delete the selected variants?');

		return $html;
	}

	// Private Methods
	// =========================================================================

	/**
	 * Returns info about each variant field type for a variant matrix.
	 *
	 * @param Product $product The product model
	 * @param string  $name    The input name (sans namespace)
	 *
	 * @return array
	 */
	private function _getVariantFieldHtml($product, $name)
	{
		// Set a temporary namespace for these
		//$originalNamespace = \Craft\craft()->templates->getNamespace();
		//$namespace = \Craft\craft()->templates->namespaceInputName($name.'[__VARIANT__]', $originalNamespace);
		//\Craft\craft()->templates->setNamespace($namespace);

		// Create a fake Variant model so the field types have a way to get at the owner element, if there is one
		$variant = new Variant();
		$variant->setProduct($product);

		$variantFields = $variant->getFieldLayout()->getFields();

		foreach ($variantFields as $fieldLayoutField)
		{
			$fieldType = $fieldLayoutField->getField()->getFieldType();

			if ($fieldType)
			{
				$fieldType->element = $variant;
				$fieldType->setIsFresh(true);
			}
		}

		\Craft\craft()->templates->startJsBuffer();

		$bodyHtml = \Craft\craft()->templates->render('commerce/products/_variant_matrix_fields', array(
			'namespace' => $name.'[__VARIANT__]',
			'variant'   => $variant
		));

		$footHtml = \Craft\craft()->templates->clearJsBuffer();

		// Reset $_isFresh's
		foreach ($variantFields as $fieldLayoutField)
		{
			$fieldType = $fieldLayoutField->getField()->getFieldType();

			if ($fieldType)
			{
				$fieldType->setIsFresh(null);
			}
		}

		//\Craft\craft()->templates->setNamespace($originalNamespace);

		return array($bodyHtml, $footHtml);
	}
}
