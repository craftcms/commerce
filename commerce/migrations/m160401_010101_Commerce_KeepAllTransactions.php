<?php
namespace Craft;

class m160401_010101_Commerce_KeepAllTransactions extends BaseMigration
{
	public function safeUp()
	{
		MigrationHelper::dropForeignKeyIfExists('commerce_transactions',['userId']);
		craft()->db->createCommand()->addForeignKey('commerce_transactions', 'userId', 'users', 'id', 'SET NULL', null);
	}
}
