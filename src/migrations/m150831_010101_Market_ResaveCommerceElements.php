<?php
namespace Craft;

class m150831_010101_Market_ResaveCommerceElements extends BaseMigration
{
	public function safeUp ()
	{
		craft()->tasks->createTask('ResaveElements', Craft::t('Resaving all products'), array(
			'elementType' => 'Market_Product',
			'criteria'    => array(
				'locale' => craft()->i18n->getPrimarySiteLocaleId(),
				'status' => null
			)
		));

		return true;
	}
}