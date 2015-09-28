<?php
namespace Craft;

/**
 * Class Commerce_ProductController
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://craftcommerce.com/license Craft Commerce License Agreement
 * @see       http://craftcommerce.com
 * @package   craft.plugins.commerce.controllers
 * @since     1.0
 */
use Commerce\Helpers\CommerceDbHelper;

/**
 * Class Commerce_ProductController
 *
 * @package Craft
 */
class Commerce_ProductController extends Commerce_BaseAdminController
{
	/** @var bool All product changes should be by a logged in user */
	protected $allowAnonymous = false;

	/**
	 * Index of products
	 */
	public function actionProductIndex ()
	{
		$variables['productTypes'] = craft()->commerce_productType->getAll();
		$variables['taxCategories'] = craft()->commerce_taxCategory->getAll();
		$this->renderTemplate('commerce/products/_index', $variables);
	}

	/**
	 * Prepare screen to edit a product.
	 *
	 * @param array $variables
	 *
	 * @throws HttpException
	 */
	public function actionEditProduct (array $variables = [])
	{
		$this->_prepProductVariables($variables);

		if (!empty($variables['product']->id))
		{
			$variables['title'] = $variables['product']->title;
		}
		else
		{
			$variables['title'] = Craft::t('Create a new Product');
		}

		$variables['continueEditingUrl'] = "commerce/products/".$variables['productTypeHandle']."/{id}-{slug}".
			(craft()->isLocalized() && craft()->getLanguage() != $variables['localeId'] ? '/'.$variables['localeId'] : '');

		$variables['taxCategories'] = \CHtml::listData(craft()->commerce_taxCategory->getAll(),
			'id', 'name');

		$this->_prepVariables($variables);
		craft()->templates->includeCssResource('commerce/product.css');
		$this->renderTemplate('commerce/products/_edit', $variables);
	}

	private function _prepProductVariables (&$variables)
	{
		if (craft()->isLocalized())
		{
			// default to all use all locales for now
			$variables['localeIds'] = craft()->i18n->getEditableLocaleIds();
		}
		else
		{
			$variables['localeIds'] = [craft()->i18n->getPrimarySiteLocaleId()];
		}

		if (empty($variables['localeId']))
		{
			$variables['localeId'] = craft()->language;

			if (!in_array($variables['localeId'], $variables['localeIds']))
			{
				$variables['localeId'] = $variables['localeIds'][0];
			}
		}
		else
		{
			// Make sure they were requesting a valid locale
			if (!in_array($variables['localeId'], $variables['localeIds']))
			{
				throw new HttpException(404);
			}
		}

		if (!empty($variables['productTypeHandle']))
		{
			$variables['productType'] = craft()->commerce_productType->getByHandle($variables['productTypeHandle']);
		}

		if (empty($variables['productType']))
		{
			throw new HttpException(400,
				craft::t('Wrong product type specified'));
		}

		if (empty($variables['product']))
		{
			if (!empty($variables['productId']))
			{
				$variables['product'] = craft()->commerce_product->getById($variables['productId'], $variables['localeId']);

				if (!$variables['product']->id)
				{
					throw new HttpException(404);
				}
			}
			else
			{
				$variables['product'] = new Commerce_ProductModel();
				$variables['product']->typeId = $variables['productType']->id;
				if ($variables['localeId'])
				{
					$variables['product']->locale = $variables['localeId'];
				}
			}
		}

		if (!empty($variables['product']->id))
		{
			$variables['enabledLocales'] = craft()->elements->getEnabledLocalesForElement($variables['product']->id);
		}
		else
		{
			$variables['enabledLocales'] = [];

			foreach (craft()->i18n->getEditableLocaleIds() as $locale)
			{
				$variables['enabledLocales'][] = $locale;
			}
		}
	}

	/**
	 * @param $variables
	 *
	 * @throws HttpException
	 */
	private function _prepVariables (&$variables)
	{
		$variables['tabs'] = [];

		if (empty($variables['implicitVariant']))
		{
			$variables['implicitVariant'] = $variables['product']->implicitVariant ?: new Commerce_VariantModel;
		}

		foreach ($variables['productType']->getFieldLayout()->getTabs() as $index => $tab)
		{
			// Do any of the fields on this tab have errors?
			$hasErrors = false;
			if ($variables['product']->hasErrors())
			{
				foreach ($tab->getFields() as $field)
				{
					if ($variables['product']->getErrors($field->getField()->handle))
					{
						$hasErrors = true;
						break;
					}
				}
			}

			$variables['tabs'][] = [
				'label' => Craft::t($tab->name),
				'url'   => '#tab'.($index + 1),
				'class' => ($hasErrors ? 'error' : null)
			];
		}
	}

