<?php
namespace Craft;

class Commerce_TaxZoneStateRecord extends BaseRecord
{

	/**
	 * @return string
	 */
	public function getTableName ()
	{
		return "commerce_taxzone_states";
	}

	/**
	 * @return array
	 */
	public function defineIndexes ()
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
	public function defineRelations ()
	{
		return [
			'taxZone' => [
				static::BELONGS_TO,
				'Commerce_TaxZoneRecord',
				'onDelete' => self::CASCADE,
				'onUpdate' => self::CASCADE,
				'required' => true
			],
			'state'   => [
				static::BELONGS_TO,
				'Commerce_StateRecord',
				'onDelete' => self::CASCADE,
				'onUpdate' => self::CASCADE,
				'required' => true
			],
		];
	}

}