<?php

namespace Market\Seed;

use Craft\Market_OrderSettingsModel;
use Craft\Market_SettingsModel;
use Craft\Market_ShippingMethodRecord;
use Craft\Market_ShippingRuleRecord;
use Craft\Market_OrderStatusModel;
use Craft\Market_ProductModel;
use Craft\Market_ProductTypeModel;
use Craft\Market_VariantModel;
use Craft\Market_TaxCategoryModel;
use Craft\FieldLayoutModel;
use Craft\DateTime;

/**
 * Default Seeder
 */
class Market_InstallSeeder implements Market_SeederInterface
{

	public function seed()
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
	private function defaultShippingMethod()
	{
		$method          = new Market_ShippingMethodRecord();
		$method->name    = 'Default Shipping Method';
		$method->enabled = true;
		$method->default = true;
		$method->save();

		$rule = new Market_ShippingRuleRecord();
		$rule->methodId = $method->id;
		$rule->description  = "Catches all countries and states";
		$rule->name  = "Catch All";
		$rule->enabled = true;
		$rule->save();

	}

	/**
	 * @throws \Exception
	 */
	private function defaultOrderSettings()
	{

			$orderSettings                   = new Market_OrderSettingsModel;
			$orderSettings->name             = 'Order';
			$orderSettings->handle           = 'order';

			// Set the field layout
			$fieldLayout       = \Craft\craft()->fields->assembleLayout([], []);
			$fieldLayout->type = 'Market_Order';
			$orderSettings->setFieldLayout($fieldLayout);

			\Craft\craft()->market_orderSettings->save($orderSettings);

			$data  = [
				'name'        => 'Processing',
				'handle'      => 'processing',
				'color'       => 'green',
				'default'     => true
			];
			$defaultStatus = Market_OrderStatusModel::populateModel($data);
			\Craft\craft()->market_orderStatus->save($defaultStatus,[]);

			$data  = [
				'name'        => 'Shipped',
				'handle'      => 'shipped',
				'color'       => 'blue',
				'default'     => false
			];

			$status = Market_OrderStatusModel::populateModel($data);

			\Craft\craft()->market_orderStatus->save($status,[]);

	}

	/**
	 * @throws \Craft\Exception
	 */
	private function defaultTaxCategories()
	{
		$category = Market_TaxCategoryModel::populateModel([
			'name'    => 'General',
			'default' => 1,
		]);

		\Craft\craft()->market_taxCategory->save($category);
	}

	/**
	 * @throws \Craft\Exception
	 * @throws \Exception
	 */
	private function defaultProductTypes()
	{
		$productType         = new Market_ProductTypeModel;
		$productType->name   = 'Plain Shirts';
		$productType->handle = 'plainShirts';
		$productType->hasDimensions = true;
		$productType->hasUrls = true;
		$productType->hasVariants = false;
		$productType->template = 'commerce/products/_product';

		$fieldLayout = FieldLayoutModel::populateModel(['type' => 'Market_Product']);
		\Craft\craft()->fields->saveLayout($fieldLayout);
		$productType->asa('productFieldLayout')->setFieldLayout($fieldLayout);

		$variantFieldLayout = FieldLayoutModel::populateModel(['type' => 'Market_Variant']);
		\Craft\craft()->fields->saveLayout($variantFieldLayout);
		$productType->asa('variantFieldLayout')->setFieldLayout($variantFieldLayout);

		\Craft\craft()->market_productType->save($productType);

		$productTypeLocales = \Craft\craft()->i18n->getSiteLocaleIds();

		foreach($productTypeLocales as $locale){
			\Craft\craft()->db->createCommand()->insert('market_producttypes_i18n',[
				'productTypeId' => $productType->id,
				'locale' => $locale,
				'urlFormat' => 'commerce/products/{slug}'
			]);
		}

	}

	/**
	 * @throws \Craft\Exception
	 * @throws \Craft\HttpException
	 * @throws \Exception
	 */
	private function defaultProducts()
	{
		$productTypes = \Craft\craft()->market_productType->getAll();

		//first test product
		/** @var Market_ProductModel $product */
		$product = Market_ProductModel::populateModel([
			'typeId'        => $productTypes[0]->id,
			'enabled'       => 1,
			'authorId'      => \Craft\craft()->userSession->id,
			'availableOn'   => new DateTime(),
			'expiresOn'     => NULL,
			'promotable'    => 1,
			'taxCategoryId' => \Craft\craft()->market_taxCategory->getDefaultId(),
		]);

		$product->getContent()->title = 'Nice Shirt';

		\Craft\craft()->market_product->save($product);

		//implicit variant
		/** @var Market_VariantModel $implicitVariant */
		$implicitVariant = Market_VariantModel::populateModel([
			'productId'      => $product->id,
			'isImplicit'       => 1,
			'sku'            => 'ABC',
			'price'          => 10,
			'unlimitedStock' => 1,
		]);
		\Craft\craft()->market_variant->save($implicitVariant);

		//another test product
		/** @var Market_ProductModel $product */
		$product = Market_ProductModel::populateModel([
			'typeId'        => $productTypes[0]->id,
			'enabled'       => 1,
			'authorId'      => \Craft\craft()->userSession->id,
			'availableOn'   => new DateTime(),
			'expiresOn'     => NULL,
			'promotable'    => 1,
			'taxCategoryId' => \Craft\craft()->market_taxCategory->getDefaultId(),
		]);

		$product->getContent()->title = 'Really Nice Shirt';

		\Craft\craft()->market_product->save($product);

		//implicit variant
		$implicitVariant = Market_VariantModel::populateModel([
			'productId'      => $product->id,
			'isImplicit'       => 1,
			'sku'            => 'CBA',
			'price'          => 20,
			'unlimitedStock' => 1,
		]);
		\Craft\craft()->market_variant->save($implicitVariant);

	}

	private function paymentMethods()
	{
		$model                  = \Craft\craft()->market_paymentMethod->getByClass('Dummy');
		$model->frontendEnabled = true;
		\Craft\craft()->market_paymentMethod->save($model);
	}

	private function defaultSettings()
	{
		$settings = new Market_SettingsModel();
		$settings->orderPdfPath = 'commerce/_pdf/order';
		$settings->paymentMethod = 'purchase';
		\Craft\craft()->market_settings->save($settings);
	}

}