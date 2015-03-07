<?php

namespace Market\Seed;

use Craft\DateTime;
use Craft\FieldLayoutModel;
use Craft\Market_DiscountRecord;
use Craft\Market_OptionTypeModel;
use Craft\Market_OptionValueModel;
use Craft\Market_ProductModel;
use Craft\Market_ProductTypeModel;
use Craft\Market_SaleRecord;
use Craft\Market_ShippingMethodRecord;
use Craft\Market_ShippingRuleRecord;
use Craft\Market_TaxCategoryModel;
use Craft\Market_TaxRateModel;
use Craft\Market_TaxZoneModel;
use Craft\Market_VariantModel;
use Market\Product\Creator;

/**
 * Test Data useful during development
 */
class Market_TestSeeder implements Market_SeederInterface
{
	public function seed()
	{
		$this->productTypes();
		$this->optionTypes();
		$this->taxCategories();
		$this->products();
		$this->taxZones();
		$this->taxRates();
		$this->discounts();
		$this->shippingRules();
		$this->sales();
		$this->paymentMethods();
	}

	/**
	 * @throws \Craft\Exception
	 * @throws \Exception
	 */
	private function optionTypes()
	{
		//color
		$colorOptionType         = new Market_OptionTypeModel;
		$colorOptionType->name   = 'Color';
		$colorOptionType->handle = 'color';
		\Craft\craft()->market_optionType->save($colorOptionType);

		$colorOptionValues                 = [new Market_OptionValueModel, new Market_OptionValueModel];
		$colorOptionValues[0]->name        = 'blue';
		$colorOptionValues[0]->displayName = 'blue';
		$colorOptionValues[0]->position    = 1;
		$colorOptionValues[1]->name        = 'red';
		$colorOptionValues[1]->displayName = 'red';
		$colorOptionValues[1]->position    = 2;

		\Craft\craft()->market_optionValue->saveOptionValuesForOptionType($colorOptionType, $colorOptionValues);

		//size
		$sizeOptionType         = new Market_OptionTypeModel;
		$sizeOptionType->name   = 'Size';
		$sizeOptionType->handle = 'size';

		\Craft\craft()->market_optionType->save($sizeOptionType);

		$sizeOptionValues                 = [new Market_OptionValueModel, new Market_OptionValueModel];
		$sizeOptionValues[0]->name        = 'xl';
		$sizeOptionValues[0]->displayName = 'xl';
		$sizeOptionValues[0]->position    = 1;
		$sizeOptionValues[1]->name        = 'm';
		$sizeOptionValues[1]->displayName = 'm';
		$sizeOptionValues[1]->position    = 2;

		\Craft\craft()->market_optionValue->saveOptionValuesForOptionType($sizeOptionType, $sizeOptionValues);
	}

	/**
	 * @throws \Craft\Exception
	 * @throws \Exception
	 */
	private function productTypes()
	{
		$productType         = new Market_ProductTypeModel;
		$productType->name   = 'Default Product';
		$productType->handle = 'defaultProduct';
		$productType->handle = 'normal';

		$fieldLayout = FieldLayoutModel::populateModel(['type' => 'Market_Product']);
		$productType->setFieldLayout($fieldLayout);
		\Craft\craft()->market_productType->save($productType);
	}

	/**
	 * @throws \Craft\Exception
	 */
	private function taxCategories()
	{
		$taxCategories = Market_TaxCategoryModel::populateModels([[
			'name'    => 'General',
			'default' => 1,
		], [
			'name'    => 'Food',
			'default' => 0,
		], [
			'name'    => 'Clothes',
			'default' => 0,
		]]);

		foreach ($taxCategories as $category) {
			\Craft\craft()->market_taxCategory->save($category);
		}
	}

	/**
	 * @throws \Craft\Exception
	 * @throws \Exception
	 */
	private function taxZones()
	{
		//europe
		$germany     = \Craft\craft()->market_country->getByAttributes(['name' => 'Germany']);
		$italy       = \Craft\craft()->market_country->getByAttributes(['name' => 'Italy']);
		$france      = \Craft\craft()->market_country->getByAttributes(['name' => 'France']);
		$euCountries = [$germany->id, $italy->id, $france->id];

		$euZone = Market_TaxZoneModel::populateModel([
			'name'         => 'Europe',
			'countryBased' => true,
		]);

		\Craft\craft()->market_taxZone->save($euZone, $euCountries, []);

		//usa states
		$florida   = \Craft\craft()->market_state->getByAttributes(['name' => 'Florida']);
		$alaska    = \Craft\craft()->market_state->getByAttributes(['name' => 'Alaska']);
		$texas     = \Craft\craft()->market_state->getByAttributes(['name' => 'Texas']);
		$usaStates = [$florida->id, $alaska->id, $texas->id];

		$usaZone = Market_TaxZoneModel::populateModel([
			'name'         => 'USA',
			'countryBased' => false,
		]);

		\Craft\craft()->market_taxZone->save($usaZone, [], $usaStates);
	}

