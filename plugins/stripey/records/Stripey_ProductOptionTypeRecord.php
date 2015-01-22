<?php
namespace Craft;


class Stripey_ProductOptionTypeRecord extends BaseRecord
{

    public function getTableName()
    {
        return "stripey_product_optiontypes";
    }
    // Protected Methods
    // =========================================================================

    /**
     * @inheritDoc BaseRecord::defineAttributes()
     *
     * @return array
     */
    protected function defineAttributes()
    {
        return array(
            'optionTypeId' => AttributeType::Number,
            'productId'    => AttributeType::Number,
        );
    }


    /**
     * @inheritDoc BaseRecord::defineRelations()
     *
     * @return array
     */
    public function defineRelations()
    {
        return array(
            'product'     => array(static::BELONGS_TO, 'Stripey_ProductRecord'),
            'optionType'        => array(static::BELONGS_TO, 'Stripey_OptionTypeRecord'),
        );
    }


}