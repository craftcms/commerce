<?php

namespace Craft;

/**
 * Class Stripey_StateModel
 *
 * @property int $id
 * @property string $name
 * @property string $abbreviation
 * @property int $countryId
 * @property string $countryName
 * @package Craft
 */
class Stripey_StateModel extends BaseModel
{
    protected $modelRecord = 'Stripey_StateRecord';

    public function getCpEditUrl()
    {
        return UrlHelper::getCpUrl('stripey/settings/states/' . $this->id);
    }

    protected function defineAttributes()
    {
        return array(
            'id'            => AttributeType::Number,
            'name'          => AttributeType::String,
            'abbreviation'  => AttributeType::String,
            'countryId'     => AttributeType::Number,
            'countryName'     => AttributeType::String,
        );
    }

    public static function populateModel($values)
    {
        $model = parent::populateModel($values);
        if(is_object($values)) {
            $model->countryName = $values->country->name;
        }

        return $model;
    }

    public function formatName()
    {
        return $this->name . ' (' . $this->countryName . ')';
    }
}