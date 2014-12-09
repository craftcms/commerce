<?php
namespace Craft;


class Stripey_ChargeRecord extends BaseRecord{

    /**
     * Returns the name of the associated database table.
     *
     * @return string
     */
    public function getTableName()
    {
        return "stripey_charges";
    }

    /**
     * @inheritDoc BaseRecord::defineAttributes()
     *
     * @return array
     */
    protected function defineAttributes()
    {
        return array(
            'stripeId' => AttributeType::String,
            'amount' => AttributeType::Number,
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
//            'customer' => array(static::BELONGS_TO, 'Stripey_CustomerRecord'),
            'element' => array(static::BELONGS_TO, 'ElementRecord', 'id', 'required' => true, 'onDelete' => static::CASCADE),
        );
    }

}