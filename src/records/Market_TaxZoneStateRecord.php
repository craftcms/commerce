<?php
namespace Craft;

class Market_TaxZoneStateRecord extends BaseRecord
{

	public function getTableName()
	{
		return "market_taxzone_states";
	}

	public function defineIndexes()
	{
		return [
			['columns' => ['taxZoneId']],
			['columns' => ['stateId']],
			['columns' => ['taxZoneId', 'stateId'], 'unique' => true],
		];
	}


	/**
	 * @inheritDoc BaseRecord::defineRelations()
	 *
	 * @return array
	 */
	public function defineRelations()
	{
		return [
			'taxZone' => [static::BELONGS_TO, 'Market_TaxZoneRecord', 'onDelete' => self::CASCADE, 'onUpdate' => self::CASCADE, 'required' => true],
			'state'   => [static::BELONGS_TO, 'Market_StateRecord', 'onDelete' => self::CASCADE, 'onUpdate' => self::CASCADE, 'required' => true],
		];
	}

}