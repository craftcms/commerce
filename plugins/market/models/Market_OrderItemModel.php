<?php

namespace Craft;

class Market_OrderItemModel extends BaseModel
{
	protected function defineAttributes()
	{
		return [
			"variant" => AttributeType::Mixed
		];
	}
}