<?php

namespace Craft;

/**
 * Class Stripey_TaxZoneModel
 *
 * @property int $id
 * @property string $name
 * @property string $description
 * @property bool $countryBased
 * @package Craft
 */
class Stripey_TaxZoneModel extends BaseModel
{
    /** @var Stripey_CountryModel[] */
    private $countries = [];
    /** @var Stripey_StateModel[] */
    private $states = [];

    public function getCpEditUrl()
    {
        return UrlHelper::getCpUrl('stripey/settings/taxzones/' . $this->id);
    }

    protected function defineAttributes()
    {
        return array(
            'id'   => AttributeType::Number,
            'name' => AttributeType::String,
            'description' => AttributeType::String,
            'countryBased' => array(AttributeType::Bool, 'default' => 1),
        );
    }

    public static function populateModel($values)
    {
        $model = parent::populateModel($values);
        if(is_object($values) && $values instanceof Stripey_TaxZoneRecord) {

            $model->countries = Stripey_CountryModel::populateModels($values->countries);
            $model->states = Stripey_StateModel::populateModels($values->states);
        }
        return $model;
    }

    /**
     * @return array
     */
    public function getCountriesIds()
    {
        return array_map(function($country) {
            return $country->id;
        }, $this->countries);
    }

    /**
     * @return array
     */
    public function getStatesIds()
    {
        return array_map(function($state) {
            return $state->id;
        }, $this->states);
    }

    public function getCountriesNames()
    {
        return array_map(function($country) {
            return $country->name;
        }, $this->countries);
    }

    public function getStatesNames()
    {
        return array_map(function($state) {
            return $state->formatName();
        }, $this->states);
    }
}