	/**
	 * Deletes a product.
	 *
	 * @throws Exception if you try to edit a non existing Id.
	 */
	public function actionDeleteProduct ()
	{
		if (!craft()->userSession->getUser()->can('manageCommerce'))
		{
			throw new HttpException(403, Craft::t('This action is not allowed for the current user.'));
		}

		$this->requirePostRequest();

		$productId = craft()->request->getRequiredPost('productId');
		$product = craft()->commerce_product->getById($productId);

		if (!$product->id)
		{
			throw new Exception(Craft::t('No product exists with the ID “{id}”.',
				['id' => $productId]));
		}

		if (craft()->commerce_product->delete($product))
		{
			if (craft()->request->isAjaxRequest())
			{
				$this->returnJson(['success' => true]);
			}
			else
			{
				craft()->userSession->setNotice(Craft::t('Product deleted.'));
				$this->redirectToPostedUrl($product);
			}
		}
		else
		{
			if (craft()->request->isAjaxRequest())
			{
				$this->returnJson(['success' => false]);
			}
			else
			{
				craft()->userSession->setError(Craft::t('Couldn’t delete product.'));

				craft()->urlManager->setRouteVariables([
					'product' => $product

				]);
			}
		}
	}

	/**
	 * Save a new or existing product.
	 */
	public function actionSaveProduct ()
	{
		if (!craft()->userSession->getUser()->can('manageCommerce'))
		{
			throw new HttpException(403, Craft::t('This action is not allowed for the current user.'));
		}

		$this->requirePostRequest();

		$product = $this->_setProductFromPost();
		$implicitVariant = $this->_setImplicitVariantFromPost($product);

		$existingProduct = (bool)$product->id;

		CommerceDbHelper::beginStackedTransaction();

		if (craft()->commerce_product->save($product))
		{
			$implicitVariant->productId = $product->id;

			if (craft()->commerce_variant->save($implicitVariant))
			{

				CommerceDbHelper::commitStackedTransaction();

				craft()->userSession->setNotice(Craft::t('Product saved.'));

				if (craft()->request->getPost('redirectToVariant'))
				{
					$this->redirect($product->getCpEditUrl().'/variants/new');
				}
				else
				{
					$this->redirectToPostedUrl($product);
				}
			}
		}

		CommerceDbHelper::rollbackStackedTransaction();
		// Since Product may have been ok to save and an ID assigned,
		// but child model validation failed and the transaction rolled back.
		// Since action failed, lets remove the ID that was no persisted.
		if (!$existingProduct)
		{
			$product->id = null;
		}


		craft()->userSession->setNotice(Craft::t("Couldn't save product."));
		craft()->urlManager->setRouteVariables([
			'product'         => $product,
			'implicitVariant' => $implicitVariant
		]);
	}

	/**
	 * @return Commerce_ProductModel
	 * @throws Exception
	 */
	private function _setProductFromPost ()
	{
		$productId = craft()->request->getPost('productId');
		$locale = craft()->request->getPost('locale');

		if ($productId)
		{
			$product = craft()->commerce_product->getById($productId, $locale);

			if (!$product)
			{
				throw new Exception(Craft::t('No product with the ID “{id}”',
					['id' => $productId]));
			}
		}
		else
		{
			$product = new Commerce_ProductModel();
		}

		$availableOn = craft()->request->getPost('availableOn');
		$expiresOn = craft()->request->getPost('expiresOn');

		$product->availableOn = $availableOn ? DateTime::createFromString($availableOn, craft()->timezone) : $product->availableOn;
		$product->expiresOn = $expiresOn ? DateTime::createFromString($expiresOn, craft()->timezone) : null;
		$product->typeId = craft()->request->getPost('typeId');
		$product->enabled = craft()->request->getPost('enabled');
		$product->promotable = craft()->request->getPost('promotable');
		$product->freeShipping = craft()->request->getPost('freeShipping');
		$product->authorId = craft()->userSession->id;
		$product->taxCategoryId = craft()->request->getPost('taxCategoryId', $product->taxCategoryId);
		$product->localeEnabled = (bool)craft()->request->getPost('localeEnabled', $product->localeEnabled);

		if (!$product->availableOn)
		{
			$product->availableOn = new DateTime();
		}

		$product->getContent()->title = craft()->request->getPost('title', $product->title);
		$product->slug = craft()->request->getPost('slug', $product->slug);
		$product->setContentFromPost('fields');

		return $product;
	}

	/**
	 * @param Commerce_ProductModel $product
	 *
	 * @return Commerce_VariantModel
	 */
	private function _setImplicitVariantFromPost (Commerce_ProductModel $product)
	{
		$attributes = craft()->request->getPost('implicitVariant');
		$implicitVariant = $product->getImplicitVariant() ?: new Commerce_VariantModel;
		$implicitVariant->setAttributes($attributes);
		$implicitVariant->isImplicit = true;

		return $implicitVariant;
	}
} 
