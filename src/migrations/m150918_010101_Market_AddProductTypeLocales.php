<?php
namespace Craft;

class m150918_010101_Market_AddProductTypeLocales extends BaseMigration
{
	public function safeUp ()
	{

		// Create the craft_productypes_i18n table
		craft()->db->createCommand()->createTable('market_producttypes_i18n', array(
			'productTypeId' => array('column' => 'integer', 'required' => true),
			'locale'        => array('column' => 'locale', 'required' => true),
			'urlFormat'     => array(),
		), null, true);

		// Add indexes to craft_productypes_i18n
		craft()->db->createCommand()->createIndex('market_producttypes_i18n', 'productTypeId,locale', true);

		// Add foreign keys to craft_productypes_i18n
		craft()->db->createCommand()->addForeignKey('market_producttypes_i18n', 'productTypeId', 'market_producttypes', 'id', 'CASCADE', null);
		craft()->db->createCommand()->addForeignKey('market_producttypes_i18n', 'locale', 'locales', 'locale', 'CASCADE', 'CASCADE');

		$localeIds = craft()->i18n->getSiteLocales();
		$productTypes = craft()->db->createCommand()->select('*')->from('market_producttypes')->queryAll();

		foreach($localeIds as $locale){
			foreach($productTypes as $productType){
				$locale = (string) $locale;
				craft()->db->createCommand()->insert('market_producttypes_i18n',['productTypeId'=>$productType['id'],'locale'=>$locale,'urlFormat'=>$productType['urlFormat']]);
			}
		}

		return true;

	}
}