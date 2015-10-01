<?php

namespace Commerce\Seed;

use Craft\Commerce_OrderSettingsModel;
use Craft\Commerce_OrderStatusModel;
use Craft\Commerce_ProductModel;
use Craft\Commerce_ProductTypeModel;
use Craft\Commerce_SettingsModel;
use Craft\Commerce_ShippingMethodRecord;
use Craft\Commerce_ShippingRuleRecord;
use Craft\Commerce_TaxCategoryModel;
use Craft\Commerce_VariantModel;
use Craft\DateTime;
use Craft\FieldLayoutModel;

/**
 * Class Commerce_InstallSeeder
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://craftcommerce.com/license Craft Commerce License Agreement
 * @see       http://craftcommerce.com
 * @package   Commerce\Seed
 * @since     1.0
 */
class Commerce_InstallSeeder implements Commerce_SeederInterface
{

	public function seed ()
	{
		$this->defaultShippingMethod();
		$this->defaultTaxCategories();
		$this->defaultOrderSettings();
		$this->defaultProductTypes();
		$this->defaultProducts();
		$this->paymentMethods();
		$this->defaultSettings();
	}

	/**
	 * Shipping Methods
	 */
	private function defaultShippingMethod ()
	{
		$method = new Commerce_ShippingMethodRecord();
		$method->name = 'Default Shipping Method';
		$method->enabled = true;
		$method->default = true;
		$method->save();

		$rule = new Commerce_ShippingRuleRecord();
		$rule->methodId = $method->id;
		$rule->description = "Catches all countries and states";
		$rule->name = "Catch All";
		$rule->enabled = true;
		$rule->save();
	}

	/**
	 * @throws \Craft\Exception
	 */
	private function defaultTaxCategories ()
	{
		$category = Commerce_TaxCategoryModel::populateModel([
			'name'    => 'General',
			'handle'  => 'general',
			'default' => 1,
		]);

		\Craft\craft()->commerce_taxCategories->save($category);
	}

	/**
	 * @throws \Exception
	 */
	private function defaultOrderSettings ()
	{

		$orderSettings = new Commerce_OrderSettingsModel;
		$orderSettings->name = 'Order';
		$orderSettings->handle = 'order';

		// Set the field layout
		$fieldLayout = \Craft\craft()->fields->assembleLayout([], []);
		$fieldLayout->type = 'Commerce_Order';
		$orderSettings->setFieldLayout($fieldLayout);

		\Craft\craft()->commerce_orderSettings->save($orderSettings);

		$data = [
			'name'    => 'Processing',
			'handle'  => 'processing',
			'color'   => 'green',
			'default' => true
		];
		$defaultStatus = Commerce_OrderStatusModel::populateModel($data);
		\Craft\craft()->commerce_orderStatuses->save($defaultStatus, []);

		$data = [
			'name'    => 'Shipped',
			'handle'  => 'shipped',
			'color'   => 'blue',
			'default' => false
		];

		$status = Commerce_OrderStatusModel::populateModel($data);

		\Craft\craft()->commerce_orderStatuses->save($status, []);
	}

	/**
	 * @throws \Craft\Exception
	 * @throws \Exception
	 */
	private function defaultProductTypes ()
	{
		$productType = new Commerce_ProductTypeModel;
		$productType->name = 'Plain Shirts';
		$productType->handle = 'plainShirts';
		$productType->hasDimensions = true;
		$productType->hasUrls = true;
		$productType->hasVariants = false;
		$productType->template = 'commerce/products/_product';

		$fieldLayout = FieldLayoutModel::populateModel(['type' => 'Commerce_Product']);
		\Craft\craft()->fields->saveLayout($fieldLayout);
		$productType->asa('productFieldLayout')->setFieldLayout($fieldLayout);

		$variantFieldLayout = FieldLayoutModel::populateModel(['type' => 'Commerce_Variant']);
		\Craft\craft()->fields->saveLayout($variantFieldLayout);
		$productType->asa('variantFieldLayout')->setFieldLayout($variantFieldLayout);

		\Craft\craft()->commerce_productTypes->save($productType);

		$productTypeLocales = \Craft\craft()->i18n->getSiteLocaleIds();

		foreach ($productTypeLocales as $locale)
		{
			\Craft\craft()->db->createCommand()->insert('commerce_producttypes_i18n', [
				'productTypeId' => $productType->id,
				'locale'        => $locale,
				'urlFormat'     => 'commerce/products/{slug}'
			]);
		}
	}

	/**
	 * @throws \Craft\Exception
	 * @throws \Craft\HttpException
	 * @throws \Exception
	 */
	private function defaultProducts ()
	{
		$productTypes = \Craft\craft()->commerce_productTypes->getAll();

		//first test product
		/** @var Commerce_ProductModel $product */
		$product = Commerce_ProductModel::populateModel([
			'typeId'        => $productTypes[0]->id,
			'enabled'       => 1,
			'authorId'      => \Craft\craft()->userSession->id,
			'availableOn'   => new DateTime(),
			'expiresOn'     => null,
			'promotable'    => 1,
			'taxCategoryId' => \Craft\craft()->commerce_taxCategories->getDefaultId(),
		]);

		$product->getContent()->title = 'Nice Shirt';

		\Craft\craft()->commerce_products->save($product);

		//implicit variant
		/** @var Commerce_VariantModel $implicitVariant */
		$implicitVariant = Commerce_VariantModel::populateModel([
			'productId'      => $product->id,
			'isImplicit'     => 1,
			'sku'            => 'ABC',
			'price'          => 10,
			'unlimitedStock' => 1,
		]);
		\Craft\craft()->commerce_variants->save($implicitVariant);

		//another test product
		/** @var Commerce_ProductModel $product */
		$product = Commerce_ProductModel::populateModel([
			'typeId'        => $productTypes[0]->id,
			'enabled'       => 1,
			'authorId'      => \Craft\craft()->userSession->id,
			'availableOn'   => new DateTime(),
			'expiresOn'     => null,
			'promotable'    => 1,
			'taxCategoryId' => \Craft\craft()->commerce_taxCategories->getDefaultId(),
		]);

		$product->getContent()->title = 'Really Nice Shirt';

		\Craft\craft()->commerce_products->save($product);

		//implicit variant
		$implicitVariant = Commerce_VariantModel::populateModel([
			'productId'      => $product->id,
			'isImplicit'     => 1,
			'sku'            => 'CBA',
			'price'          => 20,
			'unlimitedStock' => 1,
		]);
		\Craft\craft()->commerce_variants->save($implicitVariant);
	}

	private function paymentMethods ()
	{
		$model = \Craft\craft()->commerce_paymentMethods->getByClass('Dummy');
		$model->frontendEnabled = true;
		\Craft\craft()->commerce_paymentMethods->save($model);
	}

	private function defaultSettings ()
	{
		$settings = new Commerce_SettingsModel();
		$settings->orderPdfPath = 'commerce/_pdf/order';
		$settings->paymentMethod = 'purchase';
		\Craft\craft()->commerce_settings->save($settings);
	}

}