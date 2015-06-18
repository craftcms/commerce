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

			//keep the ones we made for later
			$fields[] = $field;
		}


		//Now make a fieldLayout for Variants and add the fields created above
		$productTypes = craft()->market_productType->getAll();

		foreach ($productTypes as $productType) {

			// Since the save on producttype throws away the layout everytime, we need to rebuild it.
			$productLayout        = $productType->asa('productFieldLayout')->getFieldLayout();
			$layoutData = [];
			foreach($productLayout->getTabs() as $tab ){
				foreach($tab->getFields() as $field){
					$layoutData[$tab->name][] = (int)$field->fieldId;
				}
			}

			// Assemble the same layout that existed for products
			$productFieldLayout       = craft()->fields->assembleLayout($layoutData, []);
			$productFieldLayout->type = 'Market_Product';
			$productType->asa('productFieldLayout')->setFieldLayout($productFieldLayout);

			// Add the fields for each option type to every variant fieldlayout
			$fieldIds = $this->array_pluck("id", $fields);
			$variantLayoutData        = ['Content' => $fieldIds];
			$variantFieldLayout       = craft()->fields->assembleLayout($variantLayoutData, []);
			$variantFieldLayout->type = 'Market_Variant';
			$productType->asa('variantFieldLayout')->setFieldLayout($variantFieldLayout);

			craft()->market_productType->save($productType);
		}

//		$all = <<<EOT
//select
//vv.id as id,
//vv.variantId as variantId,
//v.productId as variantProductId,
//p.typeId as productTypeId,
//ot.handle as optionTypeName,
//ov.id as optionValueId,
//ov.name as optionValueName,
//ov.displayName as optionValueDisplayName
//from craft_market_variant_optionvalues vv
//	left join craft_market_variants v
//		on vv.variantId = v.id
//	left join craft_market_products p
//		on v.productId = p.id
//	left join craft_market_optionvalues ov
//		on vv.optionValueId = ov.id
//	left join craft_market_optiontypes ot
//		on ov.optionTypeId = ot.id
//EOT;
//
//		$allData = craft()->db->createCommand($all)->queryAll();
//
//		foreach($allData as $item){
//			$variant = craft()->market_variant->getById($item['variantId']);
//			$variant->getContent()->{$item['optionTypeName']} = $item['optionValueName'];
//			craft()->market_variant->save($variant);
//		}


		return true;
	}
}