<?php
namespace Craft;

class m160425_010101_Commerce_DeleteCountriesAndStates extends BaseMigration
{
	public function safeUp()
	{
		// Drop FKs
		$table = MigrationHelper::getTable('commerce_addresses');
		MigrationHelper::dropAllForeignKeysOnTable($table);

		// Alter to allow nulls
		craft()->db->createCommand()->alterColumn('commerce_addresses','countryId',ColumnType::Int);

		// Add FKs back
		craft()->db->createCommand()->addForeignKey('commerce_addresses', 'countryId', 'commerce_countries', 'id', 'SET NULL');
		craft()->db->createCommand()->addForeignKey('commerce_addresses', 'stateId', 'commerce_states', 'id', 'SET NULL');
	}
}
