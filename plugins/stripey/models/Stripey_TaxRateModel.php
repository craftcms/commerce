<?php

namespace Craft;

/**
 * Class Stripey_TaxRateModel
 *
 * @property int $id
 * @property string $name
 * @property float $rate
 * @property bool $include
 * @property bool $showInLabel
 * @property int $taxZoneId
 * @property int $taxCategoryId
 * @package Craft
 */
class Stripey_TaxRateModel extends BaseModel
{
    protected $modelRecord = 'Stripey_TaxRateRecord';
    /** @var Stripey_TaxZoneModel */
    public $taxZone;
    /** @var Stripey_TaxCategoryModel */
    public $taxCategory;

    public function getCpEditUrl()
    {
        return UrlHelper::getCpUrl('stripey/settings/taxrates/' . $this->id);
    }

    protected function defineAttributes()
    {
        return array(
            'id'            => AttributeType::Number,
            'name'          => AttributeType::String,
            'rate'          => array(AttributeType::Number, 'default' => .05),
            'include'       => AttributeType::Bool,
            'showInLabel'   => AttributeType::Bool,

            'taxCategoryId' => AttributeType::Number,
            'taxZoneId'     => AttributeType::Number,
        );
    }

    public static function populateModel($values)
    {
        $model = parent::populateModel($values);

        if(is_object($values) && $values instanceof Stripey_TaxRateRecord) {
            $model->taxZone = Stripey_TaxZoneModel::populateModel($values->taxZone);
            $model->taxCategory = Stripey_TaxCategoryModel::populateModel($values->taxCategory);
        }

        return $model;
    }


}