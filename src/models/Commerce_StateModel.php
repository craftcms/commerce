<?php
namespace Craft;

use Commerce\Traits\Commerce_ModelRelationsTrait;

/**
 * State model.
 *
 * @property int                    $id
 * @property string                 $name
 * @property string                 $abbreviation
 * @property int                    $countryId
 *
 * @property Commerce_CountryRecord $country
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://craftcommerce.com/license Craft Commerce License Agreement
 * @see       http://craftcommerce.com
 * @package   craft.plugins.commerce.models
 * @since     1.0
 */
class Commerce_StateModel extends BaseModel
{
	use Commerce_ModelRelationsTrait;

	/**
	 * @return string
	 */
	public function getCpEditUrl ()
	{
		return UrlHelper::getCpUrl('commerce/settings/states/'.$this->id);
	}

	/**
	 * @return string
	 */
	function __toString ()
	{
		return (string)$this->name;
	}

	/**
	 * @return string
	 */
	public function formatName ()
	{
		return $this->name.' ('.$this->country->name.')';
	}

	/**
	 * @return array
	 */
	protected function defineAttributes ()
	{
		return [
			'id'           => AttributeType::Number,
			'name'         => AttributeType::String,
			'abbreviation' => AttributeType::String,
			'countryId'    => AttributeType::Number,
		];
	}
}