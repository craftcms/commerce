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
				'weight' => $variant['weight'] * 1,
				'height' => $variant['height'] * 1,
				'length' => $variant['length'] * 1,
				'width'  => $variant['width'] * 1
			];

			craft()->db->createCommand()->update('commerce_lineitems', $data, 'purchasableId = :idx', [':idx' => $variant['id']]);
		}
	}
}
