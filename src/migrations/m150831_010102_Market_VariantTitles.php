<?php
namespace Craft;

class m150831_010102_Market_VariantTitles extends BaseMigration
{
	public function safeUp ()
	{

		$table = craft()->db->schema->getTable('craft_market_producttypes');
		if (!isset($table->columns['titleFormat']))
		{
			$this->addColumnAfter('market_producttypes', 'titleFormat', ColumnType::Varchar, 'urlFormat');
		}

		$productTypes = craft()->db->createCommand()->select('*')->from('market_producttypes')->where('hasVariants = 1')->queryAll();

		foreach($productTypes as $productType){
			craft()->db->createCommand()->update('market_producttypes',['titleFormat' => '{sku}'], 'id=:id', [':id' => $productType['id']]);
		}

		craft()->tasks->createTask('ResaveElements', Craft::t('Resaving all variants'), [
			'elementType' => 'Market_Variant',
			'criteria'    => [
				'locale' => craft()->i18n->getPrimarySiteLocaleId(),
				'status' => null
			]
		]);

		return true;
	}
}