<?php
namespace Craft;

class m160806_010101_Commerce_RemoveShowInLabel extends BaseMigration
{
	public function safeUp()
	{
		$this->dropColumn('commerce_taxrates','showInLabel');
	}
}
