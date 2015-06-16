<?php
namespace Craft;

class m150616_010101_Market_OptionTypesToFieldsOnVariant extends BaseMigration
{
	public function safeUp()
	{

		$alloptionTypes = craft()->db->createCommand()->select('*')->from('market_optiontypes')->queryAll();

		$fields = [];
		foreach($alloptionTypes as $optionType){
			$field          = new FieldModel();
			$field->groupId = 1;
			$field->context = 'global';
			$field->name    = $optionType['name'];
			$field->handle  = ElementHelper::createSlug($optionType['handle']);
			$field->type    = "Dropdown";

			// Add the values
			$optionValues = craft()->db->createCommand()->select('*')->from('market_optionvalues')->where('optionTypeId=:id', array(':id'=>$optionType['id']))->queryAll();
			$options        = [];
			foreach ($optionValues as $ov) {
				$value                = ['label' => $ov['displayName'], 'value' => $ov['name']];
				$options['options'][] = $value;
			}
			$field->settings = $options;
			craft()->fields->saveField($field);
			$fields[] = $field;
		}

		$productTypes = craft()->market_productType->getAll();
		foreach($productTypes as $productType){

		}

		return true;
	}
}