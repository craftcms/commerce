<?php

namespace Craft;

class Market_SettingsModel extends BaseModel
{

	protected function defineAttributes()
	{
		return array(
			'defaultCurrency' => AttributeType::String
		);
	}

	public function rules()
	{
		return array(
			array('defaultCurrency', 'required')
		);
	}
} 