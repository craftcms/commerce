<?php
namespace Craft;

class m151210_010101_Commerce_FixMissingLineItemDimensionData extends BaseMigration
{
	public function safeUp()
	{

		$variants = craft()->db->createCommand()
			->select('*')
			->from('commerce_variants')
			->queryAll();

		foreach ($variants as $variant)
		{
			$data = [
				'weight' => (float) $variant['weight'],
				'height' => (float) $variant['height'],
				'length' => (float) $variant['length'],
				'width'  => (float) $variant['width']
			];

			craft()->db->createCommand()->update('commerce_lineitems', $data, 'purchasableId = :idx', [':idx' => $variant['id']]);
		}
	}
}
