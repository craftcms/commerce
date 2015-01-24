<?php

namespace Craft;

/**
 * Class Stripey_CountryRecord
 * @property int $id
 * @property string $name
 * @property string $iso
 * @property bool $stateRequired
 * @package Craft
 */
class Stripey_CountryRecord extends BaseRecord
{

    public function getTableName()
    {
        return 'stripey_countries';
    }

    public function defineIndexes()
    {
        return array(
            array('columns' => array('name'), 'unique' => true),
            array('columns' => array('iso'), 'unique' => true),
        );
    }


    protected function defineAttributes()
    {
        return array(
            'name' => array(AttributeType::String, 'required' => true),
            'iso'  => array(AttributeType::String, 'required' => true, 'maxLength' => 2),
            'stateRequired' => array(AttributeType::Bool, 'required' => true, 'default' => 0),
        );
    }

    protected function beforeSave()
    {
        $this->iso = strtoupper($this->iso);
        return parent::beforeSave();
    }


}