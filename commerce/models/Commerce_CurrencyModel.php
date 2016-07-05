<?php
namespace Craft;

use JsonSerializable;

/**
 * Currency model.
 *
 * @property int    $id
 * @property string $name
 * @property string $iso
 * @property float $rate
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.models
 * @since     1.0
 */
class Commerce_CurrencyModel extends BaseModel implements JsonSerializable
{
	/**
	 * @return string
	 */
	public function getCpEditUrl()
	{
		return UrlHelper::getCpUrl('commerce/settings/currencies/'.$this->id);
	}

	/**
	 * @return string
	 */
	function __toString()
	{
		return $this->name;
	}

	/**
	 * @return array
	 */
	function jsonSerialize()
	{
		$data = [];
		$data['id'] = $this->getAttribute('id');
		$data['name'] = $this->getAttribute('name');
		$data['iso'] = $this->getAttribute('iso');
		$data['rate'] = $this->getAttribute('rate');

		return $data;
	}

	/**
	 * @return array
	 */
	protected function defineAttributes()
	{
		return [
			'id'   => AttributeType::Number,
			'name' => AttributeType::String,
			'iso'  => AttributeType::String,
			'rate'  => AttributeType::Number
		];
	}

}