	/**
	 * @throws \Craft\Exception
	 */
	private function taxRates()
	{
		$zones      = \Craft\craft()->market_taxZone->getAll(false);
		$categories = \Craft\craft()->market_taxCategory->getAll();

		foreach ($zones as $zone) {
			foreach ($categories as $category) {
				$rate = Market_TaxRateModel::populateModel([
					'name'          => $category->name . '-' . $zone->name,
					'rate'          => mt_rand(1, 10000) / 100000,
					'include'       => $zone->default ? (mt_rand(1, 2) - 1) : 0,
					'taxCategoryId' => $category->id,
					'taxZoneId'     => $zone->id,
				]);

				\Craft\craft()->market_taxRate->save($rate);
			}
		}
	}

	/**
	 * @throws \Craft\HttpException
	 */
	private function products()
	{
		$productTypes = \Craft\craft()->market_productType->getAll();

		//first test product
		$product = Market_ProductModel::populateModel([
			'typeId'        => $productTypes[0]->id,
			'enabled'       => 1,
			'authorId'      => \Craft\craft()->userSession->id,
			'availableOn'   => new DateTime(),
			'expiresOn'     => NULL,
			'taxCategoryId' => \Craft\craft()->market_taxCategory->getDefaultId(),
		]);

		$product->getContent()->title = 'Test Product';

		$productCreator = new Creator();
		$productCreator->save($product);

		//master variant
		$masterVariant = Market_VariantModel::populateModel([
			'productId'      => $product->id,
			'isMaster'       => 1,
			'sku'            => 'testSku',
			'price'          => 111,
			'unlimitedStock' => 1,
		]);
		\Craft\craft()->market_variant->save($masterVariant);

		//option types
		$optionTypes = \Craft\craft()->market_optionType->getAll();
		$ids         = array_map(function ($type) {
			return $type->id;
		}, $optionTypes);
		\Craft\craft()->market_product->setOptionTypes($product->id, $ids);

		//another test product
		$product = Market_ProductModel::populateModel([
			'typeId'        => $productTypes[0]->id,
			'enabled'       => 1,
			'authorId'      => \Craft\craft()->userSession->id,
			'availableOn'   => new DateTime(),
			'expiresOn'     => NULL,
			'taxCategoryId' => \Craft\craft()->market_taxCategory->getDefaultId(),
		]);

		$product->getContent()->title = 'Another Test Product';

		$productCreator = new Creator();
		$productCreator->save($product);

		//master variant
		$masterVariant = Market_VariantModel::populateModel([
			'productId'      => $product->id,
			'isMaster'       => 1,
			'sku'            => 'newTestSku',
			'price'          => 200,
			'unlimitedStock' => 1,
		]);
		\Craft\craft()->market_variant->save($masterVariant);

		//option types
		$optionTypes = \Craft\craft()->market_optionType->getAll();
		$ids         = array_map(function ($type) {
			return $type->id;
		}, $optionTypes);
		\Craft\craft()->market_product->setOptionTypes($product->id, $ids);
	}

	/**
	 * Discounts
	 */
	private function discounts()
	{
		$discount             = new Market_DiscountRecord();
		$discount->attributes = [
			'name'            => 'Global Test Discount',
			'code'            => 'test_code',
			'enabled'         => 1,
			'baseDiscount'    => -5,
			'perItemDiscount' => -1,
			'percentDiscount' => -0.01,
			'allGroups'       => 1,
			'allProducts'     => 1,
			'allProductTypes' => 1,
		];
		$discount->save();
	}

	private function shippingRules()
	{
		$method = Market_ShippingMethodRecord::model()->find();

		$rule                 = new Market_ShippingRuleRecord();
		$rule->name           = 'Global Shipping Rule';
		$rule->methodId       = $method->id;
		$rule->priority       = 1;
		$rule->enabled        = 1;
		$rule->baseRate       = 10;
		$rule->percentageRate = 0.01;
		$rule->weightRate     = 0.10;
		$rule->perItemRate    = 1;
		$rule->save();
	}

	private function sales()
	{
		$sale             = new Market_SaleRecord();
		$sale->attributes = [
			'name'            => 'Global Test Sale',
			'enabled'         => 1,
			'discountType'    => 'percent',
			'discountAmount'  => -0.1,
			'allGroups'       => 1,
			'allProducts'     => 1,
			'allProductTypes' => 1,
		];
		$sale->save();
	}

	private function paymentMethods()
	{
		$model                  = \Craft\craft()->market_paymentMethod->getByClass('Stripe');
		$model->frontendEnabled = true;
		$model->settings        = [
			'apiKey' => 'sk_test_8Lvmi5qDkbHRLCsyexhvOGuj',
		];
		\Craft\craft()->market_paymentMethod->save($model);
	}
}