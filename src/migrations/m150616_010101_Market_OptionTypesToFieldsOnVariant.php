<?php
namespace Craft;

class m150616_010101_Market_OptionTypesToFieldsOnVariant extends BaseMigration
{
	public function array_pluck ($toPluck, $arr) {
		return array_map(function ($item) use ($toPluck) {
			return $item[$toPluck];
		}, $arr);
	}

	public function safeUp()
	{

		//First get all option types and values and make dropdown fields

		$alloptionTypes = craft()->db->createCommand()->select('*')->from('market_optiontypes')->queryAll();

		$fields = [];
		$count = 0;
		foreach($alloptionTypes as $optionType){
			$count++;
			// Need to know if the field name is already taken
			$exists = craft()->fields->getFieldByHandle(ElementHelper::createSlug($optionType['handle']));
			//make a new field name is field name already taken
			if($exists && $exists->id){
				$optionType['handle'] = $optionType['handle'].$count;
			}

			//Make a new field
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

			//save the field
			craft()->fields->saveField($field);

			$fields[] = $field;
		}

		return true;
	}
}