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
		return array(
			array('columns' => array('taxZoneId')),
			array('columns' => array('stateId')),
			array('columns' => array('taxZoneId', 'stateId'), 'unique' => true),
		);
	}


	/**
	 * @inheritDoc BaseRecord::defineRelations()
	 *
	 * @return array
	 */
	public function defineRelations()
	{
		return array(
			'taxZone' => array(static::BELONGS_TO, 'Market_TaxZoneRecord', 'onDelete' => self::CASCADE, 'onUpdate' => self::CASCADE, 'required' => true),
			'state'   => array(static::BELONGS_TO, 'Market_StateRecord', 'onDelete' => self::CASCADE, 'onUpdate' => self::CASCADE, 'required' => true),
		);
	}

}