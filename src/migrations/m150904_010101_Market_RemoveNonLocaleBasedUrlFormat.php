<?php
namespace Craft;

class m150904_010101_Market_RemoveNonLocaleBasedUrlFormat extends BaseMigration
{
	public function safeUp ()
	{
		$table = craft()->db->schema->getTable('craft_market_producttypes');
		if(isset($table->columns['urlFormat'])) {
			$this->dropColumn('market_producttypes', 'urlFormat');
		}

		return true;
	}
}