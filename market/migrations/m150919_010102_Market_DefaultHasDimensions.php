<?php
namespace Craft;

class m150919_010102_Market_DefaultHasDimensions extends BaseMigration
{
	public function safeUp ()
	{
		craft()->db->createCommand()->update('market_producttypes', ['hasDimensions' => 1]);

		return true;
	}
}