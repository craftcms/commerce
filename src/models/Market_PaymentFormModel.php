<?php

namespace Craft;

use Omnipay\Common\Helper as OmnipayHelper;
/**
 * Class Market_PaymentFormModel
 *
 * @property string firstName
 * @property string lastName
 * @property int    month
 * @property int    year
 * @property int    cvv
 * @property int    number
 *
 * @package Craft
 */
class Market_PaymentFormModel extends BaseModel
{
    public function rules()
    {
        return [
            ['firstName, lastName, month, year, cvv, number', 'required'],
            [
                'month',
                'numerical',
                'integerOnly' => true,
                'min'         => 1,
                'max'         => 12
            ],
            [
                'year',
                'numerical',
                'integerOnly' => true,
                'min'         => date('Y'),
                'max'         => date('Y') + 12
            ],
            ['cvv', 'numerical', 'integerOnly' => true],
            ['cvv', 'length', 'min' => 3, 'max' => 4],
            ['number', 'numerical', 'integerOnly' => true],
            ['number', 'length', 'max' => 19],
            ['number', 'creditCardLuhn']
        ];
    }

    public function creditCardLuhn($attribute,$params)
    {
        if(!OmnipayHelper::validateLuhn($this->$attribute)){
            $this->addError($attribute, Craft::t('Not a valid Credit Card Number'));
        }
    }

    protected function defineAttributes()
    {
        return [
            'firstName' => AttributeType::String,
            'lastName'  => AttributeType::String,
            'number'    => AttributeType::Number,
            'month'     => AttributeType::Number,
            'year'      => AttributeType::Number,
            'cvv'       => AttributeType::Number,
        ];
    }
}