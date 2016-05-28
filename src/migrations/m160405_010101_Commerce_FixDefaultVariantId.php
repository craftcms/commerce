<?php
namespace Craft;

class m160405_010101_Commerce_FixDefaultVariantId extends BaseMigration
{
	public function safeUp()
	{

		// Find all products that do not have a default variant set.
		$products = craft()->db->createCommand()
			->select('*')
			->from('commerce_products')
			->where('defaultVariantId IS NULL')
			->queryAll();

		foreach ($products as $product)
		{
			// find products variants
			$variants = craft()->db->createCommand()
				->select('*')
				->from('commerce_variants')
				->where('productId = '.$product['id'])
				->order('sortOrder')
				->queryAll();

			$defaultVariant = null;

			// Make the first variant (or the last one that says it isDefault) the default.
			foreach ($variants as $variant)
			{
				if ($defaultVariant === null || $variant['isDefault'])
				{
					$defaultVariant = $variant;
				}
			}

			// update the product with the default variants ID
			if ($defaultVariant !== null)
			{
				craft()->db->createCommand()->update('commerce_products', ['defaultVariantId' => $defaultVariant['id']], ['id' => $product['id']]);
			}
		}
	}
}
