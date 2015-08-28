<?php
namespace Craft;

class m150827_010102_Market_RefreshShapshot extends BaseMigration
{
	public function safeUp ()
	{

		$table = craft()->db->schema->getTable('craft_market_lineitems');
		if(!isset($table->columns['salePrice'])) {
			$this->addColumnAfter('market_lineitems', 'salePrice', ['column' => ColumnType::Decimal, 'length' => '14', 'decimals' => '4'], 'saleAmount');
		}


		$lineItems = craft()->db->createCommand()->select('*')->from('market_lineitems')->where('purchasableId is not null')->queryAll();

		foreach ($lineItems as $lineItem)
		{

			$purchasable = craft()->market_variant->getById($lineItem['purchasableId']);

			$onSale = false;
			if ($lineItem['saleAmount'] != 0)
			{
				$onSale = true;
			}

			// ensure all snapshots from previous orders have the purchasable interface data in them.
			$snapshot = [
				'price'         => $purchasable->getPrice(),
				'sku'           => $purchasable->getSku(),
				'description'   => $purchasable->getDescription(),
				'purchasableId' => $purchasable->getPurchasableId(),
				'cpEditUrl'     => '#',
				'onSale'        => $onSale
			];

			// Add our purchasable data to the snapshot
			$snapshot = array_merge($purchasable->getSnapShot(), $snapshot);

			$snapshot_json = json_encode($snapshot);

			$salePrice = $lineItem['saleAmount'] + $lineItem['price'];

			craft()->db->createCommand()->insertOrUpdate('market_lineitems', ['id' => $lineItem['id'], 'purchasableId' => $lineItem['purchasableId'], 'orderId' => $lineItem['orderId']], ['snapshot' => $snapshot_json, 'salePrice' => $salePrice]);
		}

		return true;
	}
}