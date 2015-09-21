<?php

namespace Craft;

use Market\Traits\Market_ModelRelationsTrait;

/**
 * Class Market_OrderStatusModel
 *
 * @property int                 id
 * @property string              name
 * @property string              handle
 * @property string              color
 * @property bool                default
 *
 * @property Market_EmailModel[] emails
 *
 * @package Craft
 */
class Market_OrderStatusModel extends BaseModel
{
	use Market_ModelRelationsTrait;

	/**
	 * @return string
	 */
	public function getCpEditUrl ()
	{
		return UrlHelper::getCpUrl('market/settings/orderstatuses/'.$this->id);
	}

	/**
	 * @return string
	 */
	public function __toString ()
	{
		return (string)$this->name;
	}

	/**
	 * @return array
	 */
	public function getEmailsIds ()
	{
		return array_map(function (Market_EmailModel $email)
		{
			return $email->id;
		}, $this->emails);
	}

	/**
	 * @return string
	 */
	public function printName ()
	{
		return sprintf('<span class="market status %s"></span> %s',
			$this->color, $this->name);
	}

	/**
	 * @return array
	 */
	protected function defineAttributes ()
	{
		return [
			'id'      => AttributeType::Number,
			'name'    => [AttributeType::String, 'required' => true],
			'handle'  => [AttributeType::Handle, 'required' => true],
			'color'   => [AttributeType::String, 'default' => 'green'],
			'default' => [
				AttributeType::Bool,
				'default'  => 0,
				'required' => true
			],
		];
	}
}