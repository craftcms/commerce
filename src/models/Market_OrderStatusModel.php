<?php

namespace Craft;

use Market\Traits\Market_ModelRelationsTrait;

/**
 * Class Market_OrderStatusModel
 *
 * @property int                   id
 * @property string                name
 * @property int                   orderTypeId
 * @property string                handle
 * @property string                color
 * @property bool                  default
 *
 * @property Market_OrderTypeModel orderType
 * @property Market_EmailModel[]   emails
 *
 * @package Craft
 */
class Market_OrderStatusModel extends BaseModel
{
    use Market_ModelRelationsTrait;

	public function getCpEditUrl()
	{
		return UrlHelper::getCpUrl('market/settings/ordertypes/' . $this->orderTypeId . '/orderstatuses/' . $this->id);
	}

	public function __toString(){
		return (string) $this->name;
	}

	protected function defineAttributes()
	{
        return [
            'id'            => AttributeType::Number,
            'name'          => [AttributeType::String, 'required' => true],
            'orderTypeId'   => [AttributeType::Number, 'required' => true],
            'handle'        => [AttributeType::Handle, 'required' => true],
            'color'         => [AttributeType::String, 'column' => ColumnType::Char, 'length' => 7, 'required' => true],
            'default'       => [AttributeType::Bool, 'default' => 0, 'required' => true],
        ];
	}

    /**
     * @return array
     */
    public function getEmailsIds()
    {
        return array_map(function(Market_EmailModel $email){
            return $email->id;
        }, $this->emails);
    }

    /**
     * @return string
     */
    public function printName()
    {
        return sprintf('<span style="color: %s">&block;</span> %s', $this->color, $this->name);
    }
}