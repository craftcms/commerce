<?php
namespace Craft;

class m160229_010101_Commerce_ShippingZone extends BaseMigration
{
	public function safeUp()
	{
		// Create the craft_commerce_shippingzones table
		craft()->db->createCommand()->createTable('commerce_shippingzones', array(
			'name'         => array('required' => true),
			'description'  => array(),
			'countryBased' => array('maxLength' => 1, 'default' => 1, 'required' => true, 'column' => 'tinyint', 'unsigned' => true),
		), null, true);

		// Add indexes to craft_commerce_shippingzones
		craft()->db->createCommand()->createIndex('commerce_shippingzones', 'name', true);

		// Create the craft_commerce_shippingzone_countries table
		craft()->db->createCommand()->createTable('commerce_shippingzone_countries', array(
			'shippingZoneId' => array('column' => 'integer', 'required' => true),
			'countryId'      => array('column' => 'integer', 'required' => true),
		), null, true);

		// Add indexes to craft_commerce_shippingzone_countries
		craft()->db->createCommand()->createIndex('commerce_shippingzone_countries', 'shippingZoneId', false);
		craft()->db->createCommand()->createIndex('commerce_shippingzone_countries', 'countryId', false);
		craft()->db->createCommand()->createIndex('commerce_shippingzone_countries', 'shippingZoneId,countryId', true);

		// Add foreign keys to craft_commerce_shippingzone_countries
		craft()->db->createCommand()->addForeignKey('commerce_shippingzone_countries', 'shippingZoneId', 'commerce_shippingzones', 'id', 'CASCADE', 'CASCADE');
		craft()->db->createCommand()->addForeignKey('commerce_shippingzone_countries', 'countryId', 'commerce_countries', 'id', 'CASCADE', 'CASCADE');

		// Create the craft_commerce_shippingzone_states table
		craft()->db->createCommand()->createTable('commerce_shippingzone_states', array(
			'shippingZoneId' => array('column' => 'integer', 'required' => true),
			'stateId'        => array('column' => 'integer', 'required' => true),
		), null, true);

		// Add indexes to craft_commerce_shippingzone_states
		craft()->db->createCommand()->createIndex('commerce_shippingzone_states', 'shippingZoneId', false);
		craft()->db->createCommand()->createIndex('commerce_shippingzone_states', 'stateId', false);
		craft()->db->createCommand()->createIndex('commerce_shippingzone_states', 'shippingZoneId,stateId', true);

		// Add foreign keys to craft_commerce_shippingzone_states
		craft()->db->createCommand()->addForeignKey('commerce_shippingzone_states', 'shippingZoneId', 'commerce_shippingzones', 'id', 'CASCADE', 'CASCADE');
		craft()->db->createCommand()->addForeignKey('commerce_shippingzone_states', 'stateId', 'commerce_states', 'id', 'CASCADE', 'CASCADE');


		$this->addColumnAfter('commerce_shippingrules','shippingZoneId',ColumnType::Int,'methodId');

		craft()->db->createCommand()->addForeignKey('commerce_shippingrules', 'shippingZoneId', 'commerce_shippingzones', 'id', 'SET NULL', null);

		$shippingRules = craft()->db->createCommand()
			->select('*')
			->from('commerce_shippingrules')
			->queryAll();

		foreach ($shippingRules as $shippingRule)
		{
			$countryBased = (bool) ($shippingRule['countryId']);
			$stateBased = (bool) ($shippingRule['stateId']);

			if ($countryBased || $stateBased)
			{

				$shippingZone = [
					'name'         => $shippingRule['name']." ".Craft::t('Zone'),
					'description'  => $shippingRule['description'],
					'countryBased' => ($stateBased ? false : true)
				];

				craft()->db->createCommand()->insert('commerce_shippingzones',$shippingZone);
				$id = craft()->db->getLastInsertID();

				if($countryBased){
					$shippingCountry = [
						'shippingZoneId' => $id,
						'countryId' => $shippingRule['countryId']
					];
					craft()->db->createCommand()->insert('commerce_shippingzone_countries',$shippingCountry);
				}

				if($stateBased){
					$shippingState = [
						'shippingZoneId' => $id,
						'stateId' => $shippingRule['stateId']
					];
					craft()->db->createCommand()->insert('commerce_shippingzone_states',$shippingState);
				}

				$data = [
					'shippingZoneId' => $id
				];

				craft()->db->createCommand()->update('commerce_shippingrules', $data, 'id = :idx', [':idx' => $shippingRule['id']]);
			}
		}

		MigrationHelper::dropForeignKeyIfExists('commerce_shippingrules',['countryId']);
		MigrationHelper::dropForeignKeyIfExists('commerce_shippingrules',['stateId']);

		craft()->db->createCommand()->dropColumn('commerce_shippingrules','countryId');
		craft()->db->createCommand()->dropColumn('commerce_shippingrules','stateId');
	}
}
